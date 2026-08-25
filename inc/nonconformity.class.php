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

use GlpiPlugin\Grcmanager\Services\Capa\OverdueCapaService;

/**
 * A non-conformity (finding) raised during an internal audit (PluginGrcmanagerAudit), carrying the
 * full corrective/preventive action (CAPA) workflow ISO 27001 clause 10.2 requires: root cause,
 * corrective action, preventive action, a responsible owner, a due date, and a closure verification
 * date once the action is confirmed effective.
 *
 * `severity` doubles as this class' "severity/category" axis (minor/major/critical): a real GRC
 * tool typically distinguishes a full non-conformity from a lesser "observation", but a single
 * ordinal scale was kept here for a first version rather than two overlapping enums, see
 * TECH_DEBT.md Sprint 4.
 */
class PluginGrcmanagerNonconformity extends CommonDBTM
{
    public static $rightname = 'plugin_grcmanager';

    /**
     * GLPI notification event name (see inc/notificationtargetnonconformity.class.php and
     * src/Services/Capa/OverdueCapaService.php), same single-source-of-truth pattern as
     * PluginGrcmanagerRisk::REVIEW_DUE_EVENT.
     */
    public const CAPA_OVERDUE_EVENT = 'capa_overdue';

    /**
     * A non-conformity counts as "closed" (no longer overdue-eligible) once it reaches either of
     * these statuses, shared here so the dashboard card, the overdue Cron and the form validation
     * below never drift apart on the definition.
     *
     * @var array<int, string>
     */
    public const CLOSED_STATUSES = ['closed', 'verified'];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_grcmanager_nonconformities';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Non-conformité', 'Non-conformités', $nb, 'grcmanager');
    }

    public static function getIcon()
    {
        return 'ti ti-alert-hexagon';
    }

    /**
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
            'open'        => __('Ouverte', 'grcmanager'),
            'in_progress' => __('En traitement', 'grcmanager'),
            'closed'      => __('Clôturée', 'grcmanager'),
            'verified'    => __('Vérifiée', 'grcmanager'),
        ];
    }

    /**
     * Enforces ISO 27001 clause 10.2 in practice: a non-conformity cannot be marked closed or
     * verified without a documented corrective action, and reaching "verified" without an explicit
     * closure verification date auto-stamps it with today rather than silently leaving it blank
     * (same "auto-populate a real date rather than force an extra manual step" choice as
     * PluginGrcmanagerAudit's own `actual_date`).
     *
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
        $status            = (string) ($input['status'] ?? ($this->fields['status'] ?? 'open'));
        $correctiveAction  = trim((string) (
            $input['corrective_action'] ?? ($this->fields['corrective_action'] ?? '')
        ));

        if (in_array($status, self::CLOSED_STATUSES, true) && $correctiveAction === '') {
            Session::addMessageAfterRedirect(
                __(
                    'Une action corrective est obligatoire pour clôturer ou vérifier une non-conformité.',
                    'grcmanager'
                ),
                false,
                ERROR
            );

            return false;
        }

        $verificationDate = $input['closure_verification_date']
            ?? ($this->fields['closure_verification_date'] ?? null);

        if ($status === 'verified' && empty($verificationDate)) {
            $input['closure_verification_date'] = date('Y-m-d');
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
            'field'    => 'severity',
            'name'     => __('Sévérité', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'status',
            'name'     => __('Statut', 'grcmanager'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Responsable', 'grcmanager'),
            'datatype' => 'dropdown',
            'right'    => 'all',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'due_date',
            'name'     => __('Échéance', 'grcmanager'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => $this->getTable(),
            'field'    => 'closure_verification_date',
            'name'     => __('Date de vérification de clôture', 'grcmanager'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'plugin_grcmanager_audits_id',
            'name'     => PluginGrcmanagerAudit::getTypeName(1),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => $this->getTable(),
            'field'    => 'description',
            'name'     => __('Description', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => $this->getTable(),
            'field'    => 'root_cause',
            'name'     => __('Cause racine', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 10,
            'table'    => $this->getTable(),
            'field'    => 'corrective_action',
            'name'     => __('Action corrective', 'grcmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => 11,
            'table'    => $this->getTable(),
            'field'    => 'preventive_action',
            'name'     => __('Action préventive', 'grcmanager'),
            'datatype' => 'text',
        ];

        // Row link, same "a list with no way back to showForm() is not self-explanatory" lesson
        // already applied throughout this plugin family.
        $tab[] = [
            'id'       => 12,
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
            case 'severity':
                return self::severityBadge($values[$field] ?? null);

            case 'status':
                return self::statusBadge($values[$field] ?? null);

            case 'plugin_grcmanager_audits_id':
                return self::auditLink((int) ($values[$field] ?? 0));
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Real `<select>` filter widgets for severity/status, same lesson as
     * PluginGrcmanagerRisk::getSpecificValueToSelect(). `plugin_grcmanager_audits_id` is left to
     * the parent's default (free-text search on the raw numeric ID): looking it up through a real
     * GLPI dropdown join was judged not worth the added complexity for a first version, see
     * TECH_DEBT.md Sprint 4.
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
            case 'severity':
                return Dropdown::showFromArray($name, self::getSeverities(), $options);

            case 'status':
                return Dropdown::showFromArray($name, self::getStatuses(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
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
            'open'        => ['bg-red-lt', 'ti-flag', __('Ouverte', 'grcmanager')],
            'in_progress' => ['bg-blue-lt', 'ti-tool', __('En traitement', 'grcmanager')],
            'closed'      => ['bg-dark-lt', 'ti-check', __('Clôturée', 'grcmanager')],
            'verified'    => ['bg-green-lt', 'ti-shield-check', __('Vérifiée', 'grcmanager')],
        ];

        [$class, $icon, $label] = $map[$value] ?? ['bg-secondary-lt', 'ti-help', (string) $value];

        return '<span class="badge ' . $class . '"><i class="ti ' . $icon . ' me-1"></i>'
            . htmlescape($label) . '</span>';
    }

    private static function auditLink(int $auditId): string
    {
        if ($auditId <= 0) {
            return '';
        }

        $title = self::getAuditTitle($auditId);

        if ($title === '') {
            return '';
        }

        return '<a href="' . htmlescape(PluginGrcmanagerAudit::getFormURLWithID($auditId)) . '">'
            . htmlescape($title) . '</a>';
    }

    /**
     * Direct-lookup helper, same pattern as PluginGrcmanagerControl::getLinkedRisks() (a real
     * CommonDBTM::getFromDB() would work too, but this avoids instantiating a whole object just to
     * read one column).
     */
    public static function getAuditTitle(int $auditId): string
    {
        global $DB;

        $rows = $DB->request([
            'SELECT' => ['title'],
            'FROM'   => PluginGrcmanagerAudit::getTable(),
            'WHERE'  => ['id' => $auditId],
        ]);

        foreach ($rows as $row) {
            return (string) $row['title'];
        }

        return '';
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

        $auditTitles = [];
        foreach ($DB->request(['SELECT' => ['id', 'title'], 'FROM' => PluginGrcmanagerAudit::getTable()]) as $row) {
            $auditTitles[(int) $row['id']] = $row['title'];
        }

        echo '<tr class="tab_bg_1"><td>' . PluginGrcmanagerAudit::getTypeName(1) . '</td><td>';
        Dropdown::showFromArray('plugin_grcmanager_audits_id', $auditTitles, [
            'value' => $this->fields['plugin_grcmanager_audits_id'] ?? 0,
        ]);
        echo '</td>';

        echo '<td>' . __('Sévérité', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('severity', self::getSeverities(), [
            'value' => $this->fields['severity'] ?? 'minor',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Responsable', 'grcmanager') . '</td><td>';
        User::dropdown([
            'name'  => 'users_id',
            'value' => $this->fields['users_id'] ?? 0,
            'right' => 'all',
        ]);
        echo '</td>';

        echo '<td>' . __('Statut', 'grcmanager') . '</td><td>';
        Dropdown::showFromArray('status', self::getStatuses(), [
            'value' => $this->fields['status'] ?? 'open',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Échéance', 'grcmanager') . '</td><td>';
        Html::showDateField('due_date', ['value' => $this->fields['due_date'] ?? '']);
        echo '</td>';

        echo '<td>' . __('Date de vérification de clôture', 'grcmanager') . '</td><td>';
        Html::showDateField('closure_verification_date', [
            'value' => $this->fields['closure_verification_date'] ?? '',
        ]);
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Description', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="description" class="form-control" rows="3">'
            . htmlescape($this->fields['description'] ?? '') . '</textarea>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Cause racine', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="root_cause" class="form-control" rows="3">'
            . htmlescape($this->fields['root_cause'] ?? '') . '</textarea>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Action corrective', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="corrective_action" class="form-control" rows="3">'
            . htmlescape($this->fields['corrective_action'] ?? '') . '</textarea>';
        echo '<small class="form-hint">' . __(
            'Obligatoire pour clôturer ou vérifier cette non-conformité.',
            'grcmanager'
        ) . '</small>';
        echo '</td></tr>';

        echo '<tr class="tab_bg_1"><td>' . __('Action préventive', 'grcmanager') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="preventive_action" class="form-control" rows="3">'
            . htmlescape($this->fields['preventive_action'] ?? '') . '</textarea>';
        echo '</td></tr>';

        $this->showFormButtons($options);

        return true;
    }

    /**
     * GLPI Cron entry point, same structure as PluginGrcmanagerRisk::cronReviewreminder().
     * Notifies the responsible owner of every non-conformity whose due date has passed and that
     * is not yet closed/verified (see OverdueCapaService, inc/notificationtargetnonconformity.class.php).
     *
     * @return int 0 if no CAPA was overdue, 1 otherwise
     */
    public static function cronOverduecapa(CronTask $task): int
    {
        $result = (new OverdueCapaService())->notify();

        $task->addVolume($result->getNotified());
        $task->log(sprintf(
            '%d action(s) corrective(s)/préventive(s) en retard, %d notification(s) déclenchée(s).',
            $result->getDue(),
            $result->getNotified()
        ));

        return $result->getDue() > 0 ? 1 : 0;
    }
}
