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
use GlpiPlugin\Grcmanager\Services\Risk\LinkableItemtypes;

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
    // PluginGrcmanagerTraining and PluginGrcmanagerManagementReview. Issue #32 (objectifs ISMS et
    // suivi de KPI dans le temps, clause 6.2) adds PluginGrcmanagerObjective last: the dashboard
    // (Sprint 7) shows the ISMS's current state, this screen is where an admin sets and tracks
    // measurable objectives over time, a natural final entry in this same menu.
    // PluginGrcmanagerObjectiveMeasurement (the per-objective measurement history) deliberately
    // has NO menu entry of its own: it is only ever added/removed inline from its parent
    // objective's own form (see PluginGrcmanagerObjective::showMeasurementHistory()), same
    // "no menu entry for a pure link/child table" convention as every many-to-many link in this
    // plugin family.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['grcmanager'] = [
        'tools' => [
            PluginGrcmanagerRisk::class,
            PluginGrcmanagerSupplierRisk::class,
            PluginGrcmanagerControl::class,
            PluginGrcmanagerAudit::class,
            PluginGrcmanagerNonconformity::class,
            PluginGrcmanagerTraining::class,
            PluginGrcmanagerManagementReview::class,
            PluginGrcmanagerObjective::class,
        ],
    ];

    // Sprint 2 (matrice de risque administrable, front/config.php) : reachable via
    // Configuration > Plugins > wrench icon on this plugin's row, same minimal-footprint pattern
    // as the sibling plugin glpi-vulnerability-manager's own remise-glpi-inspired Config screen
    // (its own setup.php notes it "likewise has no MENU_TOADD entry of its own"). Revisit with a
    // dedicated menu entry only if a later sprint makes this screen something used daily rather
    // than an occasional admin setting.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['grcmanager'] = 'front/config.php';

    // Issue #25 (lien registre de risques <-> actifs GLPI/CMDB) : onglet "Risques" en lecture
    // seule sur la fiche de chaque actif potentiellement lié (voir
    // PluginGrcmanagerRisk::getTabNameForItem()/displayTabContentForItem()), même mécanisme
    // Plugin::registerClass()/addtabon que le plugin jumeau assetsign-glpi pour ses propres
    // onglets (voir son setup.php). Liste FIXE (LinkableItemtypes::DEFAULT_ITEMTYPES), pas le
    // résultat dynamique de PluginGrcmanagerRisk::getLinkableItemtypes() (qui ajoute aussi les
    // actifs personnalisés actifs) : à l'exécution de ce hook (listener InitializePlugins), GLPI
    // n'a pas encore chargé les définitions d'actifs personnalisés en mémoire, même limitation de
    // séquencement déjà documentée par assetsign-glpi pour sa propre
    // Config::getAllManageableItemtypes() (voir son docblock) — un actif personnalisé reste tout
    // de même liable depuis le formulaire du risque, seul l'onglet retour sur sa propre fiche n'est
    // pas posé (voir TECH_DEBT.md).
    Plugin::registerClass(PluginGrcmanagerRisk::class, [
        'addtabon' => LinkableItemtypes::DEFAULT_ITEMTYPES,
    ]);

    // Issue #26 (classification Confidentialité/Intégrité/Disponibilité des actifs) : même
    // mécanisme et même liste FIXE d'itemtypes qu'immédiatement ci-dessus pour l'onglet "Risques"
    // de l'issue #25 (même limitation de séquencement InitializePlugins/CustomObjectsBoot, voir son
    // commentaire ci-dessus et TECH_DEBT.md), un second onglet indépendant sur la fiche de chaque
    // actif liable pour consulter/éditer sa classification C/I/D (voir
    // PluginGrcmanagerAssetClassification::getTabNameForItem()/displayTabContentForItem()).
    Plugin::registerClass(PluginGrcmanagerAssetClassification::class, [
        'addtabon' => LinkableItemtypes::DEFAULT_ITEMTYPES,
    ]);

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
