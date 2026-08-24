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

use GlpiPlugin\Grcmanager\Services\Risk\RiskScoringService;

/**
 * Generic organizational risk register (ISO 27001 clause 6.1.2/8.2), see issue #89 on the sibling
 * repository glpi-vulnerability-manager for the full scope this plugin covers. Deliberately
 * broader than that sibling plugin's own CVE-specific risk model: a row here can describe a
 * people, process, physical, third-party or technical risk, not just a scored vulnerability.
 */
class PluginGrcmanagerRisk extends CommonDBTM
{
    public static $rightname = 'plugin_grcmanager';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_grcmanager_risks';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Risque', 'Risques', $nb, 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-shield-exclamation';
    }

    /**
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            'people'       => __('Humain', 'grcmanager'),
            'process'      => __('Processus', 'grcmanager'),
            'physical'     => __('Physique', 'grcmanager'),
            'third_party'  => __('Tiers / fournisseur', 'grcmanager'),
            'technical'    => __('Technique', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getProbabilities(): array
    {
        return [
            'rare'     => __('Rare', 'grcmanager'),
            'possible' => __('Possible', 'grcmanager'),
            'probable' => __('Probable', 'grcmanager'),
            'certain'  => __('Quasi certaine', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getImpacts(): array
    {
        return [
            'low'      => __('Faible', 'grcmanager'),
            'medium'   => __('Moyen', 'grcmanager'),
            'high'     => __('Élevé', 'grcmanager'),
            'critical' => __('Critique', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getTreatments(): array
    {
        return [
            ''         => __('Aucune décision', 'grcmanager'),
            'accept'   => __('Accepter', 'grcmanager'),
            'mitigate' => __('Mitiger', 'grcmanager'),
            'transfer' => __('Transférer', 'grcmanager'),
            'avoid'    => __('Éviter', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            'identified'   => __('Identifié', 'grcmanager'),
            'in_treatment' => __('En traitement', 'grcmanager'),
            'accepted'     => __('Accepté', 'grcmanager'),
            'closed'       => __('Clôturé', 'grcmanager'),
        ];
    }

    /**
     * Keeps `computed_score`/`risk_level` derived from `probability`/`impact` at all times, see
     * RiskScoringService — neither field is ever entered manually.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        return $this->computeRiskLevel($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->computeRiskLevel($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function computeRiskLevel(array $input): array
    {
        $probability = $input['probability'] ?? ($this->fields['probability'] ?? null);
        $impact      = $input['impact'] ?? ($this->fields['impact'] ?? null);

        if ($probability !== null && $impact !== null) {
            $scoringService = new RiskScoringService();
            $score = $scoringService->score((string) $probability, (string) $impact);

            $input['computed_score'] = $score;
            $input['risk_level']     = $scoringService->level($score);
        }

        return $input;
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
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'category',
            'name'     => __('Catégorie', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'probability',
            'name'     => __('Probabilité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'impact',
            'name'     => __('Impact', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'risk_level',
            'name'     => __('Niveau de risque', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => $this->getTable(),
            'field'    => 'treatment',
            'name'     => __('Traitement', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Propriétaire', 'grcmanager'),
            'datatype' => 'dropdown',
            'right'    => 'all',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => $this->getTable(),
            'field'    => 'status',
            'name'     => __('Statut', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => $this->getTable(),
            'field'    => 'review_date',
            'name'     => __('Date de revue', 'grcmanager'),
            'datatype' => 'date',
        ];

        // Row link (a list with no way back to showForm() is not self-explanatory, lesson learned
        // on the sibling plugin glpi-vulnerability-manager, see its own inc/risk.class.php and
        // TECH_DEBT.md, applied here from the very first commit rather than fixed after the fact).
        $tab[] = [
            'id'       => 10,
            'table'    => $this->getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'itemlink',
            'itemtype' => self::class,
        ];

        return $tab;
    }

    /**
     * Translates the raw DB enum values into color-coded Tabler badges instead of showing the
     * untranslated raw string, same UX lesson already applied on the sibling plugin
     * glpi-vulnerability-manager after a real live-instance review found its lists unusable
     * without it, see TECH_DEBT.md there. Applied here from the start.
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'category':
                return self::plainBadge('bg-azure-lt', self::getCategories(), $values[$field] ?? null);

            case 'probability':
                return self::plainBadge('bg-blue-lt', self::getProbabilities(), $values[$field] ?? null);

            case 'impact':
            case 'risk_level':
                return self::riskLevelBadge($values[$field] ?? null);

            case 'treatment':
                return self::plainBadge('bg-purple-lt', self::getTreatments(), $values[$field] ?? null);

            case 'status':
                return self::statusBadge($values[$field] ?? null);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    private static function plainBadge(string $class, array $labels, ?string $value): string
    {
        $label = $labels[$value] ?? (string) $value;

        if ($label === '') {
            return '';
        }

        return '<span class="badge ' . $class . '">' . htmlescape($label) . '</span>';
    }

    /**
     * Shared low/medium/high/critical scale, used for both `impact` and `risk_level`.
     */
    public static function riskLevelBadge(?string $value): string
    {
        $levels = [
            'low'      => ['bg-green-lt', __('Faible', 'grcmanager')],
            'medium'   => ['bg-yellow-lt', __('Moyen', 'grcmanager')],
            'high'     => ['bg-orange-lt', __('Élevé', 'grcmanager')],
            'critical' => ['bg-red-lt', __('Critique', 'grcmanager')],
        ];

        [$class, $label] = $levels[$value] ?? ['bg-secondary-lt', (string) $value];
        $icon = in_array($value, ['high', 'critical'], true)
            ? '<i class="ti ti-alert-triangle me-1"></i>'
            : '';

        return '<span class="badge ' . $class . '">' . $icon . htmlescape($label) . '</span>';
    }

    private static function statusBadge(?string $value): string
    {
        $map = [
            'identified'   => ['bg-secondary-lt', 'ti-eye', __('Identifié', 'grcmanager')],
            'in_treatment' => ['bg-blue-lt', 'ti-tool', __('En traitement', 'grcmanager')],
            'accepted'     => ['bg-green-lt', 'ti-shield-check', __('Accepté', 'grcmanager')],
            'closed'       => ['bg-dark-lt', 'ti-check', __('Clôturé', 'grcmanager')],
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

        echo '<tr class="tab_bg_1"><td>' . __('Catégorie', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('category', self::getCategories(), [
            'value' => $this->fields['category'] ?? 'process',
        ]);
        echo '</td>';

        echo '<td>' . __('Propriétaire', 'grcmanager') . '</td><td>';
        User::dropdown([
            'name'  => 'users_id',
            'value' => $this->fields['users_id'] ?? 0,
            'right' => 'all',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Probabilité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('probability', self::getProbabilities(), [
            'value' => $this->fields['probability'] ?? 'possible',
        ]);
        echo '</td>';

        echo '<td>' . __('Impact', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('impact', self::getImpacts(), [
            'value' => $this->fields['impact'] ?? 'medium',
        ]);
        echo '</td></tr>';

        if ($this->isNewID($ID) === false) {
            echo '<tr class="tab_bg_1"><td>' . __('Niveau de risque', 'grcmanager') . '</td><td>';
            echo self::riskLevelBadge($this->fields['risk_level'] ?? null);
            echo '</td>';

            echo '<td>' . __('Score', 'grcmanager') . '</td><td>';
            echo htmlescape((string) ($this->fields['computed_score'] ?? '0'));
            echo '</td></tr>';
        }

        echo '<tr class="tab_bg_1"><td>' . __('Traitement', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('treatment', self::getTreatments(), [
            'value' => $this->fields['treatment'] ?? '',
        ]);
        echo '</td>';

        echo '<td>' . __('Statut', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('status', self::getStatuses(), [
            'value' => $this->fields['status'] ?? 'identified',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Date de revue', 'grcmanager') . '</td><td>';
        Html::showDateField('review_date', ['value' => $this->fields['review_date'] ?? '']);
        echo '</td><td colspan="2"></td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Description', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="description" class="form-control" rows="4">'
            . htmlescape($this->fields['description'] ?? '') . '</textarea>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Justification', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="justification" class="form-control" rows="4">'
            . htmlescape($this->fields['justification'] ?? '') . '</textarea>';
        echo '</td></tr>';

        $this->showFormButtons($options);

        return true;
    }
}
