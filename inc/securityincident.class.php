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

use GlpiPlugin\Grcmanager\Services\Incident\SecurityIncidentRules;

/**
 * Registre des incidents de sécurité de l'information (issue #29, ISO/IEC 27001:2022 Annexe A
 * A.5.24-27 "planification et préparation, évaluation et décision des incidents de sécurité de
 * l'information, réponse aux incidents de sécurité de l'information, apprentissage tiré des
 * incidents de sécurité de l'information"). Ces contrôles n'existaient jusqu'ici que comme lignes à
 * cocher dans la Déclaration d'Applicabilité (PluginGrcmanagerControl) : GLPI dispose nativement de
 * Ticket/Problem pour tracer un incident opérationnellement, mais rien ne le qualifie comme
 * "incident de sécurité de l'information" au sens ISO, ni ne le relie au registre de risques de ce
 * plugin pour boucler la clause A.5.27 ("tirer des enseignements des incidents").
 *
 * Ne duplique jamais le contenu d'un ticket GLPI existant : `linked_itemtype`/`linked_items_id`
 * (voir SecurityIncidentRules::ALLOWED_LINKED_ITEMTYPES) est une simple référence optionnelle
 * zéro-ou-un vers un Ticket ou Problem déjà existant, affichée via un lien construit avec
 * `CommonDBTM::getLinkURL()` natif (voir ticketLink() ci-dessous) plutôt qu'un recopiage de son
 * titre/sa description. Cardinalité plus simple que le lien risque <-> actifs CMDB many-to-many de
 * l'issue #25 (un incident correspond au plus à un seul Ticket/Problem en pratique) : deux colonnes
 * directes, pas une table de liaison.
 *
 * Lien optionnel zéro-ou-un vers un risque existant (`plugin_grcmanager_risks_id`, colonne
 * directe) : même convention que PluginGrcmanagerComplianceObligation (issue #30, voir son
 * docblock et SecurityIncidentRules::normalizeLinkedRiskId()/isLinkedToRisk()) plutôt qu'une
 * nouvelle table de liaison, la boucle "leçons apprises" que la clause A.5.27 demande explicitement.
 *
 * `root_cause`/`lessons_learned` suivent exactement la même convention "obligatoire seulement à la
 * clôture" que `corrective_action` sur PluginGrcmanagerNonconformity (issue #27/Sprint 4, clause
 * 10.2) : ni requis pour ouvrir un incident ni pour le passer en investigation/contenu, mais
 * bloquant pour le clôturer sans avoir documenté sa cause racine ET ce qui en a été appris (clause
 * A.5.27), voir SecurityIncidentRules::isClosureDocumentationMissing().
 *
 * `cia_impact` (axes confidentialité/intégrité/disponibilité affectés) est une liste de valeurs
 * séparées par des virgules sur une seule colonne varchar, pas une table de liaison ni un
 * multi-select GLPI/select2 : même convention déjà établie par `PluginGrcmanagerAudit.risk_categories`
 * (Sprint 4, TECH_DEBT.md) pour un ensemble fixe et petit de valeurs sur un seul enregistrement.
 * Rendue ici avec de simples cases à cocher HTML plutôt que
 * `Dropdown::showFromArray(..., ['multiple' => true])` (le widget select2 que
 * `PluginGrcmanagerAudit::showForm()` utilise pour son propre `risk_categories`) : un multi-select
 * select2 alimenté par un tableau PHP brut ne répond pas de façon fiable, en conditions réelles
 * (voir la leçon de test de ce même projet), à une sélection simulée par un simple événement JS
 * bas niveau, et n'apporte aucun bénéfice réel pour seulement 3 cases (pas de recherche, pas de
 * longue liste à faire défiler) - de simples cases à cocher sont ici à la fois plus simples et plus
 * fiables. Réutilise les noms d'axes de `ClassificationLevels::AXES` (issue #26) pour ne jamais
 * introduire un second vocabulaire confidentiality/integrity/availability.
 *
 * `users_id` (responsable de l'incident) et `description` (texte libre décrivant ce qui s'est
 * passé) ne sont pas explicitement listés par l'issue #29 mais ajoutés par cohérence avec CHAQUE
 * autre registre de ce plugin (risque, non-conformité, obligation, politique, audit...), qui ont
 * tous les deux : un registre d'incidents sans responsable assignable ni description libre aurait
 * été une régression d'utilisabilité par rapport au reste du plugin, pas une simplification
 * justifiée.
 */
class PluginGrcmanagerSecurityIncident extends CommonDBTM
{
    public static $rightname = 'plugin_grcmanager';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_grcmanager_securityincidents';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Incident de sécurité', 'Incidents de sécurité', $nb, 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-shield-bolt';
    }

    /**
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            'data_breach'         => __('Violation de données', 'grcmanager'),
            'malware'             => __('Logiciel malveillant', 'grcmanager'),
            'unauthorized_access' => __('Accès non autorisé', 'grcmanager'),
            'availability'        => __('Indisponibilité / déni de service', 'grcmanager'),
            'other'               => __('Autre', 'grcmanager'),
        ];
    }

    /**
     * Même échelle que PluginGrcmanagerNonconformity::getSeverities() (issue #29 : "reuse the SAME
     * severity scale"), dupliquée ici plutôt que partagée via un trait - voir le docblock de
     * SecurityIncidentRules pour le raisonnement (aucune autre échelle de sévérité de ce plugin
     * n'est factorisée non plus, seul un vrai calcul partagé, RiskAssessmentTrait, l'est).
     *
     * @return array<string, string>
     */
    public static function getSeverities(): array
    {
        return [
            'minor'    => __('Mineure', 'grcmanager'),
            'major'    => __('Majeure', 'grcmanager'),
            'critical' => __('Critique', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            'open'          => __('Ouvert', 'grcmanager'),
            'investigating' => __('En investigation', 'grcmanager'),
            'contained'     => __('Contenu', 'grcmanager'),
            'closed'        => __('Clôturé', 'grcmanager'),
        ];
    }

    /**
     * @return array<string, string> label per CIA axis, in SecurityIncidentRules::CIA_AXES order.
     */
    public static function getCiaAxisLabels(): array
    {
        return [
            'confidentiality' => __('Confidentialité', 'grcmanager'),
            'integrity'       => __('Intégrité', 'grcmanager'),
            'availability'    => __('Disponibilité', 'grcmanager'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        return $this->validateAndNormalize($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->validateAndNormalize($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validateAndNormalize(array $input)
    {
        if (array_key_exists('category', $input)) {
            $input['category'] = SecurityIncidentRules::normalizeCategory($input['category']);
        }

        if (array_key_exists('severity', $input)) {
            $input['severity'] = SecurityIncidentRules::normalizeSeverity($input['severity']);
        }

        if (array_key_exists('cia_impact', $input)) {
            $input['cia_impact'] = SecurityIncidentRules::normalizeCiaImpact($input['cia_impact']);
        }

        if (array_key_exists('plugin_grcmanager_risks_id', $input)) {
            $input['plugin_grcmanager_risks_id'] = SecurityIncidentRules::normalizeLinkedRiskId(
                $input['plugin_grcmanager_risks_id']
            );
        }

        if (array_key_exists('linked_itemtype', $input) || array_key_exists('linked_items_id', $input)) {
            $linked = SecurityIncidentRules::normalizeLinkedItem(
                (string) ($input['linked_itemtype'] ?? ($this->fields['linked_itemtype'] ?? '')),
                $input['linked_items_id'] ?? ($this->fields['linked_items_id'] ?? 0)
            );

            $input['linked_itemtype'] = $linked['itemtype'];
            $input['linked_items_id'] = $linked['items_id'];
        }

        // Statut : normalisé puis validé pour la clôture (clause A.5.27), même schéma que
        // PluginGrcmanagerNonconformity::validateAndNormalize() (issue #27) - recalculé à chaque
        // add()/update() à partir de $this->fields quand $input ne le fournit pas explicitement,
        // pour qu'éditer root_cause/lessons_learned sur un incident déjà clôturé ne puisse jamais
        // les vider en douce sans repasser par cette même validation.
        $status = SecurityIncidentRules::normalizeStatus(
            (string) ($input['status'] ?? ($this->fields['status'] ?? SecurityIncidentRules::DEFAULT_STATUS))
        );

        if (array_key_exists('status', $input)) {
            $input['status'] = $status;
        }

        $rootCause      = (string) ($input['root_cause'] ?? ($this->fields['root_cause'] ?? ''));
        $lessonsLearned = (string) ($input['lessons_learned'] ?? ($this->fields['lessons_learned'] ?? ''));

        if (SecurityIncidentRules::isClosureDocumentationMissing($status, $rootCause, $lessonsLearned)) {
            Session::addMessageAfterRedirect(
                __(
                    'La cause racine et les enseignements tirés sont obligatoires pour clôturer un '
                        . 'incident de sécurité.',
                    'grcmanager'
                ),
                false,
                ERROR
            );

            return false;
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
            'datatype' => 'itemlink',
            'itemtype' => self::class,
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
            'field'    => 'severity',
            'name'     => __('Sévérité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'status',
            'name'     => __('Statut', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'incident_date',
            'name'     => __('Date de l\'incident', 'grcmanager'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Responsable', 'grcmanager'),
            'datatype' => 'dropdown',
            'right'    => 'all',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'cia_impact',
            'name'     => __('Impact C/I/D', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => $this->getTable(),
            'field'    => 'plugin_grcmanager_risks_id',
            'name'     => PluginGrcmanagerRisk::getTypeName(1),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => $this->getTable(),
            'field'    => 'linked_items_id',
            'name'     => __('Ticket/Problem lié', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 10,
            'table'    => $this->getTable(),
            'field'    => 'description',
            'name'     => __('Description', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 11,
            'table'    => $this->getTable(),
            'field'    => 'root_cause',
            'name'     => __('Cause racine', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 12,
            'table'    => $this->getTable(),
            'field'    => 'lessons_learned',
            'name'     => __('Enseignements tirés', 'grcmanager'),
            'datatype' => 'text',
        ];

        // Row link, same "a list with no way back to showForm() is not self-explanatory" lesson
        // already applied throughout this plugin family.
        $tab[] = [
            'id'       => 13,
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
            case 'category':
                return self::categoryBadge($values[$field] ?? null);

            case 'severity':
                return self::severityBadge($values[$field] ?? null);

            case 'status':
                return self::statusBadge($values[$field] ?? null);

            case 'cia_impact':
                return self::ciaImpactBadges((string) ($values[$field] ?? ''));

            case 'plugin_grcmanager_risks_id':
                return self::riskLink((int) ($values[$field] ?? 0));

            case 'linked_items_id':
                return self::ticketLink(
                    (string) ($values['linked_itemtype'] ?? ''),
                    (int) ($values[$field] ?? 0)
                );
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Real `<select>` filter widgets for category/severity/status, same lesson as
     * PluginGrcmanagerRisk::getSpecificValueToSelect(). `cia_impact` (a comma-separated column)
     * uses the same single-value "contains" filter already accepted for
     * PluginGrcmanagerAudit::risk_categories (TECH_DEBT.md Sprint 4). `plugin_grcmanager_risks_id`
     * and `linked_items_id` are left to the parent's default (free-text search on the raw numeric
     * id), same choice already made for `plugin_grcmanager_audits_id` on
     * PluginGrcmanagerNonconformity.
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
            case 'category':
                return Dropdown::showFromArray($name, self::getCategories(), $options);

            case 'severity':
                return Dropdown::showFromArray($name, self::getSeverities(), $options);

            case 'status':
                return Dropdown::showFromArray($name, self::getStatuses(), $options);

            case 'cia_impact':
                return Dropdown::showFromArray($name, self::getCiaAxisLabels(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    private static function categoryBadge(?string $value): string
    {
        $map = [
            'data_breach'         => ['bg-red-lt', 'ti-file-shredder', __('Violation de données', 'grcmanager')],
            'malware'             => ['bg-orange-lt', 'ti-virus', __('Logiciel malveillant', 'grcmanager')],
            'unauthorized_access' => ['bg-purple-lt', 'ti-lock-open', __('Accès non autorisé', 'grcmanager')],
            'availability'        => [
                'bg-yellow-lt',
                'ti-plug-connected-x',
                __('Indisponibilité / déni de service', 'grcmanager'),
            ],
            'other'               => ['bg-secondary-lt', 'ti-dots', __('Autre', 'grcmanager')],
        ];

        [$class, $icon, $label] = $map[$value] ?? ['bg-secondary-lt', 'ti-help', (string) $value];

        return '<span class="badge ' . $class . '"><i class="ti ' . $icon . ' me-1"></i>'
            . htmlescape($label) . '</span>';
    }

    private static function severityBadge(?string $value): string
    {
        $map = [
            'minor'    => ['bg-yellow-lt', 'ti-alert-circle', __('Mineure', 'grcmanager')],
            'major'    => ['bg-orange-lt', 'ti-alert-triangle', __('Majeure', 'grcmanager')],
            'critical' => ['bg-red-lt', 'ti-alert-octagon', __('Critique', 'grcmanager')],
        ];

        [$class, $icon, $label] = $map[$value] ?? ['bg-secondary-lt', 'ti-help', (string) $value];

        return '<span class="badge ' . $class . '"><i class="ti ' . $icon . ' me-1"></i>'
            . htmlescape($label) . '</span>';
    }

    private static function statusBadge(?string $value): string
    {
        $map = [
            'open'          => ['bg-red-lt', 'ti-flag', __('Ouvert', 'grcmanager')],
            'investigating' => ['bg-blue-lt', 'ti-search', __('En investigation', 'grcmanager')],
            'contained'     => ['bg-yellow-lt', 'ti-shield-check', __('Contenu', 'grcmanager')],
            'closed'        => ['bg-green-lt', 'ti-check', __('Clôturé', 'grcmanager')],
        ];

        [$class, $icon, $label] = $map[$value] ?? ['bg-secondary-lt', 'ti-help', (string) $value];

        return '<span class="badge ' . $class . '"><i class="ti ' . $icon . ' me-1"></i>'
            . htmlescape($label) . '</span>';
    }

    private static function ciaImpactBadges(string $csv): string
    {
        $axes = SecurityIncidentRules::splitCiaImpact($csv);

        if ($axes === []) {
            return '';
        }

        $labels = self::getCiaAxisLabels();
        $badges = [];

        foreach ($axes as $axis) {
            $badges[] = '<span class="badge bg-azure-lt">' . htmlescape($labels[$axis] ?? $axis) . '</span>';
        }

        return implode(' ', $badges);
    }

    private static function riskLink(int $riskId): string
    {
        if (!SecurityIncidentRules::isLinkedToRisk($riskId)) {
            return '';
        }

        $risk = new PluginGrcmanagerRisk();
        if (!$risk->getFromDB($riskId)) {
            return '';
        }

        return '<a href="' . htmlescape($risk->getFormURLWithID($riskId)) . '">'
            . htmlescape($risk->fields['title']) . '</a>';
    }

    /**
     * Renders the optional Ticket/Problem reference as a real link, built from the referenced
     * item's own `getLinkURL()` (issue #29: "using GLPI's own getLinkURL()-style convention") -
     * never a recopy of its title/description, only ever a pointer to the item that already exists
     * in GLPI.
     */
    private static function ticketLink(string $itemtype, int $itemsId): string
    {
        if (!SecurityIncidentRules::isLinkedToItem($itemtype, $itemsId)) {
            return '';
        }

        if (!is_a($itemtype, CommonDBTM::class, true)) {
            return '';
        }

        $item = new $itemtype();
        if (!$item->getFromDB($itemsId)) {
            return sprintf(__('%1$s #%2$d (supprimé)', 'grcmanager'), $itemtype::getTypeName(1), $itemsId);
        }

        return '<a href="' . htmlescape($item->getLinkURL()) . '">'
            . htmlescape($itemtype::getTypeName(1) . ' #' . $itemsId . ' - ' . $item->getName()) . '</a>';
    }

    public function showForm($ID, array $options = []): bool
    {
        global $DB;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo '<tr class="tab_bg_1"><td>' . __('Titre', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('title', ['value' => $this->fields['title'] ?? '', 'size' => 80]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Catégorie', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('category', self::getCategories(), [
            'value' => $this->fields['category'] ?? SecurityIncidentRules::DEFAULT_CATEGORY,
        ]);
        echo '</td>';

        echo '<td>' . __('Sévérité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('severity', self::getSeverities(), [
            'value' => $this->fields['severity'] ?? SecurityIncidentRules::DEFAULT_SEVERITY,
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Date de l\'incident', 'grcmanager') . '</td><td>';
        Html::showDateTimeField('incident_date', ['value' => $this->fields['incident_date'] ?? '']);
        echo '</td>';

        echo '<td>' . __('Responsable', 'grcmanager') . '</td><td>';
        User::dropdown([
            'name'  => 'users_id',
            'value' => $this->fields['users_id'] ?? 0,
            'right' => 'all',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Statut', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('status', self::getStatuses(), [
            'value' => $this->fields['status'] ?? SecurityIncidentRules::DEFAULT_STATUS,
        ]);
        echo '</td><td colspan="2"></td></tr>';

        // Issue #29 : cases a cocher HTML simples (jamais un select2 multi-valeurs), voir le
        // docblock de classe pour le raisonnement complet (fiabilite de persistance + seulement 3
        // valeurs fixes).
        echo '<tr class="tab_bg_1"><td>' . __('Impact C/I/D', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        $selectedAxes = SecurityIncidentRules::splitCiaImpact((string) ($this->fields['cia_impact'] ?? ''));
        foreach (self::getCiaAxisLabels() as $axis => $label) {
            $checked = in_array($axis, $selectedAxes, true) ? ' checked' : '';
            echo '<label class="form-check form-check-inline">';
            echo '<input type="checkbox" class="form-check-input" name="cia_impact[]" value="'
                . htmlescape($axis) . '"' . $checked . '>';
            echo '<span class="form-check-label">' . htmlescape($label) . '</span>';
            echo '</label>';
        }
        echo '<br><small class="form-hint">' . __(
            'Axe(s) confidentialité/intégrité/disponibilité affecté(s) par cet incident.',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Description', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="description" class="form-control" rows="3">'
            . htmlescape($this->fields['description'] ?? '') . '</textarea>';
        echo '</td></tr>';

        // Issue #29 : reference legere vers un Ticket/Problem GLPI deja existant, jamais une
        // duplication de son contenu (voir le docblock de classe).
        $linkedItemtype = (string) ($this->fields['linked_itemtype'] ?? '');
        $linkedItemsId  = (int) ($this->fields['linked_items_id'] ?? 0);

        echo '<tr class="tab_bg_1"><td>' . __('Type d\'élément lié', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('linked_itemtype', [
            ''         => Dropdown::EMPTY_VALUE,
            'Ticket'   => Ticket::getTypeName(1),
            'Problem'  => Problem::getTypeName(1),
        ], [
            'value' => $linkedItemtype,
        ]);
        echo '</td>';

        echo '<td>' . __('Ticket/Problem lié (optionnel)', 'grcmanager') . '</td><td>';
        $linkedOptions = [0 => Dropdown::EMPTY_VALUE];
        foreach (SecurityIncidentRules::ALLOWED_LINKED_ITEMTYPES as $itemtype) {
            if ($linkedItemtype !== '' && $itemtype !== $linkedItemtype) {
                continue;
            }

            foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $itemtype::getTable()]) as $row) {
                $linkedOptions[(int) $row['id']] = sprintf(
                    '%s #%d - %s',
                    $itemtype::getTypeName(1),
                    (int) $row['id'],
                    $row['name'] !== '' ? $row['name'] : sprintf('#%d', (int) $row['id'])
                );
            }
        }
        Dropdown::showFromArray('linked_items_id', $linkedOptions, [
            'value' => $linkedItemsId,
        ]);
        echo '<small class="form-hint">' . __(
            'Choisir d\'abord un type ci-contre pour filtrer la liste.',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        // Issue #29 : lien optionnel zero-ou-un vers un risque du registre (boucle "lecons
        // apprises" A.5.27), meme convention que PluginGrcmanagerComplianceObligation::showForm()
        // (issue #30) - un simple Dropdown::showFromArray non-multiple avec une option "Aucun".
        $riskTitles = [0 => Dropdown::EMPTY_VALUE];
        foreach ($DB->request(['SELECT' => ['id', 'title'], 'FROM' => PluginGrcmanagerRisk::getTable()]) as $row) {
            $riskTitles[(int) $row['id']] = $row['title'];
        }

        echo '<tr class="tab_bg_1"><td>' . __('Risque lié (optionnel)', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        Dropdown::showFromArray('plugin_grcmanager_risks_id', $riskTitles, [
            'value' => $this->fields['plugin_grcmanager_risks_id'] ?? 0,
        ]);
        echo '<small class="form-hint">' . __(
            'À renseigner si cet incident a mis en évidence un risque identifié dans le registre '
                . 'de risques (ou en a révélé un nouveau).',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Cause racine', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="root_cause" class="form-control" rows="3">'
            . htmlescape($this->fields['root_cause'] ?? '') . '</textarea>';
        echo '<small class="form-hint">' . __(
            'Obligatoire, avec les enseignements tirés ci-dessous, pour clôturer cet incident.',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Enseignements tirés', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="lessons_learned" class="form-control" rows="3">'
            . htmlescape($this->fields['lessons_learned'] ?? '') . '</textarea>';
        echo '<small class="form-hint">' . __(
            'Ce qui a été appris de cet incident (clause A.5.27) : à documenter avant clôture.',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        $this->showFormButtons($options);

        return true;
    }
}
