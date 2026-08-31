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

use GlpiPlugin\Grcmanager\Install\Installer;
use GlpiPlugin\Grcmanager\Services\Dashboard\DashboardCardService;

/**
 * Hooks::DASHBOARD_CARDS callback (registered in setup.php).
 *
 * The nullable, accumulator-merging signature is deliberate from the very first commit of this
 * plugin: the sibling plugin glpi-vulnerability-manager (same author) shipped a bare
 * `array $cards = []` signature first and had to fix it after finding, against a real instance
 * running alongside another plugin also hooking DASHBOARD_CARDS, that Plugin::doHookFunction()
 * chains every registered callback through the same accumulator
 * (`$ret = call_user_func($function, $ret)`, never array_merge()-ing the results itself) — a
 * callback that ignores the incoming array and returns only its own cards silently discards every
 * other plugin's contribution whenever it runs later in the chain, and gets discarded itself when
 * it runs earlier. The parameter must accept null, not just default to an empty array: Grid.php
 * calls the very first plugin in the chain as call_user_func($function, null), an explicit null
 * that does NOT fall through to a `array $cards = []` default (PHP only applies a default when
 * the argument is omitted, not when null is passed for a non-nullable type).
 */
function plugin_grcmanager_dashboard_cards(?array $cards = null): array
{
    $cards ??= [];

    $group = __('GRC Manager', 'grcmanager');

    return $cards + [
        'grcmanager_open_risks' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Risques ouverts', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::openRisksCount',
        ],
        'grcmanager_risks_by_level' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Risques par niveau', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::risksByLevel',
        ],
        'grcmanager_risks_by_category' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Risques par catégorie', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::risksByCategory',
        ],
        'grcmanager_risks_pending_review' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Risques en attente de revue', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::risksPendingReviewCount',
        ],
        // Sprint 3 (SoA, clause 6.1.3).
        'grcmanager_soa_reviewed' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Contrôles SoA revus', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::soaReviewedCount',
        ],
        'grcmanager_soa_by_applicability' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Contrôles SoA par applicabilité', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::soaByApplicability',
        ],
        'grcmanager_soa_by_implementation_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Contrôles SoA par état de mise en œuvre', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::soaByImplementationStatus',
        ],
        // Sprint 4 (audits internes et CAPA, clause 9.2/10.2).
        'grcmanager_open_nonconformities' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Non-conformités ouvertes', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::openNonconformitiesCount',
        ],
        'grcmanager_overdue_capa' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Actions correctives/préventives en retard', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::overdueCapaCount',
        ],
        'grcmanager_audits_by_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Audits internes par statut', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::auditsByStatus',
        ],
        // Sprint 5 (risques fournisseurs/tiers).
        'grcmanager_supplierrisks_by_level' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Risques fournisseurs par niveau', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::supplierRisksByLevel',
        ],
        'grcmanager_suppliers_with_high_risk' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Fournisseurs avec au moins un risque élevé/critique ouvert', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::suppliersWithHighRiskCount',
        ],
        // Sprint 6 (formations et revues de direction, clauses 7.2/7.3/9.3).
        'grcmanager_training_completion_rate' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Taux de réalisation des formations', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::trainingCompletionRate',
        ],
        'grcmanager_training_overdue_renewal' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Participants en retard de renouvellement de formation', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::trainingOverdueRenewalCount',
        ],
        'grcmanager_management_reviews_by_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Revues de direction par statut', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::managementReviewsByStatus',
        ],
        // Issue #28 (bibliothèque de politiques de sécurité versionnées, A.5.1).
        'grcmanager_policies_pending_review' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Politiques en attente de revue', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::policiesPendingReviewCount',
        ],
        'grcmanager_policies_by_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Politiques de sécurité par statut', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::policiesByStatus',
        ],
        // Issue #30 (registre des obligations légales/réglementaires/contractuelles, clause 4.2).
        'grcmanager_obligations_non_compliant' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Obligations non conformes', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::obligationsNonCompliantCount',
        ],
        'grcmanager_obligations_pending_review' => [
            'widgettype' => ['bigNumber'],
            'label' => __('Obligations en attente de revue', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::obligationsPendingReviewCount',
        ],
        'grcmanager_obligations_by_type' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Obligations par type', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::obligationsByType',
        ],
        'grcmanager_obligations_by_compliance_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Obligations par statut de conformité', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::obligationsByComplianceStatus',
        ],
        // Issue #32 (objectifs ISMS et suivi de KPI dans le temps, clause 6.2).
        'grcmanager_objectives_by_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Objectifs ISMS par statut', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::objectivesByStatus',
        ],
        // Issue #29 (registre des incidents de sécurité de l'information, A.5.24-27).
        'grcmanager_security_incidents_by_status' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Incidents de sécurité par statut', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::securityIncidentsByStatus',
        ],
        'grcmanager_security_incidents_by_severity' => [
            'widgettype' => ['multipleNumber', 'pie', 'donut', 'bar', 'hbar'],
            'label' => __('Incidents de sécurité par sévérité', 'grcmanager'),
            'group' => $group,
            'provider' => DashboardCardService::class . '::securityIncidentsBySeverity',
        ],
    ];
}

function plugin_grcmanager_install(): bool
{
    $migration = new Migration(PLUGIN_GRCMANAGER_VERSION);

    return (new Installer())->install($migration);
}

function plugin_grcmanager_uninstall(): bool
{
    $migration = new Migration(PLUGIN_GRCMANAGER_VERSION);

    return (new Installer())->uninstall($migration);
}
