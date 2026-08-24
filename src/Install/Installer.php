<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Install;

use DBConnection;
use GlpiPlugin\Grcmanager\Services\DefaultSearchColumns;
use Migration;
use ProfileRight;

/**
 * Handles plugin install/uninstall. Kept out of hook.php so it can evolve (and be
 * integration-tested) without touching the GLPI entry point.
 *
 * NOTE: relies on GLPI core classes (Migration, ProfileRight, DBConnection, global $DB) that are
 * only available inside a running GLPI instance, same exclusion rationale as the sibling plugin
 * glpi-vulnerability-manager, see phpstan.neon.dist.
 */
final class Installer
{
    public const RIGHT_NAME = 'plugin_grcmanager';

    // Table-name derivation confirmed against a real GLPI 11 instance by the sibling plugins of
    // this same author (glpi-vulnerability-manager, assetsign-glpi): the class-name suffix is
    // lowercased and concatenated as one block (no underscore inserted at camelCase boundaries).
    private const RISKS_TABLE = 'glpi_plugin_grcmanager_risks';

    public function install(Migration $migration): bool
    {
        global $DB;

        $migration->setVersion(PLUGIN_GRCMANAGER_VERSION);

        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $keySign   = DBConnection::getDefaultPrimaryKeySignOption();

        if (!$DB->tableExists(self::RISKS_TABLE)) {
            $query = "CREATE TABLE `" . self::RISKS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text,
                `category` varchar(32) NOT NULL DEFAULT 'process'
                    COMMENT 'people, process, physical, third_party, technical',
                `probability` varchar(16) NOT NULL DEFAULT 'possible'
                    COMMENT 'rare, possible, probable, certain',
                `impact` varchar(16) NOT NULL DEFAULT 'medium'
                    COMMENT 'low, medium, high, critical',
                `risk_level` varchar(16) NOT NULL DEFAULT 'medium'
                    COMMENT 'Derived from probability x impact, see RiskScoringService, never entered manually',
                `computed_score` decimal(5,2) NOT NULL DEFAULT 0,
                `treatment` varchar(16) NOT NULL DEFAULT ''
                    COMMENT 'accept, mitigate, transfer, avoid, empty = no decision yet',
                `users_id` int {$keySign} NOT NULL DEFAULT 0 COMMENT 'Risk owner',
                `justification` text,
                `review_date` date DEFAULT NULL,
                `status` varchar(16) NOT NULL DEFAULT 'identified'
                    COMMENT 'identified, in_treatment, accepted, closed',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `category` (`category`),
                KEY `risk_level` (`risk_level`),
                KEY `status` (`status`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // ProfileRight::addProfileRights() is NOT idempotent, it does a raw INSERT with no
        // existence check, so it throws a duplicate-key error on any second call, guarded here
        // the same way the sibling plugin glpi-vulnerability-manager guards its own equivalent
        // call (see its Installer.php).
        if ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => self::RIGHT_NAME]])->count() === 0) {
            ProfileRight::addProfileRights([self::RIGHT_NAME]);
        }

        // addProfileRights() above inserts every profile at rights=0 (GLPI core's own default) —
        // an admin is expected to grant it explicitly per profile via Administration > Profils,
        // except Super-Admin which always gets full rights so the plugin is usable right after
        // install without a second manual step, same lesson learned live on the sibling plugins
        // of this same author (glpi-vulnerability-manager, assetsign-glpi, Configuration-glpi-auto).
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => 'Super-Admin']]) as $profileRow) {
            ProfileRight::updateProfileRights((int) $profileRow['id'], [self::RIGHT_NAME => ALLSTANDARDRIGHT]);
        }

        $this->seedDisplayPreferences();

        $migration->executeMigration();

        return true;
    }

    private function seedDisplayPreferences(): void
    {
        global $DB;

        foreach (DefaultSearchColumns::COLUMNS as $itemtype => $columns) {
            $alreadySeeded = $DB->request([
                'FROM' => 'glpi_displaypreferences',
                'WHERE' => ['itemtype' => $itemtype, 'users_id' => 0],
            ])->count() > 0;

            if ($alreadySeeded) {
                continue;
            }

            foreach (array_values($columns) as $index => $searchOptionId) {
                $DB->insert('glpi_displaypreferences', [
                    'itemtype' => $itemtype,
                    'num' => $searchOptionId,
                    'rank' => $index + 1,
                    'users_id' => 0,
                    'interface' => 'central',
                ]);
            }
        }
    }

    public function uninstall(Migration $migration): bool
    {
        global $DB;

        ProfileRight::deleteProfileRights([self::RIGHT_NAME]);

        $DB->delete('glpi_displaypreferences', ['itemtype' => array_keys(DefaultSearchColumns::COLUMNS)]);

        // GLPI 11 forbids $DB->query() for direct queries ("Executing direct queries is not
        // allowed!"), same lesson learned live on the sibling plugin glpi-vulnerability-manager,
        // see its TECH_DEBT.md. Migration's own dropTable() is the sanctioned way to drop a table
        // outside doQuery()/QueryBuilder.
        $migration->dropTable(self::RISKS_TABLE);

        $migration->executeMigration();

        return true;
    }
}
