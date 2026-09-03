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

use GlpiPlugin\Grcmanager\Services\Classification\ClassificationLevels;

/**
 * Confidentialité/Intégrité/Disponibilité (C/I/D) classification register (issue #26, ISO/IEC
 * 27001:2022 controls A.5.9/A.5.12/A.8.2). Deliberately its OWN small register, keyed directly on
 * the real asset (itemtype/items_id, one row per asset via `unicity_link`), not a field bolted
 * onto the risk <-> asset link table from issue #25
 * (glpi_plugin_grcmanager_risks_items/PluginGrcmanagerRisk::getLinkedAssets()): a classification is
 * an inherent property of the asset itself ("the HR database is confidentiality-sensitive"),
 * independent of any one risk that happens to mention it, and must still exist/be readable even
 * for an asset with zero linked risks.
 *
 * Reuses the exact same linkable-itemtype list as issue #25
 * (GlpiPlugin\Grcmanager\Services\Risk\LinkableItemtypes / PluginGrcmanagerRisk::
 * getLinkableItemtypes()) rather than inventing a second, possibly-diverging list of "which GLPI
 * itemtypes does this plugin care about" — see setup.php for the identical
 * Plugin::registerClass()/addtabon mechanism already used for the Sprint-#25 "Risques" reverse tab,
 * applied here for a second, independent tab.
 */
class PluginGrcmanagerAssetClassification extends CommonDBTM
{
    public static $rightname = 'plugin_grcmanager';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_grcmanager_assetclassifications';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Classification C/I/D', 'Classifications C/I/D', $nb, 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-shield-lock';
    }

    /**
     * @return array<string, string>
     */
    public static function getLevels(): array
    {
        return [
            ClassificationLevels::LOW    => __('Faible', 'grcmanager'),
            ClassificationLevels::MEDIUM => __('Moyen', 'grcmanager'),
            ClassificationLevels::HIGH   => __('Élevé', 'grcmanager'),
        ];
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
            'field'    => 'itemtype',
            'name'     => __('Type d\'actif', 'grcmanager'),
            'datatype' => 'itemtypename',
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'confidentiality',
            'name'     => __('Confidentialité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'integrity',
            'name'     => __('Intégrité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'availability',
            'name'     => __('Disponibilité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if (in_array($field, ClassificationLevels::AXES, true)) {
            return self::levelBadge($values[$field] ?? null);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Same low/medium/high scale for all three axes, color-coded like every other enum badge in
     * this plugin (RiskAssessmentTrait::riskLevelBadge()), but only 3 levels (no "critical") — see
     * ClassificationLevels' own docblock for why this scale is deliberately narrower than the risk
     * impact scale it otherwise resembles.
     */
    public static function levelBadge(?string $value): string
    {
        $levels = [
            ClassificationLevels::LOW    => ['bg-green-lt', __('Faible', 'grcmanager')],
            ClassificationLevels::MEDIUM => ['bg-yellow-lt', __('Moyen', 'grcmanager')],
            ClassificationLevels::HIGH   => ['bg-orange-lt', __('Élevé', 'grcmanager')],
        ];

        if (!ClassificationLevels::isValid($value)) {
            return '<span class="text-muted">' . __('Non classifié', 'grcmanager') . '</span>';
        }

        [$class, $label] = $levels[$value];

        return '<span class="badge ' . $class . '">' . htmlescape($label) . '</span>';
    }

    /**
     * Never trusts confidentiality/integrity/availability from the request as-is: an
     * invalid/tampered value always falls back to '' ("not set on this axis", see
     * src/Install/Installer.php's column comment), exactly the empty-string "no decision yet"
     * convention this plugin already uses for `PluginGrcmanagerRisk::treatment`, never stored as
     * garbage nor silently guessed.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitizeLevels(array $input): array
    {
        foreach (ClassificationLevels::AXES as $axis) {
            if (array_key_exists($axis, $input)) {
                $input[$axis] = ClassificationLevels::sanitize($input[$axis]) ?? '';
            }
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        return $this->sanitizeLevels($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->sanitizeLevels($input);
    }

    /**
     * @return array<string, mixed>|null raw DB row for this asset's classification, or null if it
     *         has never been classified at all (distinct from "classified with all axes empty",
     *         which cannot happen through the form below but is handled the same way by
     *         ClassificationLevels::isClassified() regardless).
     */
    public static function getByItem(string $itemtype, int $itemsId): ?array
    {
        global $DB;

        $criteria = [
            'FROM'  => self::getTable(),
            'WHERE' => ['itemtype' => $itemtype, 'items_id' => $itemsId],
        ];

        foreach ($DB->request($criteria) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>> items_id => raw DB row, every classified asset of
     *         one itemtype in a single query. Used by PluginGrcmanagerRisk::showForm() to annotate
     *         each option of its per-itemtype multi-select without one query per row (issue #26,
     *         "read-only hint next to a linked asset").
     */
    public static function getByItemtype(string $itemtype): array
    {
        global $DB;

        $rows = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['itemtype' => $itemtype]]) as $row) {
            $rows[(int) $row['items_id']] = $row;
        }

        return $rows;
    }

    /**
     * Issue #26, reverse view: read-only summary + edit form on every linkable itemtype's own page
     * (see setup.php, Plugin::registerClass()/addtabon), same mechanism as issue #25's own
     * "Risques" tab (PluginGrcmanagerRisk::getTabNameForItem()). Guarded to only fire for a
     * genuinely linkable $item, same reasoning as PluginGrcmanagerRisk's own guard.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!in_array($item->getType(), PluginGrcmanagerRisk::getLinkableItemtypes(), true)) {
            return '';
        }

        if (!Session::haveRight(self::$rightname, READ)) {
            return '';
        }

        return self::createTabEntry(self::getTypeName(1));
    }

    /**
     * Read-only summary of the asset's current C/I/D classification (or an explicit empty state if
     * it has never been classified), followed by a plain 3-dropdown edit form — same "HTML/PHP
     * manual, no JS-heavy widget" convention as every other form in this plugin (see TECH_DEBT.md).
     * Posts to front/assetclassification.form.php (itemtype/items_id carried as hidden fields,
     * this class is polymorphic-keyed, not a single-ID form like every other itemtype in this
     * plugin), which resolves add-vs-update itself via getByItem() rather than requiring the
     * caller to know the row's own numeric id.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;

        $itemtype = $item->getType();
        $itemsId  = (int) $item->getID();

        $classification = self::getByItem($itemtype, $itemsId);

        echo '<div class="p-2">';

        if (ClassificationLevels::isClassified($classification)) {
            $summary = __(
                'Classification actuelle : Confidentialité %1$s / Intégrité %2$s / Disponibilité %3$s',
                'grcmanager'
            );
            echo '<p>' . sprintf(
                $summary,
                self::levelBadge($classification['confidentiality'] ?? null),
                self::levelBadge($classification['integrity'] ?? null),
                self::levelBadge($classification['availability'] ?? null)
            ) . '</p>';
        } else {
            echo '<p class="text-muted">'
                . __('Cet actif n\'a pas encore été classifié (confidentialité/intégrité/disponibilité).', 'grcmanager')
                . '</p>';
        }

        // Lecture seule au-delà de ce point si l'utilisateur n'a pas le droit de modifier : montrer
        // le formulaire sans pouvoir l'enregistrer serait trompeur (le POST échouerait de toute
        // façon côté serveur, voir front/assetclassification.form.php, mais autant ne pas l'afficher).
        if (!Session::haveRight(self::$rightname, UPDATE)) {
            echo '</div>';
            return true;
        }

        $formUrl = $CFG_GLPI['root_doc'] . '/plugins/grcmanager/front/assetclassification.form.php';
        echo '<form method="post" action="' . htmlescape($formUrl) . '">';
        echo Html::hidden('itemtype', ['value' => $itemtype]);
        echo Html::hidden('items_id', ['value' => $itemsId]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo '<table class="table">';

        echo '<tr><td>' . __('Confidentialité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('confidentiality', self::getLevels(), [
            'value'               => $classification['confidentiality'] ?? '',
            'display_emptychoice' => true,
        ]);
        echo '</td></tr>';

        echo '<tr><td>' . __('Intégrité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('integrity', self::getLevels(), [
            'value'               => $classification['integrity'] ?? '',
            'display_emptychoice' => true,
        ]);
        echo '</td></tr>';

        echo '<tr><td>' . __('Disponibilité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('availability', self::getLevels(), [
            'value'               => $classification['availability'] ?? '',
            'display_emptychoice' => true,
        ]);
        echo '</td></tr>';

        echo '</table>';

        echo '<div class="mt-2">';
        echo Html::submit(__('Enregistrer'), ['name' => 'save']);
        echo '</div>';
        echo '</form>';
        echo '</div>';

        return true;
    }
}
