<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Install;

use CronTask;
use DBConnection;
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
