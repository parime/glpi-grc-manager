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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Grcmanager\Compatibility\RequirementChecker;

// GLPI does NOT autoload plugin src/ classes on its own (confirmed against a real GLPI 11
// instance by the sibling plugins of this same author, see docs/design/DEVELOPMENT_PLAN.md
// "Sprint 1"). `composer install --no-dev` must be run after cloning, and any release package
// must bundle vendor/, see .github/workflows/release.yml.
require_once __DIR__ . '/vendor/autoload.php';

define('PLUGIN_GRCMANAGER_VERSION', '1.0.1');
define('PLUGIN_GRCMANAGER_MIN_GLPI', '11.0.0');
define('PLUGIN_GRCMANAGER_MAX_GLPI', '11.99.99');
define('PLUGIN_GRCMANAGER_MIN_PHP', '8.1.0');

/**
 * Called by GLPI on every page load once the plugin is active. Registers hooks.
 */
function plugin_init_grcmanager(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['grcmanager'] = true;

    if (!Plugin::isPluginActive('grcmanager')) {
        return;
    }

    // Single entry point under Assistance > Sécurité (matches the "used daily by the RSSI" intent
    // of the plugin: the generic risk register is the first screen a compliance officer needs).
    // Format confirmed against the sibling plugins of this same author (glpi-vulnerability-manager,
    // assetsign-glpi): a flat array of classes per menu category, keyed by GLPI's internal
    // category key ('tools'), not by its translated display label.
    // Sprint 3 (SoA, clause 6.1.3) adds PluginGrcmanagerControl alongside the risk register, same
    // 'tools' category. Sprint 4 (audits internes et CAPA, clause 9.2/10.2) adds
    // PluginGrcmanagerAudit and PluginGrcmanagerNonconformity the same way. Sprint 5 (risques
    // fournisseurs/tiers) adds PluginGrcmanagerSupplierRisk right after the generic risk register
    // it mirrors. Sprint 6 (formations et revues de direction, clauses 7.2/7.3/9.3) adds
    // PluginGrcmanagerTraining and PluginGrcmanagerManagementReview.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['grcmanager'] = [
        'tools' => [
            PluginGrcmanagerRisk::class,
            PluginGrcmanagerSupplierRisk::class,
            PluginGrcmanagerControl::class,
            PluginGrcmanagerAudit::class,
            PluginGrcmanagerNonconformity::class,
            PluginGrcmanagerTraining::class,
            PluginGrcmanagerManagementReview::class,
        ],
    ];

    // Sprint 2 (matrice de risque administrable, front/config.php) : reachable via
    // Configuration > Plugins > wrench icon on this plugin's row, same minimal-footprint pattern
    // as the sibling plugin glpi-vulnerability-manager's own remise-glpi-inspired Config screen
    // (its own setup.php notes it "likewise has no MENU_TOADD entry of its own"). Revisit with a
    // dedicated menu entry only if a later sprint makes this screen something used daily rather
    // than an occasional admin setting.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['grcmanager'] = 'front/config.php';

    // Dashboard KPI cards, kept accumulator-safe from the start (?array $cards = null, merged
    // onto rather than replacing): a bare no-argument signature returning only this plugin's own
    // cards would silently discard every other plugin's contribution when
    // Plugin::doHookFunction() chains multiple plugins hooking the same point, and would itself
    // be discarded when this plugin runs earlier in that chain. See hook.php.
    $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['grcmanager'] = 'plugin_grcmanager_dashboard_cards';
}

/**
 * Plugin metadata displayed in GLPI's plugin list.
 */
function plugin_version_grcmanager(): array
{
    return [
        'name'         => 'GLPI GRC Manager',
        'version'      => PLUGIN_GRCMANAGER_VERSION,
        'author'       => 'Vincent GUILLOTTE',
        'license'      => 'GPLv3',
        'homepage'     => 'https://github.com/parime/glpi-grc-manager',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GRCMANAGER_MIN_GLPI,
                'max' => PLUGIN_GRCMANAGER_MAX_GLPI,
            ],
            'php' => [
                'min' => PLUGIN_GRCMANAGER_MIN_PHP,
            ],
        ],
    ];
}

/**
 * Checked by GLPI before allowing activation. Must not assume GLPI's own autoloading has run for
 * plugin classes yet, hence the explicit require above.
 */
function plugin_grcmanager_check_prerequisites(): bool
{
    $checker = new RequirementChecker();

    if (!$checker->isPhpVersionSupported(PHP_VERSION, PLUGIN_GRCMANAGER_MIN_PHP)) {
        echo sprintf(
            'Cette version du plugin nécessite PHP %s minimum.',
            PLUGIN_GRCMANAGER_MIN_PHP
        );
        return false;
    }

    if (
        defined('GLPI_VERSION')
        && !$checker->isGlpiVersionSupported(
            GLPI_VERSION,
            PLUGIN_GRCMANAGER_MIN_GLPI,
            PLUGIN_GRCMANAGER_MAX_GLPI
        )
    ) {
        echo sprintf(
            'Cette version du plugin nécessite GLPI %s minimum (jusqu\'à %s).',
            PLUGIN_GRCMANAGER_MIN_GLPI,
            PLUGIN_GRCMANAGER_MAX_GLPI
        );
        return false;
    }

    return true;
}

/**
 * Checked by GLPI to verify the plugin's own configuration is valid (none required yet).
 */
function plugin_grcmanager_check_config(bool $verbose = false): bool
{
    return true;
}
