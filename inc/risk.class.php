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
use GlpiPlugin\Grcmanager\Services\Risk\LinkableItemtypes;
use GlpiPlugin\Grcmanager\Services\Risk\ReviewReminderService;
use GlpiPlugin\Grcmanager\Services\Risk\RiskItemLinkNormalizer;
use GlpiPlugin\Grcmanager\Traits\RiskAssessmentTrait;

/**
 * Generic organizational risk register (ISO 27001 clause 6.1.2/8.2), see issue #89 on the sibling
 * repository glpi-vulnerability-manager for the full scope this plugin covers. Deliberately
 * broader than that sibling plugin's own CVE-specific risk model: a row here can describe a
 * people, process, physical, third-party or technical risk, not just a scored vulnerability.
 *
 * The probability x impact scoring, treatment/status enums and their badge rendering are shared
 * with the Sprint 5 supplier/third-party risk register (PluginGrcmanagerSupplierRisk) via
 * RiskAssessmentTrait, see its own docblock: one implementation, never two that could drift apart.
 */
class PluginGrcmanagerRisk extends CommonDBTM
{
    use RiskAssessmentTrait;

    public static $rightname = 'plugin_grcmanager';

    /**
     * GLPI notification event name (see inc/notificationtargetrisk.class.php and
     * src/Services/Risk/ReviewReminderService.php), shared here as a single source of truth so
     * the event string never drifts between the NotificationTarget, the reminder service, and the
     * Cron entry point below.
     */
    public const REVIEW_DUE_EVENT = 'review_due';

    /**
     * Issue #25 (lien registre de risques <-> actifs GLPI/CMDB) : table de liaison polymorphe
     * (itemtype/items_id), voir src/Install/Installer.php pour le schéma et son commentaire, et
     * getLinkedAssets()/syncLinkedAssets()/getRisksLinkedToItem() ci-dessous. Gérée en accès direct
     * $DB par de simples méthodes statiques, même simplification assumée depuis le Sprint 3 pour
     * les autres liens de ce plugin (glpi_plugin_grcmanager_controls_risks, etc.), voir
     * TECH_DEBT.md : pas une vraie classe CommonDBRelation, un nombre d'actifs liés par risque
     * toujours faible en pratique.
     */
    private const ITEMS_LINK_TABLE = 'glpi_plugin_grcmanager_risks_items';

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
     * without it, see TECH_DEBT.md there. Applied here from the start. Every column here is shared
     * with the Sprint 5 supplier risk register, see RiskAssessmentTrait::commonValueToDisplay().
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $common = self::commonValueToDisplay($field, $values[$field] ?? null);
        if ($common !== null) {
            return $common;
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Renders a real `<select>` filter widget in the search form for every fixed-enum column
     * (category/probability/impact/risk_level/treatment/status), instead of GLPI's default
     * free-text box for `datatype => 'specific'` fields (confirmed by reading GLPI 11 core,
     * src/Glpi/Search/Input/QueryBuilder.php: 'specific' falls through to the same generic
     * text-input pattern as 'string' unless this hook is overridden). Sprint 1 shipped translated,
     * color-coded values in the *list*, but left every one of these columns filterable only by
     * typing the raw untranslated DB key (e.g. "third_party") into a text box, genuinely
     * filterable in the sense that GLPI's search does apply it, but not self-explanatory for a
     * non-technical user, and not what Sprint 2 asks for. Sorting was never affected (GLPI sorts
     * by the raw SQL column for any datatype), only filtering needed this. Every column here is
     * shared with the Sprint 5 supplier risk register, see RiskAssessmentTrait::commonValueToSelect().
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $options['display'] = false;
        $options['name']    = $name;
        $options['value']   = $values[$field] ?? '';

        $common = self::commonValueToSelect($field, $name, $options);
        if ($common !== null) {
            return $common;
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
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

        // Issue #26 : suggestion non-bloquante uniquement, ne modifie jamais la valeur choisie par
        // l'utilisateur (voir ClassificationLevels::hasHighAxis()) - un simple signal visuel quand
        // au moins un actif déjà lié porte une classification C/I/D élevée, pour orienter la
        // décision sans jamais la prendre à sa place. Reste dans la structure existante de
        // showForm() (pas de logique JS conditionnelle, cohérent avec TECH_DEBT.md).
        if (!$this->isNewID($ID) && self::hasHighClassificationAmongLinkedAssets((int) $ID)) {
            $hint = __(
                'Un actif lié porte une classification C/I/D élevée : vérifiez l\'impact de ce risque.',
                'grcmanager'
            );
            echo '<div class="form-hint text-warning mt-1">'
                . '<i class="ti ti-alert-triangle me-1"></i>' . $hint
                . '</div>';
        }

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

        // Issue #25 (lien registre de risques <-> actifs GLPI/CMDB) : un multi-select PAR itemtype
        // liable (jamais un unique widget polymorphe façon Dropdown::showSelectItemFromItemtypes(),
        // qui exige de la JS conditionnelle absente de ce formulaire, voir TECH_DEBT.md "showForm()
        // en HTML/PHP manuel") — même widget Dropdown::showFromArray(..., ['multiple' => true]) que
        // PluginGrcmanagerControl::showForm() pour son propre lien vers les risques, un par type
        // pour couvrir plusieurs itemtypes cibles à la fois. Un type sans aucun enregistrement dans
        // cette instance GLPI n'affiche aucune ligne, pour ne pas encombrer le formulaire avec des
        // listes vides.
        echo '<tr class="tab_bg_1"><td colspan="4"><strong>'
            . __('Actifs liés (CMDB)', 'grcmanager') . '</strong></td></tr>';

        $linkedIdsByType = $this->isNewID($ID) ? [] : self::getLinkedAssetIdsByType((int) $ID);

        foreach (self::getLinkableItemtypes() as $itemtype) {
            if (!is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            // Issue #26 : annote chaque option avec sa classification C/I/D existante quand elle en
            // a une (ex. "web-server-01 (C: Élevé, I: Élevé, D: Moyen)"), en lecture seule - l'édition
            // reste exclusivement sur l'onglet "Classification C/I/D" propre à l'actif
            // (PluginGrcmanagerAssetClassification), jamais dupliquée ici. Une seule requête par
            // itemtype (getByItemtype()), pas une par actif, pour ne pas alourdir une liste déjà non
            // paginée (voir TECH_DEBT.md, limitation assumée depuis l'issue #25).
            $classificationsByItemsId = PluginGrcmanagerAssetClassification::getByItemtype($itemtype);

            $options = [];
            foreach ($DB->request(['FROM' => $itemtype::getTable(), 'ORDER' => 'name']) as $row) {
                $itemsId = (int) $row['id'];
                $label   = $row['name'] !== '' ? $row['name'] : sprintf('#%d', $itemsId);

                $classification = $classificationsByItemsId[$itemsId] ?? null;
                if (ClassificationLevels::isClassified($classification)) {
                    $label .= ' ' . sprintf(
                        __('(C : %1$s, I : %2$s, D : %3$s)', 'grcmanager'),
                        self::classificationLevelLabel($classification['confidentiality'] ?? null),
                        self::classificationLevelLabel($classification['integrity'] ?? null),
                        self::classificationLevelLabel($classification['availability'] ?? null)
                    );
                }

                $options[$itemsId] = $label;
            }

            if ($options === []) {
                continue;
            }

            echo '<tr class="tab_bg_1"><td>' . htmlescape($itemtype::getTypeName(2)) . '</td>';
            echo '<td colspan="3">';
            Dropdown::showFromArray(self::linkedAssetFieldName($itemtype), $options, [
                'values'   => $linkedIdsByType[$itemtype] ?? [],
                'multiple' => true,
                'width'    => '100%',
            ]);
            echo '</td></tr>';
        }

        $this->showFormButtons($options);

        return true;
    }

    /**
     * @return array<int, string> itemtype class names a risk may be linked to. The fixed default
     *         list (LinkableItemtypes::DEFAULT_ITEMTYPES) plus any active GLPI custom asset
     *         definition, enumerated dynamically at each call so an asset type created or
     *         activated after this plugin's install is usable without reinstalling it — same
     *         dynamic-discovery approach and same `glpi_assets_assetdefinitions` query as the
     *         sibling plugin assetsign-glpi's own Config::getAllManageableItemtypes().
     */
    public static function getLinkableItemtypes(): array
    {
        global $DB;

        $itemtypes = LinkableItemtypes::DEFAULT_ITEMTYPES;

        if ($DB->tableExists('glpi_assets_assetdefinitions')) {
            foreach ($DB->request(['FROM' => 'glpi_assets_assetdefinitions', 'WHERE' => ['is_active' => 1]]) as $row) {
                $itemtypes[] = 'Glpi\\CustomAsset\\' . $row['system_name'] . 'Asset';
            }
        }

        return $itemtypes;
    }

    /**
     * @return array<int, array{itemtype:string, items_id:int, name:string, url:string}>
     */
    public static function getLinkedAssets(int $riskId): array
    {
        global $DB;

        $items = [];

        $rows = $DB->request([
            'FROM'  => self::ITEMS_LINK_TABLE,
            'WHERE' => ['plugin_grcmanager_risks_id' => $riskId],
        ]);

        foreach ($rows as $row) {
            $itemtype = (string) $row['itemtype'];
            $itemsId  = (int) $row['items_id'];

            if (!is_a($itemtype, CommonDBTM::class, true)) {
                // Classe disparue depuis la création du lien (ex. désinstallation du plugin qui
                // fournissait un actif personnalisé) : ignorée plutôt que de faire échouer tout
                // l'affichage du formulaire pour un seul lien orphelin.
                continue;
            }

            $item = new $itemtype();
            $name = $item->getFromDB($itemsId)
                ? $item->getName()
                : sprintf(__('%1$s #%2$d (supprimé)', 'grcmanager'), $itemtype::getTypeName(1), $itemsId);

            $items[] = [
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
                'name'     => $name,
                'url'      => $item->getFormURLWithID($itemsId),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array<int, int>> itemtype => linked item ids, to pre-select each
     *         per-itemtype multi-select in showForm() above.
     */
    public static function getLinkedAssetIdsByType(int $riskId): array
    {
        $byType = [];
        foreach (self::getLinkedAssets($riskId) as $item) {
            $byType[$item['itemtype']][] = $item['items_id'];
        }

        return $byType;
    }

    /**
     * Issue #26 : soft, non-blocking suggestion backing the hint shown next to the Impact field in
     * showForm() above - true as soon as ONE of this risk's currently linked CMDB assets carries a
     * HIGH classification on any of its three C/I/D axes (pure decision in
     * ClassificationLevels::hasHighAxis(), unit tested there). Never used to change `impact`
     * itself, only to decide whether to print the hint.
     */
    private static function hasHighClassificationAmongLinkedAssets(int $riskId): bool
    {
        foreach (self::getLinkedAssets($riskId) as $asset) {
            $classification = PluginGrcmanagerAssetClassification::getByItem($asset['itemtype'], $asset['items_id']);
            if (ClassificationLevels::hasHighAxis($classification)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translated label for one raw C/I/D level value, used only for the inline read-only hint next
     * to a linked asset's multi-select option above - the color-coded badge
     * (PluginGrcmanagerAssetClassification::levelBadge()) is HTML and cannot be embedded in a plain
     * `<option>` label text, hence this plain-text equivalent kept in sync with the same three
     * levels (ClassificationLevels::ALLOWED).
     */
    private static function classificationLevelLabel(?string $value): string
    {
        return PluginGrcmanagerAssetClassification::getLevels()[$value] ?? __('Non classifié', 'grcmanager');
    }

    /**
     * Reverse lookup (issue #25: "inversement partir d'un risque pour voir les actifs concernés",
     * and its mirror direction): every risk currently linked to one specific CMDB item, for the
     * read-only "Risques" tab this plugin adds on each linkable itemtype (see setup.php,
     * getTabNameForItem()/displayTabContentForItem() below).
     *
     * @return array<int, array{id:int, title:string, risk_level:?string}>
     */
    public static function getRisksLinkedToItem(string $itemtype, int $itemsId): array
    {
        global $DB;

        // Filtre SQL uniquement sur itemtype (indexé, voir la clé `item` du schéma) : le nombre de
        // liens pour un seul type reste faible en pratique, le filtre exact sur items_id est fait
        // ensuite en PHP par RiskItemLinkNormalizer::findRiskIdsForItem(), la même logique que
        // celle couverte par les tests unitaires (voir
        // tests/Unit/Services/Risk/RiskItemLinkNormalizerTest.php), pour ne jamais diverger entre
        // le code réellement exécuté et celui testé.
        $links = iterator_to_array($DB->request([
            'FROM'  => self::ITEMS_LINK_TABLE,
            'WHERE' => ['itemtype' => $itemtype],
        ]));

        $riskIds = RiskItemLinkNormalizer::findRiskIdsForItem($links, $itemtype, $itemsId);
        if ($riskIds === []) {
            return [];
        }

        $risks = [];

        $rows = $DB->request([
            'SELECT' => ['id', 'title', 'risk_level'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['id' => $riskIds],
        ]);

        foreach ($rows as $row) {
            $risks[] = [
                'id'         => (int) $row['id'],
                'title'      => $row['title'],
                'risk_level' => $row['risk_level'],
            ];
        }

        return $risks;
    }

    /**
     * Replaces the full set of CMDB items linked to this risk with exactly $pairs (delete then
     * re-insert), same "supprimer-puis-réinsérer" convention already used by every other simple
     * link table in this plugin (PluginGrcmanagerControl::syncLinkedRisks(),
     * PluginGrcmanagerAudit::syncLinkedControls()...), see TECH_DEBT.md. An empty $pairs array is
     * the expected, non-error baseline for a purely organizational risk with no CMDB counterpart
     * (issue #25, "processus de recrutement").
     *
     * @param array<int, array{itemtype:string, items_id:int}> $pairs
     */
    private static function syncLinkedAssets(int $riskId, array $pairs): void
    {
        global $DB;

        $DB->delete(self::ITEMS_LINK_TABLE, ['plugin_grcmanager_risks_id' => $riskId]);

        $allowed = self::getLinkableItemtypes();

        foreach ($pairs as $pair) {
            $itemtype = (string) ($pair['itemtype'] ?? '');
            $itemsId  = (int) ($pair['items_id'] ?? 0);

            if (!RiskItemLinkNormalizer::isLinkable($itemtype, $itemsId, $allowed)) {
                continue;
            }

            $DB->insert(self::ITEMS_LINK_TABLE, [
                'plugin_grcmanager_risks_id' => $riskId,
                'itemtype'                   => $itemtype,
                'items_id'                   => $itemsId,
                'date_creation'              => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Rebuilds the candidate itemtype/items_id pairs from the per-itemtype multi-selects rendered
     * in showForm() above, then delegates to syncLinkedAssets(). Does nothing if NONE of the
     * `linked_items_<Itemtype>` fields are present in the submitted input: an HTML multi-select
     * with zero selections submits no field at all, indistinguishable from "this form section
     * wasn't rendered" (e.g. a future programmatic/API caller that doesn't know about this block) —
     * only ever wipes existing links when the real form was genuinely submitted.
     */
    private function syncLinkedAssetsFromInput(): void
    {
        $itemtypes = self::getLinkableItemtypes();

        $formWasSubmitted = false;
        foreach ($itemtypes as $itemtype) {
            if (array_key_exists(self::linkedAssetFieldName($itemtype), $this->input)) {
                $formWasSubmitted = true;
                break;
            }
        }

        if (!$formWasSubmitted) {
            return;
        }

        $pairs = [];
        foreach ($itemtypes as $itemtype) {
            foreach ((array) ($this->input[self::linkedAssetFieldName($itemtype)] ?? []) as $itemsId) {
                $pairs[] = ['itemtype' => $itemtype, 'items_id' => (int) $itemsId];
            }
        }

        self::syncLinkedAssets((int) $this->fields['id'], $pairs);
    }

    private static function linkedAssetFieldName(string $itemtype): string
    {
        return 'linked_items_' . str_replace('\\', '_', $itemtype);
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->syncLinkedAssetsFromInput();
    }

    public function post_updateItem($history = true)
    {
        parent::post_updateItem($history);
        $this->syncLinkedAssetsFromInput();
    }

    /**
     * Purges this risk's own rows in the polymorphic link table when the risk itself is purged, so
     * a deleted risk never leaves orphaned `glpi_plugin_grcmanager_risks_items` rows behind. Not
     * strictly forced by a real GLPI foreign key (same reason as every other simple link table in
     * this plugin, see TECH_DEBT.md), but cheap and worth doing here since this table can grow
     * unboundedly with real CMDB items, unlike this plugin's small fixed-catalog link tables
     * (controls_risks, audits_controls).
     */
    public function post_purgeItem()
    {
        parent::post_purgeItem();

        global $DB;
        $DB->delete(self::ITEMS_LINK_TABLE, ['plugin_grcmanager_risks_id' => $this->fields['id']]);
    }

    /**
     * Issue #25, reverse view: read-only "Risques" tab on every linkable itemtype's own page (see
     * setup.php, Plugin::registerClass()/addtabon). Guarded to only fire for a genuinely linkable
     * $item (this method is invoked by GLPI core for every plugin class registered via addtabon,
     * regardless of which item is currently displayed) and behind the plugin's own read right,
     * same right as every other screen of this plugin.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!in_array($item->getType(), self::getLinkableItemtypes(), true)) {
            return '';
        }

        if (!Session::haveRight(self::$rightname, READ)) {
            return '';
        }

        $count = count(self::getRisksLinkedToItem($item->getType(), (int) $item->getID()));

        return self::createTabEntry(self::getTypeName(2), $count);
    }

    /**
     * Minimal read-only list (title + risk level badge, linked to the risk's own form): this
     * plugin registers no other reverse tab anywhere else (confirmed by inspecting every other
     * inc/*.class.php file), so there is no richer existing convention to match here, and the
     * "many risks per asset" cardinality this issue describes (issue #25, "panne électrique du
     * site") stays small enough that a full itemtype/list-search screen would be over-engineering
     * for what this issue actually asks for.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $risks = self::getRisksLinkedToItem($item->getType(), (int) $item->getID());

        if ($risks === []) {
            echo '<p class="p-2 text-muted">' . __('Aucun risque lié à cet actif.', 'grcmanager') . '</p>';
            return true;
        }

        $riskItem = new self();

        echo '<table class="table table-striped">';
        echo '<thead><tr><th>' . __('Titre', 'grcmanager') . '</th>'
            . '<th>' . __('Niveau de risque', 'grcmanager') . '</th></tr></thead><tbody>';

        foreach ($risks as $risk) {
            echo '<tr><td><a href="' . htmlescape($riskItem->getFormURLWithID($risk['id'])) . '">'
                . htmlescape($risk['title']) . '</a></td>';
            echo '<td>' . self::riskLevelBadge($risk['risk_level']) . '</td></tr>';
        }

        echo '</tbody></table>';

        return true;
    }

    /**
     * GLPI Cron entry point, registered via CronTask::Register() in the plugin installer
     * (src/Install/Installer.php), same structure as the sibling plugin
     * glpi-vulnerability-manager's own cronSynchronize() entry points. Finds risks whose review
     * date has passed or is within the reminder window and raises a real GLPI Notification for
     * each (see ReviewReminderService, inc/notificationtargetrisk.class.php); the dashboard card
     * `grcmanager_risks_pending_review` (Sprint 1) already gives a permanent in-app signal
     * regardless of whether GLPI notifications are enabled; this cron adds the active, per-risk
     * one (email to the risk owner, when notifications are configured).
     *
     * @return int 0 if no risk was due, 1 otherwise
     */
    public static function cronReviewreminder(CronTask $task): int
    {
        $result = (new ReviewReminderService(self::class))->notify();

        $task->addVolume($result->getNotified());
        $task->log(sprintf(
            '%d risque(s) en attente de revue, %d notification(s) déclenchée(s).',
            $result->getDue(),
            $result->getNotified()
        ));

        return $result->getDue() > 0 ? 1 : 0;
    }
}
