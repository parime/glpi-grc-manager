<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Services\Dashboard;

use Glpi\DBAL\QueryExpression;

/**
 * Data providers for the plugin's `Hooks::DASHBOARD_CARDS` entries (registered in
 * setup.php/hook.php). Each public static method matches the calling convention GLPI's
 * `Glpi\Dashboard\Grid` uses for a card's `provider` callable, one `array $params` argument,
 * returning the shape the chosen native widget (`bigNumber`/`multipleNumber`/`pie`) expects.
 *
 * NOTE: depends on GLPI's runtime global $DB, not unit-tested in isolation, same exclusion
 * rationale as the sibling plugin glpi-vulnerability-manager, see phpstan.neon.dist.
 */
final class DashboardCardService
{
    public static function openRisksCount(array $params = []): array
    {
        global $DB;

        $count = (int) $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_plugin_grcmanager_risks',
            'WHERE' => ['status' => ['identified', 'in_treatment']],
        ])->current()['c'];

        return [
            'number' => $count,
            'label' => $params['label'] ?? __('Risques ouverts', 'grcmanager'),
            'icon' => 'ti ti-shield-exclamation',
        ];
    }

    public static function risksByLevel(array $params = []): array
    {
        global $DB;

        $countsByLevel = array_fill_keys(['low', 'medium', 'high', 'critical'], 0);

        $rows = $DB->request([
            'SELECT' => ['risk_level', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_risks',
            'GROUPBY' => 'risk_level',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByLevel[$row['risk_level']])) {
                $countsByLevel[$row['risk_level']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByLevel as $level => $count) {
            $data[] = ['label' => $level, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Risques par niveau', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    public static function risksByCategory(array $params = []): array
    {
        global $DB;

        $countsByCategory = array_fill_keys(
            ['people', 'process', 'physical', 'third_party', 'technical'],
            0
        );

        $rows = $DB->request([
            'SELECT' => ['category', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_risks',
            'GROUPBY' => 'category',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByCategory[$row['category']])) {
                $countsByCategory[$row['category']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByCategory as $category => $count) {
            $data[] = ['label' => $category, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Risques par catégorie', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    public static function risksPendingReviewCount(array $params = []): array
    {
        global $DB;

        $count = (int) $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_plugin_grcmanager_risks',
            'WHERE' => [
                'status' => ['<>', 'closed'],
                new QueryExpression('(review_date IS NULL OR review_date <= CURDATE())'),
            ],
        ])->current()['c'];

        return [
            'number' => $count,
            'label' => $params['label'] ?? __('Risques en attente de revue', 'grcmanager'),
            'icon' => 'ti ti-calendar-due',
        ];
    }

    /**
     * SoA completion progress (Sprint 3, clause 6.1.3): "X/93 contrôles revus", a control counts
     * as reviewed as soon as it has been explicitly saved once through the form (see
     * PluginGrcmanagerControl::validateAndMarkReviewed()), regardless of the applicability chosen.
     */
    public static function soaReviewedCount(array $params = []): array
    {
        global $DB;

        $total = (int) $DB->request([
            'COUNT' => 'c',
            'FROM'  => 'glpi_plugin_grcmanager_controls',
        ])->current()['c'];

        $reviewed = (int) $DB->request([
            'COUNT' => 'c',
            'FROM'  => 'glpi_plugin_grcmanager_controls',
            'WHERE' => ['is_reviewed' => 1],
        ])->current()['c'];

        return [
            'number' => $reviewed,
            'label' => $params['label'] ?? sprintf(
                __('Contrôles SoA revus (%d au total)', 'grcmanager'),
                $total
            ),
            'icon' => 'ti ti-checklist',
        ];
    }

    public static function soaByApplicability(array $params = []): array
    {
        global $DB;

        $countsByApplicability = array_fill_keys(['yes', 'no', 'partial'], 0);

        $rows = $DB->request([
            'SELECT' => ['applicability', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_controls',
            'GROUPBY' => 'applicability',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByApplicability[$row['applicability']])) {
                $countsByApplicability[$row['applicability']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByApplicability as $applicability => $count) {
            $data[] = ['label' => $applicability, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Contrôles SoA par applicabilité', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    public static function soaByImplementationStatus(array $params = []): array
    {
        global $DB;

        $countsByStatus = array_fill_keys(
            ['not_started', 'in_progress', 'implemented', 'verified'],
            0
        );

        $rows = $DB->request([
            'SELECT' => ['implementation_status', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_controls',
            'GROUPBY' => 'implementation_status',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByStatus[$row['implementation_status']])) {
                $countsByStatus[$row['implementation_status']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByStatus as $status => $count) {
            $data[] = ['label' => $status, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Contrôles SoA par état de mise en œuvre', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    /**
     * Sprint 4 (audits internes et CAPA, clause 10.2) : non-conformités encore ouvertes ou en
     * traitement, quel que soit l'audit d'origine.
     */
    public static function openNonconformitiesCount(array $params = []): array
    {
        global $DB;

        $count = (int) $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_plugin_grcmanager_nonconformities',
            'WHERE' => ['status' => ['open', 'in_progress']],
        ])->current()['c'];

        return [
            'number' => $count,
            'label' => $params['label'] ?? __('Non-conformités ouvertes', 'grcmanager'),
            'icon' => 'ti ti-alert-hexagon',
        ];
    }

    /**
     * Actions correctives/préventives en retard : même définition que
     * GlpiPlugin\Grcmanager\Services\Capa\OverdueCapaService (échéance dépassée, statut ni
     * clôturée ni vérifiée), pour que la carte de tableau de bord et la tâche Cron ne divergent
     * jamais sur ce qui compte comme "en retard".
     */
    public static function overdueCapaCount(array $params = []): array
    {
        global $DB;

        $count = (int) $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_plugin_grcmanager_nonconformities',
            'WHERE' => [
                'status' => ['NOT IN', ['closed', 'verified']],
                new QueryExpression('due_date IS NOT NULL'),
                new QueryExpression('due_date < CURDATE()'),
            ],
        ])->current()['c'];

        return [
            'number' => $count,
            'label' => $params['label'] ?? __('Actions correctives/préventives en retard', 'grcmanager'),
            'icon' => 'ti ti-calendar-due',
        ];
    }

    public static function auditsByStatus(array $params = []): array
    {
        global $DB;

        $countsByStatus = array_fill_keys(
            ['planned', 'in_progress', 'completed', 'cancelled'],
            0
        );

        $rows = $DB->request([
            'SELECT' => ['status', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_audits',
            'GROUPBY' => 'status',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByStatus[$row['status']])) {
                $countsByStatus[$row['status']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByStatus as $status => $count) {
            $data[] = ['label' => $status, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Audits internes par statut', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    /**
     * Sprint 5 (risques fournisseurs/tiers), same low/medium/high/critical breakdown as
     * risksByLevel() above, for the dedicated supplier risk register table.
     */
    public static function supplierRisksByLevel(array $params = []): array
    {
        global $DB;

        $countsByLevel = array_fill_keys(['low', 'medium', 'high', 'critical'], 0);

        $rows = $DB->request([
            'SELECT' => ['risk_level', new QueryExpression('COUNT(*) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_supplierrisks',
            'GROUPBY' => 'risk_level',
        ]);

        foreach ($rows as $row) {
            if (isset($countsByLevel[$row['risk_level']])) {
                $countsByLevel[$row['risk_level']] = (int) $row['c'];
            }
        }

        $data = [];
        foreach ($countsByLevel as $level => $count) {
            $data[] = ['label' => $level, 'number' => $count];
        }

        return [
            'data' => $data,
            'label' => $params['label'] ?? __('Risques fournisseurs par niveau', 'grcmanager'),
            'icon' => 'ti ti-chart-pie',
        ];
    }

    /**
     * Number of distinct suppliers carrying at least one still-open (not accepted/closed)
     * high/critical supplier risk, the same "open" definition risksByLevel()/openRisksCount()
     * apply to the generic register: `identified`/`in_treatment` count, `accepted`/`closed` don't
     * (an accepted or closed high risk is a risk the organization has already made a documented
     * decision about, not one still needing attention on a dashboard).
     */
    public static function suppliersWithHighRiskCount(array $params = []): array
    {
        global $DB;

        $count = (int) $DB->request([
            'SELECT' => [new QueryExpression('COUNT(DISTINCT suppliers_id) AS c')],
            'FROM' => 'glpi_plugin_grcmanager_supplierrisks',
            'WHERE' => [
                'risk_level' => ['high', 'critical'],
                'status'     => ['identified', 'in_treatment'],
                new QueryExpression('suppliers_id > 0'),
            ],
        ])->current()['c'];

        return [
            'number' => $count,
            'label' => $params['label'] ?? __(
                'Fournisseurs avec au moins un risque élevé/critique ouvert',
                'grcmanager'
            ),
            'icon' => 'ti ti-building-warehouse',
        ];
    }
}
