<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Install;

use CronTask;
use DBConnection;
use GlpiPlugin\Grcmanager\Services\Control\ControlCatalogDefaults;
use GlpiPlugin\Grcmanager\Services\DefaultSearchColumns;
use GlpiPlugin\Grcmanager\Services\Risk\RiskMatrixDefaults;
use Migration;
use Notification;
use NotificationTemplate;
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

    // Sprint 2 (matrice de risque administrable), same derivation rule.
    private const RISK_MATRIX_CONFIG_TABLE = 'glpi_plugin_grcmanager_riskmatrixconfig';

    // Sprint 3 (Déclaration d'Applicabilité / SoA, clause 6.1.3), same derivation rule.
    private const CONTROLS_TABLE = 'glpi_plugin_grcmanager_controls';

    // Many-to-many link table, `<left>_<right>` naming after the `glpi_plugin_grcmanager_` prefix
    // (both sides already lowercased+concatenated class-name suffixes), matching GLPI core's own
    // `Left_Right` relation-table convention (e.g. glpi_documents_items).
    private const CONTROLS_RISKS_TABLE = 'glpi_plugin_grcmanager_controls_risks';

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

        // Sprint 2 (TECH_DEBT.md "Matrice de risque fixe, non administrable") : matrice
        // probabilité x impact administrable depuis front/config.php (RiskMatrixConfig), une
        // seule ligne singleton (id=1), seedée avec exactement la grille fixe du Sprint 1
        // (RiskMatrixDefaults::MATRIX) pour qu'une installation existante ne voie aucun changement
        // tant qu'un administrateur ne modifie pas la matrice.
        if (!$DB->tableExists(self::RISK_MATRIX_CONFIG_TABLE)) {
            $query = "CREATE TABLE `" . self::RISK_MATRIX_CONFIG_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `matrix` text COMMENT 'JSON - grille probabilite x impact -> niveau de risque',
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());

            $DB->insert(self::RISK_MATRIX_CONFIG_TABLE, [
                'matrix'   => json_encode(RiskMatrixDefaults::MATRIX),
                'date_mod' => date('Y-m-d H:i:s'),
            ]);
        }

        // Sprint 3 (SoA, clause 6.1.3) : les 93 mesures Annexe A ISO/IEC 27001:2022, une ligne par
        // mesure. Seule la donnée stable (code, thème) est seedée, jamais l'intitulé traduit (voir
        // PluginGrcmanagerControl::getControlTitles()), sur le même principe que les enums
        // catégorie/probabilité/impact du registre de risques.
        if (!$DB->tableExists(self::CONTROLS_TABLE)) {
            $query = "CREATE TABLE `" . self::CONTROLS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `code` varchar(8) NOT NULL COMMENT 'Identifiant ISO/IEC 27001:2022 Annexe A, ex. A.5.1',
                `theme` varchar(32) NOT NULL
                    COMMENT 'organizational, people, physical, technological',
                `applicability` varchar(16) NOT NULL DEFAULT 'yes' COMMENT 'yes, no, partial',
                `justification` text
                    COMMENT 'Obligatoire si applicability != yes, voir PluginGrcmanagerControl',
                `implementation_status` varchar(16) NOT NULL DEFAULT 'not_started'
                    COMMENT 'not_started, in_progress, implemented, verified',
                `is_reviewed` tinyint NOT NULL DEFAULT 0
                    COMMENT 'Mis a 1 des la premiere sauvegarde explicite via le formulaire',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_code` (`code`),
                KEY `theme` (`theme`),
                KEY `applicability` (`applicability`),
                KEY `implementation_status` (`implementation_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Many-to-many : risque(s) du registre justifiant ou pilotant la mise en oeuvre d'une
        // mesure Annexe A (voir PluginGrcmanagerControl::getLinkedRisks()/syncLinkedRisks()).
        if (!$DB->tableExists(self::CONTROLS_RISKS_TABLE)) {
            $query = "CREATE TABLE `" . self::CONTROLS_RISKS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_grcmanager_controls_id` int {$keySign} NOT NULL,
                `plugin_grcmanager_risks_id` int {$keySign} NOT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_link` (`plugin_grcmanager_controls_id`, `plugin_grcmanager_risks_id`),
                KEY `controls_id` (`plugin_grcmanager_controls_id`),
                KEY `risks_id` (`plugin_grcmanager_risks_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        $this->seedControls();

        $this->seedReviewReminderNotification();

        // Sprint 2 (rappels de date de revue) : évalue chaque jour les risques dont la date de
        // revue est dépassée ou approche, et déclenche la notification GLPI seedée ci-dessus (voir
        // PluginGrcmanagerRisk::cronReviewreminder(), src/Services/Risk/ReviewReminderService.php).
        CronTask::Register(
            'PluginGrcmanagerRisk',
            'reviewreminder',
            DAY_TIMESTAMP,
            [
                'comment' => 'Notifie le propriétaire de chaque risque dont la date de revue est '
                    . 'dépassée ou approche',
                'mode'    => CronTask::MODE_EXTERNAL,
            ]
        );

        // ProfileRight::addProfileRights() is NOT idempotent, it does a raw INSERT with no
        // existence check, so it throws a duplicate-key error on any second call, guarded here
        // the same way the sibling plugin glpi-vulnerability-manager guards its own equivalent
        // call (see its Installer.php).
        if ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => self::RIGHT_NAME]])->count() === 0) {
            ProfileRight::addProfileRights([self::RIGHT_NAME]);
        }

        // addProfileRights() above inserts every profile at rights=0 (GLPI core's own default),
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

    /**
     * Seeds the Notification/NotificationTemplate/translation/target rows the review-date
     * reminder Cron task (PluginGrcmanagerRisk::cronReviewreminder()) needs to actually send
     * something via NotificationEvent::raiseEvent('review_due', ...), see
     * inc/notificationtargetrisk.class.php for the NotificationTarget class and the tag list.
     * Idempotent: skipped entirely if a Notification for this itemtype/event already exists (an
     * admin may have edited the template's wording since, never overwritten here).
     */
    private function seedReviewReminderNotification(): void
    {
        global $DB;

        $itemtype = 'PluginGrcmanagerRisk';
        $event    = 'review_due';

        $alreadySeeded = $DB->request([
            'FROM'  => 'glpi_notifications',
            'WHERE' => ['itemtype' => $itemtype, 'event' => $event],
        ])->count() > 0;

        if ($alreadySeeded) {
            return;
        }

        $template = new NotificationTemplate();
        $templateId = $template->add([
            'name'     => 'GRC Manager - Revue de risque à échéance',
            'itemtype' => $itemtype,
            'comment'  => 'Notification envoyée par la tâche automatique GRC Manager lorsqu\'un '
                . 'risque atteint ou dépasse sa date de revue.',
        ]);

        $DB->insert('glpi_notificationtemplatetranslations', [
            'notificationtemplates_id' => $templateId,
            'language'                 => '',
            'subject'                  => '##risk.action## : ##risk.title##',
            'content_text'             => "##risk.action## : ##risk.title##\n\n"
                . "Catégorie : ##risk.category##\n"
                . "Niveau de risque : ##risk.risklevel##\n"
                . "Date de revue : ##risk.reviewdate##\n\n"
                . "Voir le risque : ##risk.url##",
            'content_html'             => '<p><strong>##risk.action## : ##risk.title##</strong></p>'
                . '<p>Catégorie : ##risk.category##<br>'
                . 'Niveau de risque : ##risk.risklevel##<br>'
                . 'Date de revue : ##risk.reviewdate##</p>'
                . '<p><a href="##risk.url##">Voir le risque</a></p>',
        ]);

        $notification = new Notification();
        $notificationId = $notification->add([
            'name'         => 'GRC Manager - Revue de risque à échéance',
            'entities_id'  => 0,
            'is_recursive' => 1,
            'itemtype'     => $itemtype,
            'event'        => $event,
            'is_active'    => 1,
        ]);

        $DB->insert('glpi_notifications_notificationtemplates', [
            'notifications_id'         => $notificationId,
            'mode'                     => 'mailing',
            'notificationtemplates_id' => $templateId,
        ]);

        // Default recipient: the risk's own owner (Notification::ITEM_USER, resolved generically
        // by GLPI core from the `users_id` field, see NotificationTarget::addItemOwner() and
        // inc/notificationtargetrisk.class.php::addAdditionalTargets()). Without this row,
        // NotificationEvent::raiseEvent() finds the Notification above but no configured
        // recipient and silently sends nothing.
        $DB->insert('glpi_notificationtargets', [
            'items_id'         => Notification::ITEM_USER,
            'type'             => Notification::USER_TYPE,
            'notifications_id' => $notificationId,
        ]);
    }

    /**
     * Idempotent like seedSource() on the sibling plugin glpi-vulnerability-manager (same author,
     * same guard shape): each of the 93 controls is looked up by its unique `code` before
     * inserting, so re-running install() (upgrade path, `plugin:install --force`) never duplicates
     * a row nor overwrites an admin's own applicability/justification/status edits on an existing
     * one.
     */
    private function seedControls(): void
    {
        global $DB;

        foreach (ControlCatalogDefaults::CONTROLS as $code => $theme) {
            $exists = $DB->request([
                'FROM'  => self::CONTROLS_TABLE,
                'WHERE' => ['code' => $code],
            ])->count() > 0;

            if ($exists) {
                continue;
            }

            $DB->insert(self::CONTROLS_TABLE, [
                'code'                  => $code,
                'theme'                 => $theme,
                'applicability'         => 'yes',
                'implementation_status' => 'not_started',
                'is_reviewed'           => 0,
                'date_creation'         => date('Y-m-d H:i:s'),
                'date_mod'              => date('Y-m-d H:i:s'),
            ]);
        }
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

        // Sprint 2 (rappels de date de revue) : même règle que le plugin jumeau
        // glpi-vulnerability-manager (voir son propre Installer::uninstall()), supprime toutes
        // les tâches Cron enregistrées par ce plugin.
        CronTask::Unregister('grcmanager');

        $this->unseedReviewReminderNotification();

        // GLPI 11 forbids $DB->query() for direct queries ("Executing direct queries is not
        // allowed!"), same lesson learned live on the sibling plugin glpi-vulnerability-manager,
        // see its TECH_DEBT.md. Migration's own dropTable() is the sanctioned way to drop a table
        // outside doQuery()/QueryBuilder.
        $migration->dropTable(self::RISKS_TABLE);
        $migration->dropTable(self::RISK_MATRIX_CONFIG_TABLE);
        $migration->dropTable(self::CONTROLS_RISKS_TABLE);
        $migration->dropTable(self::CONTROLS_TABLE);

        $migration->executeMigration();

        return true;
    }

    /**
     * Purges (not soft-deletes) the Notification and NotificationTemplate seeded by
     * seedReviewReminderNotification(): both classes' own cleanDBonPurge() cascades to
     * glpi_notifications_notificationtemplates, glpi_notificationtargets and
     * glpi_notificationtemplatetranslations (confirmed by reading GLPI 11 core,
     * src/Notification.php and src/NotificationTemplate.php), so nothing is orphaned.
     */
    private function unseedReviewReminderNotification(): void
    {
        global $DB;

        $itemtype = 'PluginGrcmanagerRisk';

        $notification = new Notification();
        foreach ($DB->request(['FROM' => 'glpi_notifications', 'WHERE' => ['itemtype' => $itemtype]]) as $row) {
            $notification->delete(['id' => $row['id']], true);
        }

        $template = new NotificationTemplate();
        foreach ($DB->request(['FROM' => 'glpi_notificationtemplates', 'WHERE' => ['itemtype' => $itemtype]]) as $row) {
            $template->delete(['id' => $row['id']], true);
        }
    }
}
