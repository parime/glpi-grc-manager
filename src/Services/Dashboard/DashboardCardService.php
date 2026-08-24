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
}
