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

use GlpiPlugin\Grcmanager\Services\Risk\ReviewReminderService;
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
