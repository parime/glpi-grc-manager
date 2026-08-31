<?php

/**
 * -------------------------------------------------------------------------
 * GLPI GRC Manager plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-grc-manager
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Grcmanager\Services\Objective\ObjectiveStatuses;

/**
 * ISMS security objective (issue #32, ISO 27001 clause 6.2): the plugin's 15-card dashboard
 * (DashboardCardService) shows the ISMS's CURRENT state, a snapshot, but clause 6.2 explicitly
 * requires the organization to SET measurable objectives (e.g. "réduire de 20% les
 * non-conformités récurrentes d'ici fin d'année") and demonstrate progress toward them over time —
 * a trajectory, which is what this class and its measurement history
 * (PluginGrcmanagerObjectiveMeasurement) exist to hold.
 *
 * `target_value`/`target_description` are two independent, both-optional target fields rather than
 * forcing every objective into a single numeric target: some objectives genuinely have a number to
 * hit ("réduire de 20%"), others are qualitative only ("obtenir la certification ISO 27001",
 * "déployer le MFA sur tous les comptes à privilèges") and have nothing meaningful to put in a
 * `target_value` column. Whichever is set (or both) is display-only context for the human reading
 * the measurement history below; this class does not itself compute "on track" vs "at risk" from
 * the numbers (see `status`, entered manually like every other status column in this plugin
 * family — RiskAssessmentTrait::getStatuses(), PluginGrcmanagerAudit::getStatuses()... — an
 * automatic "distance to target" computation was judged premature for a first version, especially
 * for the qualitative-only case where no such computation is even possible, see TECH_DEBT.md).
 *
 * Progress itself lives in `PluginGrcmanagerObjectiveMeasurement`
 * (glpi_plugin_grcmanager_objectivemeasurements), a simple manually-entered time series ("as of
 * this review, we're at X"), deliberately NOT auto-computed from other plugin data (e.g. pulling a
 * live non-conformity count): matches this plugin's own "une version minimale et testée vaut mieux
 * qu'une version élaborée et non testée" philosophy (TECH_DEBT.md Sprint 2), a future issue can
 * wire up auto-computed measurements if that is ever wanted. Shown as a plain chronological table
 * on this class' own `showForm()` (see showMeasurementHistory() below), not a chart: an honest way
 * to show "trajectory" without a new charting dependency, consistent with every other manual-HTML
 * form in this plugin (TECH_DEBT.md Sprint 1).
 */
class PluginGrcmanagerObjective extends CommonDBTM
{
    public static $rightname = 'plugin_grcmanager';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_grcmanager_objectives';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Objectif ISMS', 'Objectifs ISMS', $nb, 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-target-arrow';
    }

    /**
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            ObjectiveStatuses::NOT_STARTED => __('Non démarré', 'grcmanager'),
            ObjectiveStatuses::ON_TRACK    => __('Sur la bonne voie', 'grcmanager'),
            ObjectiveStatuses::AT_RISK     => __('À risque', 'grcmanager'),
            ObjectiveStatuses::ACHIEVED    => __('Atteint', 'grcmanager'),
            ObjectiveStatuses::MISSED      => __('Manqué', 'grcmanager'),
        ];
    }

    /**
     * Never trusts `status` from the request as-is, same defensive posture as
     * PluginGrcmanagerAssetClassification::sanitizeLevels(): an invalid/tampered value always
     * falls back to the safe default rather than being stored as garbage.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitizeStatus(array $input): array
    {
        if (array_key_exists('status', $input) && !ObjectiveStatuses::isValid($input['status'])) {
            $input['status'] = ObjectiveStatuses::NOT_STARTED;
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        return $this->sanitizeStatus($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->sanitizeStatus($input);
    }

    /**
     * Whether this objective has a numeric target to plot measurements against (see class
     * docblock): drives both the required-ness of a new measurement's `value`
     * (ObjectiveMeasurementValidator) and the "Valeur" column header hint on the history table.
     */
    public function hasNumericTarget(): bool
    {
        $targetValue = $this->fields['target_value'] ?? null;

        return $targetValue !== null && $targetValue !== '';
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];

        $tab[] = [
            'id'       => 1,
            'table'    => $this->getTable(),
            'field'    => 'title',
            'name'     => __('Titre', 'grcmanager'),
            'datatype' => 'itemlink',
            'itemtype' => self::class,
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'status',
            'name'     => __('Statut', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'target_date',
            'name'     => __('Échéance', 'grcmanager'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'target_value',
            'name'     => __('Valeur cible', 'grcmanager'),
            'datatype' => 'decimal',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'target_description',
            'name'     => __('Cible qualitative', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Propriétaire', 'grcmanager'),
            'datatype' => 'dropdown',
            'right'    => 'all',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'description',
            'name'     => __('Description', 'grcmanager'),
            'datatype' => 'text',
        ];

        // Row link, same "a list with no way back to showForm() is not self-explanatory" lesson
        // already applied throughout this plugin family.
        $tab[] = [
            'id'       => 8,
            'table'    => $this->getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'itemlink',
            'itemtype' => self::class,
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'status':
                return self::statusBadge($values[$field] ?? null);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Real `<select>` filter widget for `status`, same lesson as every other fixed-enum column in
     * this plugin family.
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $options['display'] = false;
        $options['name']    = $name;
        $options['value']   = $values[$field] ?? '';

        switch ($field) {
            case 'status':
                return Dropdown::showFromArray($name, self::getStatuses(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function statusBadge(?string $value): string
    {
        $map = [
            ObjectiveStatuses::NOT_STARTED => ['bg-secondary-lt', 'ti-player-stop', __('Non démarré', 'grcmanager')],
            ObjectiveStatuses::ON_TRACK    => ['bg-green-lt', 'ti-trending-up', __('Sur la bonne voie', 'grcmanager')],
            ObjectiveStatuses::AT_RISK     => ['bg-orange-lt', 'ti-alert-triangle', __('À risque', 'grcmanager')],
            ObjectiveStatuses::ACHIEVED    => ['bg-teal-lt', 'ti-trophy', __('Atteint', 'grcmanager')],
            ObjectiveStatuses::MISSED      => ['bg-red-lt', 'ti-x', __('Manqué', 'grcmanager')],
        ];

        [$class, $icon, $label] = $map[$value] ?? ['bg-secondary-lt', 'ti-help', (string) $value];

        return '<span class="badge ' . $class . '"><i class="ti ' . $icon . ' me-1"></i>'
            . htmlescape($label) . '</span>';
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo '<tr class="tab_bg_1"><td>' . __('Titre', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('title', ['value' => $this->fields['title'] ?? '', 'size' => 80]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Propriétaire', 'grcmanager') . '</td><td>';
        User::dropdown([
            'name'  => 'users_id',
            'value' => $this->fields['users_id'] ?? 0,
            'right' => 'all',
        ]);
        echo '</td>';

        echo '<td>' . __('Statut', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('status', self::getStatuses(), [
            'value' => $this->fields['status'] ?? ObjectiveStatuses::NOT_STARTED,
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Échéance', 'grcmanager') . '</td><td>';
        Html::showDateField('target_date', ['value' => $this->fields['target_date'] ?? '']);
        echo '</td>';

        echo '<td>' . __('Valeur cible', 'grcmanager') . '</td><td>';
        echo Html::input('target_value', [
            'value' => $this->fields['target_value'] ?? '',
            'size'  => 10,
        ]);
        echo '<small class="form-hint">' . __(
            'Laisser vide si cet objectif n\'a pas d\'indicateur chiffré (objectif purement qualitatif).',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Cible qualitative', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="target_description" class="form-control" rows="2">'
            . htmlescape($this->fields['target_description'] ?? '') . '</textarea>';
        echo '<small class="form-hint">' . __(
            'Description libre de la cible visée, en complément ou à la place d\'une valeur chiffrée '
            . '(ex. "obtenir la certification ISO 27001", "déployer le MFA sur tous les comptes à '
            . 'privilèges").',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Description', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="description" class="form-control" rows="3">'
            . htmlescape($this->fields['description'] ?? '') . '</textarea>';
        echo '</td></tr>';

        $this->showFormButtons($options);

        // La saisie de mesures n'a de sens que pour un objectif déjà enregistré (il lui faut un id
        // réel auquel rattacher glpi_plugin_grcmanager_objectivemeasurements) : jamais affichée sur
        // le formulaire de création, avant le premier enregistrement.
        if (!$this->isNewID($ID)) {
            $this->showMeasurementHistory((int) $ID);
        }

        return true;
    }

    /**
     * Mini formulaire d'ajout (date + valeur + commentaire, voir
     * front/objectivemeasurement.form.php) suivi de l'historique chronologique des mesures déjà
     * enregistrées : un tableau simple, honnête, montre la trajectoire sans nécessiter de
     * dépendance de graphique supplémentaire (voir le docblock de cette classe).
     *
     * En dehors de `showFormHeader()`/`showFormButtons()` ci-dessus (donc en dehors du <form>
     * qu'ils ouvrent/ferment) : un <form> HTML ne peut pas en contenir un second, et ce mini
     * formulaire poste vers un contrôleur différent (front/objectivemeasurement.form.php) que celui
     * de l'objectif lui-même.
     */
    private function showMeasurementHistory(int $objectiveId): void
    {
        global $CFG_GLPI;

        $canEdit = Session::haveRight(self::$rightname, UPDATE);

        echo '<div class="card mt-3"><div class="card-body">';
        echo '<h3>' . __('Historique des mesures', 'grcmanager') . '</h3>';

        if ($canEdit) {
            $formUrl = $CFG_GLPI['root_doc'] . '/plugins/grcmanager/front/objectivemeasurement.form.php';
            echo '<form method="post" action="' . htmlescape($formUrl) . '" class="mb-3">';
            echo Html::hidden('plugin_grcmanager_objectives_id', ['value' => $objectiveId]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

            echo '<table class="table">';
            echo '<tr>';
            echo '<td>' . __('Date de la mesure', 'grcmanager') . '</td>';
            echo '<td>' . __('Valeur', 'grcmanager') . '</td>';
            echo '<td>' . __('Commentaire', 'grcmanager') . '</td>';
            echo '<td></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td>';
            Html::showDateField('measurement_date', ['value' => date('Y-m-d')]);
            echo '</td>';
            echo '<td>' . Html::input('value', ['size' => 8]) . '</td>';
            echo '<td><input type="text" name="comment" class="form-control"></td>';
            echo '<td>' . Html::submit(__('Ajouter'), ['name' => 'add']) . '</td>';
            echo '</tr>';
            echo '</table>';

            echo '<small class="form-hint">' . ($this->hasNumericTarget()
                ? __('Une valeur numérique est obligatoire pour cet objectif chiffré.', 'grcmanager')
                : __(
                    'Objectif qualitatif : la valeur reste optionnelle, mais un commentaire est alors '
                    . 'obligatoire.',
                    'grcmanager'
                )) . '</small>';
            echo '</form>';
        }

        echo '<table class="table">';
        echo '<thead><tr>';
        echo '<th>' . __('Date de la mesure', 'grcmanager') . '</th>';
        echo '<th>' . __('Valeur', 'grcmanager') . '</th>';
        echo '<th>' . __('Commentaire', 'grcmanager') . '</th>';
        if ($canEdit) {
            echo '<th></th>';
        }
        echo '</tr></thead><tbody>';

        $measurements = PluginGrcmanagerObjectiveMeasurement::getMeasurements($objectiveId);

        if (count($measurements) === 0) {
            $colspan = $canEdit ? 4 : 3;
            echo '<tr><td colspan="' . $colspan . '" class="text-muted">'
                . __('Aucune mesure enregistrée pour le moment.', 'grcmanager') . '</td></tr>';
        }

        foreach ($measurements as $measurement) {
            echo '<tr>';
            echo '<td>' . htmlescape(Html::convDate($measurement['measurement_date'] ?? '')) . '</td>';
            echo '<td>' . ($measurement['value'] === null ? '' : htmlescape((string) $measurement['value'])) . '</td>';
            echo '<td>' . htmlescape((string) ($measurement['comment'] ?? '')) . '</td>';

            if ($canEdit) {
                echo '<td>';
                $deleteUrl = $CFG_GLPI['root_doc'] . '/plugins/grcmanager/front/objectivemeasurement.form.php';
                echo '<form method="post" action="' . htmlescape($deleteUrl) . '" '
                    . 'onsubmit="return confirm(\'' . __('Confirmer la suppression ?', 'grcmanager') . '\');">';
                echo Html::hidden('id', ['value' => $measurement['id']]);
                echo Html::hidden('plugin_grcmanager_objectives_id', ['value' => $objectiveId]);
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                echo '<button type="submit" name="purge" class="btn btn-sm btn-outline-danger">'
                    . '<i class="ti ti-trash"></i></button>';
                echo '</form>';
                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div></div>';
    }
}
