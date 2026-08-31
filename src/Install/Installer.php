<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Install;

use CronTask;
use DBConnection;
use GlpiPlugin\Grcmanager\Services\Control\ControlCatalogDefaults;
use GlpiPlugin\Grcmanager\Services\Dashboard\DefaultDashboardService;
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

    // Sprint 4 (audits internes et CAPA, clause 9.2/10.2), same table-name derivation rule.
    private const AUDITS_TABLE = 'glpi_plugin_grcmanager_audits';
    private const NONCONFORMITIES_TABLE = 'glpi_plugin_grcmanager_nonconformities';
    private const AUDITS_CONTROLS_TABLE = 'glpi_plugin_grcmanager_audits_controls';

    // Sprint 5 (risques fournisseurs/tiers), same table-name derivation rule.
    private const SUPPLIER_RISKS_TABLE = 'glpi_plugin_grcmanager_supplierrisks';

    // Sprint 6 (formations et revues de direction, clauses 7.2/7.3/9.3), same table-name
    // derivation rule.
    private const TRAININGS_TABLE = 'glpi_plugin_grcmanager_trainings';
    private const TRAININGS_USERS_TABLE = 'glpi_plugin_grcmanager_trainings_users';
    private const MANAGEMENT_REVIEWS_TABLE = 'glpi_plugin_grcmanager_managementreviews';
    private const MANAGEMENT_REVIEWS_USERS_TABLE = 'glpi_plugin_grcmanager_managementreviews_users';

    // Issue #25 (lien registre de risques <-> actifs GLPI/CMDB), même dérivation de nom de table.
    private const RISKS_ITEMS_TABLE = 'glpi_plugin_grcmanager_risks_items';

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

        // Sprint 4 (audits internes, clause 9.2) : un audit peut couvrir un ou plusieurs contrôles
        // Annexe A (lien many-to-many, voir PluginGrcmanagerAudit::getLinkedControls()/
        // syncLinkedControls()) et/ou une ou plusieurs catégories de risque (liste séparée par des
        // virgules, résolue via PluginGrcmanagerRisk::getCategories(), jamais une seconde
        // définition de cette énumération).
        if (!$DB->tableExists(self::AUDITS_TABLE)) {
            $query = "CREATE TABLE `" . self::AUDITS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `scope` text,
                `planned_date` date DEFAULT NULL,
                `actual_date` date DEFAULT NULL,
                `users_id` int {$keySign} NOT NULL DEFAULT 0 COMMENT 'Auditeur',
                `status` varchar(16) NOT NULL DEFAULT 'planned'
                    COMMENT 'planned, in_progress, completed, cancelled',
                `conclusion` text,
                `risk_categories` varchar(255) NOT NULL DEFAULT ''
                    COMMENT 'Liste de categories PluginGrcmanagerRisk separees par des virgules',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Many-to-many : contrôle(s) Annexe A dans le périmètre d'un audit (voir
        // PluginGrcmanagerAudit::getLinkedControls()/syncLinkedControls()), même convention que
        // CONTROLS_RISKS_TABLE ci-dessus.
        if (!$DB->tableExists(self::AUDITS_CONTROLS_TABLE)) {
            $query = "CREATE TABLE `" . self::AUDITS_CONTROLS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_grcmanager_audits_id` int {$keySign} NOT NULL,
                `plugin_grcmanager_controls_id` int {$keySign} NOT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_link` (`plugin_grcmanager_audits_id`, `plugin_grcmanager_controls_id`),
                KEY `audits_id` (`plugin_grcmanager_audits_id`),
                KEY `controls_id` (`plugin_grcmanager_controls_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Sprint 4 (non-conformités et CAPA, clause 10.2) : un constat (finding) rattaché à un
        // audit, avec le cycle complet cause racine -> action corrective -> action préventive ->
        // clôture vérifiée que la clause 10.2 impose. `plugin_grcmanager_audits_id` n'est pas une
        // vraie clé étrangère GLPI (pas de ON DELETE CASCADE natif ici), résolue par
        // PluginGrcmanagerNonconformity elle-même, même simplification assumée que le lien
        // contrôle <-> risque du Sprint 3 (voir TECH_DEBT.md).
        if (!$DB->tableExists(self::NONCONFORMITIES_TABLE)) {
            $query = "CREATE TABLE `" . self::NONCONFORMITIES_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text,
                `plugin_grcmanager_audits_id` int {$keySign} NOT NULL DEFAULT 0,
                `severity` varchar(16) NOT NULL DEFAULT 'minor' COMMENT 'minor, major, critical',
                `root_cause` text,
                `corrective_action` text,
                `preventive_action` text,
                `users_id` int {$keySign} NOT NULL DEFAULT 0 COMMENT 'Responsable',
                `due_date` date DEFAULT NULL,
                `status` varchar(16) NOT NULL DEFAULT 'open'
                    COMMENT 'open, in_progress, closed, verified',
                `closure_verification_date` date DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `plugin_grcmanager_audits_id` (`plugin_grcmanager_audits_id`),
                KEY `severity` (`severity`),
                KEY `status` (`status`),
                KEY `users_id` (`users_id`),
                KEY `due_date` (`due_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Sprint 5 (risques fournisseurs/tiers) : même structure que RISKS_TABLE ci-dessus (mêmes
        // colonnes de notation/traitement, voir GlpiPlugin\Grcmanager\Traits\RiskAssessmentTrait),
        // avec en plus `suppliers_id`, une vraie clé étrangère vers le `Supplier` natif de GLPI
        // (`glpi_suppliers`), jamais un concept fournisseur parallèle propre à ce plugin.
        if (!$DB->tableExists(self::SUPPLIER_RISKS_TABLE)) {
            $query = "CREATE TABLE `" . self::SUPPLIER_RISKS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `suppliers_id` int {$keySign} NOT NULL DEFAULT 0 COMMENT 'glpi_suppliers.id',
                `title` varchar(255) NOT NULL,
                `description` text,
                `category` varchar(32) NOT NULL DEFAULT 'third_party'
                    COMMENT 'people, process, physical, third_party, technical',
                `probability` varchar(16) NOT NULL DEFAULT 'possible'
                    COMMENT 'rare, possible, probable, certain',
                `impact` varchar(16) NOT NULL DEFAULT 'medium'
                    COMMENT 'low, medium, high, critical',
                `risk_level` varchar(16) NOT NULL DEFAULT 'medium'
                    COMMENT 'Derived from probability x impact, see RiskAssessmentTrait, never entered manually',
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
                KEY `suppliers_id` (`suppliers_id`),
                KEY `category` (`category`),
                KEY `risk_level` (`risk_level`),
                KEY `status` (`status`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Sprint 6 (suivi des formations de sensibilisation, clauses 7.2/7.3) : une ligne par
        // session/campagne de formation. `renewal_period_months` = 0 signifie qu'aucun
        // renouvellement periodique n'est requis (voir PluginGrcmanagerTraining::getOverdueParticipants()).
        if (!$DB->tableExists(self::TRAININGS_TABLE)) {
            $query = "CREATE TABLE `" . self::TRAININGS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text,
                `format` varchar(16) NOT NULL DEFAULT 'in_person'
                    COMMENT 'in_person, e_learning, other',
                `target_audience` varchar(255) NOT NULL DEFAULT '' COMMENT 'Texte libre',
                `date_delivered` date DEFAULT NULL,
                `is_mandatory` tinyint NOT NULL DEFAULT 1,
                `renewal_period_months` int NOT NULL DEFAULT 0
                    COMMENT '0 = pas de renouvellement requis',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `format` (`format`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Many-to-many + suivi individuel : un participant (vrai `User` natif de GLPI) par ligne,
        // avec son propre statut/date de realisation (pas seulement un lien, contrairement aux
        // liens controle<->risque/audit<->controle des Sprints 3-4 : un auditeur ISO 27001 doit
        // pouvoir voir qui a precisement termine quelle formation et quand, pas seulement un
        // decompte agrege), voir PluginGrcmanagerTraining::syncParticipants()/getParticipants().
        if (!$DB->tableExists(self::TRAININGS_USERS_TABLE)) {
            $query = "CREATE TABLE `" . self::TRAININGS_USERS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_grcmanager_trainings_id` int {$keySign} NOT NULL,
                `users_id` int {$keySign} NOT NULL,
                `completion_status` varchar(16) NOT NULL DEFAULT 'pending'
                    COMMENT 'pending, completed, exempted',
                `completion_date` date DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_link` (`plugin_grcmanager_trainings_id`, `users_id`),
                KEY `trainings_id` (`plugin_grcmanager_trainings_id`),
                KEY `users_id` (`users_id`),
                KEY `completion_status` (`completion_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Sprint 6 (revues de direction, clause 9.3) : une ligne par revue de direction realisee ou
        // planifiee, avec l'ordre du jour et les decisions/actions en texte libre (pas un lien
        // fort vers le mecanisme CAPA existant, voir PluginGrcmanagerManagementReview pour le
        // raisonnement, et TECH_DEBT.md).
        if (!$DB->tableExists(self::MANAGEMENT_REVIEWS_TABLE)) {
            $query = "CREATE TABLE `" . self::MANAGEMENT_REVIEWS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `status` varchar(16) NOT NULL DEFAULT 'planned' COMMENT 'planned, completed',
                `review_date` date DEFAULT NULL,
                `agenda` text,
                `decisions` text,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Many-to-many : participants (vrais `User` natifs de GLPI) d'une revue de direction, meme
        // convention "lien simple en acces direct $DB" que AUDITS_CONTROLS_TABLE ci-dessus (voir
        // PluginGrcmanagerManagementReview::getAttendees()/syncAttendees()).
        if (!$DB->tableExists(self::MANAGEMENT_REVIEWS_USERS_TABLE)) {
            $query = "CREATE TABLE `" . self::MANAGEMENT_REVIEWS_USERS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_grcmanager_managementreviews_id` int {$keySign} NOT NULL,
                `users_id` int {$keySign} NOT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_link` (`plugin_grcmanager_managementreviews_id`, `users_id`),
                KEY `reviews_id` (`plugin_grcmanager_managementreviews_id`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        // Issue #25 (lien registre de risques <-> actifs GLPI/CMDB) : table de liaison
        // POLYMORPHE (itemtype/items_id), pas une deuxième colonne d'ID fixe comme
        // CONTROLS_RISKS_TABLE/AUDITS_CONTROLS_TABLE ci-dessus (deux itemtypes fixes, propres à ce
        // plugin) : la cible ici est n'importe quel itemtype GLPI géré (Computer, actif
        // personnalisé...), exactement le même modèle que les tables de liaison polymorphes du
        // cœur GLPI lui-même (ex. glpi_documents_items). Un risque peut avoir zéro ligne ici (reste
        // un risque purement organisationnel, ex. "processus de recrutement", voir l'issue) — ce
        // n'est pas une relation obligatoire, contrairement à `users_id` (propriétaire) sur
        // RISKS_TABLE ci-dessus qui, elle, est bien une colonne directe (relation 1-vers-1
        // implicite avec un `User`, toujours renseignée). Voir
        // PluginGrcmanagerRisk::getLinkedAssets()/syncLinkedAssets()/getRisksLinkedToItem().
        if (!$DB->tableExists(self::RISKS_ITEMS_TABLE)) {
            $query = "CREATE TABLE `" . self::RISKS_ITEMS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `plugin_grcmanager_risks_id` int {$keySign} NOT NULL,
                `itemtype` varchar(100) NOT NULL,
                `items_id` int {$keySign} NOT NULL DEFAULT 0,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity_link` (`plugin_grcmanager_risks_id`, `itemtype`, `items_id`),
                KEY `risks_id` (`plugin_grcmanager_risks_id`),
                KEY `item` (`itemtype`, `items_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
        }

        $this->seedControls();

        $this->seedReviewReminderNotification(
            'PluginGrcmanagerRisk',
            'risk',
            'Revue de risque à échéance',
            'risque'
        );
        $this->seedReviewReminderNotification(
            'PluginGrcmanagerSupplierRisk',
            'supplierrisk',
            'Revue de risque fournisseur à échéance',
            'risque fournisseur'
        );
        $this->seedCapaOverdueNotification();
        $this->seedTrainingRenewalNotification();

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

        // Sprint 5 (risques fournisseurs/tiers) : même mécanisme de rappel de revue que le
        // registre générique ci-dessus (voir GlpiPlugin\Grcmanager\Services\Risk\ReviewReminderService,
        // partagée par les deux tâches Cron), mais une tâche dédiée : le modèle de tâche Cron de
        // GLPI enregistre/déclenche un point d'entrée statique par itemtype, une seule tâche ne
        // peut donc pas couvrir les deux tables elle-même (voir
        // PluginGrcmanagerSupplierRisk::cronReviewreminder()).
        CronTask::Register(
            'PluginGrcmanagerSupplierRisk',
            'reviewreminder',
            DAY_TIMESTAMP,
            [
                'comment' => 'Notifie le propriétaire de chaque risque fournisseur dont la date de '
                    . 'revue est dépassée ou approche',
                'mode'    => CronTask::MODE_EXTERNAL,
            ]
        );

        // Sprint 4 (CAPA en retard) : évalue chaque jour les non-conformités dont l'échéance est
        // dépassée et qui ne sont ni clôturées ni vérifiées, et déclenche la notification GLPI
        // seedée ci-dessus (voir PluginGrcmanagerNonconformity::cronOverduecapa(),
        // src/Services/Capa/OverdueCapaService.php).
        CronTask::Register(
            'PluginGrcmanagerNonconformity',
            'overduecapa',
            DAY_TIMESTAMP,
            [
                'comment' => 'Notifie le responsable de chaque action corrective/préventive dont '
                    . 'l\'échéance est dépassée',
                'mode'    => CronTask::MODE_EXTERNAL,
            ]
        );

        // Sprint 6 (formations, clauses 7.2/7.3) : évalue chaque jour les formations ayant au
        // moins un participant en retard de renouvellement, et déclenche la notification GLPI
        // seedée ci-dessus (voir PluginGrcmanagerTraining::cronRenewaldue(),
        // src/Services/Training/TrainingRenewalService.php).
        CronTask::Register(
            'PluginGrcmanagerTraining',
            'renewaldue',
            DAY_TIMESTAMP,
            [
                'comment' => 'Notifie chaque participant en retard de renouvellement pour une '
                    . 'formation',
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

        // Sprint 7 (tableaux de bord, consolidation) : tableau de bord natif GLPI prêt à l'emploi,
        // voir DefaultDashboardService pour le détail (idempotent comme le reste de cette
        // méthode : upsert sur une clé fixe, jamais dupliqué au réinstall).
        DefaultDashboardService::seed();

        $migration->executeMigration();

        return true;
    }

    /**
     * Seeds the Notification/NotificationTemplate/translation/target rows a review-date reminder
     * Cron task (PluginGrcmanagerRisk::cronReviewreminder(), and since Sprint 5
     * PluginGrcmanagerSupplierRisk::cronReviewreminder()) needs to actually send something via
     * NotificationEvent::raiseEvent('review_due', ...), see inc/notificationtargetrisk.class.php /
     * inc/notificationtargetsupplierrisk.class.php for the NotificationTarget classes and their tag
     * lists. Idempotent per itemtype: skipped entirely if a Notification for that itemtype/event
     * already exists (an admin may have edited the template's wording since, never overwritten
     * here). Generalized at Sprint 5 (was PluginGrcmanagerRisk-only before) so both review-reminder
     * itemtypes are seeded from the exact same implementation, only their tag prefix/wording differ.
     *
     * @param string $itemtype  'PluginGrcmanagerRisk' or 'PluginGrcmanagerSupplierRisk'.
     * @param string $tagPrefix Matches the NotificationTarget's own tag prefix ('risk'/'supplierrisk').
     * @param string $name      Human-readable Notification/NotificationTemplate name suffix.
     * @param string $noun      French noun used in the seeded comment ('risque'/'risque fournisseur').
     */
    private function seedReviewReminderNotification(
        string $itemtype,
        string $tagPrefix,
        string $name,
        string $noun
    ): void {
        global $DB;

        $event = 'review_due';

        $alreadySeeded = $DB->request([
            'FROM'  => 'glpi_notifications',
            'WHERE' => ['itemtype' => $itemtype, 'event' => $event],
        ])->count() > 0;

        if ($alreadySeeded) {
            return;
        }

        $template = new NotificationTemplate();
        $templateId = $template->add([
            'name'     => 'GRC Manager - ' . $name,
            'itemtype' => $itemtype,
            'comment'  => 'Notification envoyée par la tâche automatique GRC Manager lorsqu\'un '
                . $noun . ' atteint ou dépasse sa date de revue.',
        ]);

        $DB->insert('glpi_notificationtemplatetranslations', [
            'notificationtemplates_id' => $templateId,
            'language'                 => '',
            'subject'                  => "##{$tagPrefix}.action## : ##{$tagPrefix}.title##",
            'content_text'             => "##{$tagPrefix}.action## : ##{$tagPrefix}.title##\n\n"
                . "Catégorie : ##{$tagPrefix}.category##\n"
                . "Niveau de risque : ##{$tagPrefix}.risklevel##\n"
                . "Date de revue : ##{$tagPrefix}.reviewdate##\n\n"
                . "Voir le " . $noun . " : ##{$tagPrefix}.url##",
            'content_html'             => "<p><strong>##{$tagPrefix}.action## : ##{$tagPrefix}.title##</strong></p>"
                . '<p>Catégorie : ' . "##{$tagPrefix}.category##<br>"
                . 'Niveau de risque : ' . "##{$tagPrefix}.risklevel##<br>"
                . 'Date de revue : ' . "##{$tagPrefix}.reviewdate##</p>"
                . "<p><a href=\"##{$tagPrefix}.url##\">Voir le " . $noun . '</a></p>',
        ]);

        $notification = new Notification();
        $notificationId = $notification->add([
            'name'         => 'GRC Manager - ' . $name,
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
        // inc/notificationtargetrisk.class.php / inc/notificationtargetsupplierrisk.class.php's
        // own addAdditionalTargets()). Without this row, NotificationEvent::raiseEvent() finds the
        // Notification above but no configured recipient and silently sends nothing.
        $DB->insert('glpi_notificationtargets', [
            'items_id'         => Notification::ITEM_USER,
            'type'             => Notification::USER_TYPE,
            'notifications_id' => $notificationId,
        ]);
    }

    /**
     * Same structure as seedReviewReminderNotification() above, for the Sprint 4 overdue-CAPA Cron
     * task (PluginGrcmanagerNonconformity::cronOverduecapa(), event 'capa_overdue', see
     * inc/notificationtargetnonconformity.class.php). Idempotent for the same reason.
     */
    private function seedCapaOverdueNotification(): void
    {
        global $DB;

        $itemtype = 'PluginGrcmanagerNonconformity';
        $event    = 'capa_overdue';

        $alreadySeeded = $DB->request([
            'FROM'  => 'glpi_notifications',
            'WHERE' => ['itemtype' => $itemtype, 'event' => $event],
        ])->count() > 0;

        if ($alreadySeeded) {
            return;
        }

        $template = new NotificationTemplate();
        $templateId = $template->add([
            'name'     => 'GRC Manager - Action corrective/préventive en retard',
            'itemtype' => $itemtype,
            'comment'  => 'Notification envoyée par la tâche automatique GRC Manager lorsqu\'une '
                . 'non-conformité dépasse son échéance sans être clôturée ni vérifiée.',
        ]);

        $DB->insert('glpi_notificationtemplatetranslations', [
            'notificationtemplates_id' => $templateId,
            'language'                 => '',
            'subject'                  => '##nc.action## : ##nc.title##',
            'content_text'             => "##nc.action## : ##nc.title##\n\n"
                . "Audit : ##nc.audit##\n"
                . "Sévérité : ##nc.severity##\n"
                . "Échéance : ##nc.duedate##\n\n"
                . "Voir la non-conformité : ##nc.url##",
            'content_html'             => '<p><strong>##nc.action## : ##nc.title##</strong></p>'
                . '<p>Audit : ##nc.audit##<br>'
                . 'Sévérité : ##nc.severity##<br>'
                . 'Échéance : ##nc.duedate##</p>'
                . '<p><a href="##nc.url##">Voir la non-conformité</a></p>',
        ]);

        $notification = new Notification();
        $notificationId = $notification->add([
            'name'         => 'GRC Manager - Action corrective/préventive en retard',
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

        // Default recipient: the non-conformity's own responsible owner (`users_id`), same
        // generic resolution as seedReviewReminderNotification() above.
        $DB->insert('glpi_notificationtargets', [
            'items_id'         => Notification::ITEM_USER,
            'type'             => Notification::USER_TYPE,
            'notifications_id' => $notificationId,
        ]);
    }

    /**
     * Same structure as seedCapaOverdueNotification() above, for the Sprint 6 training-renewal Cron
     * task (PluginGrcmanagerTraining::cronRenewaldue(), event 'training_renewal_due', see
     * inc/notificationtargettraining.class.php). Idempotent for the same reason.
     *
     * Deliberately does NOT insert a `glpi_notificationtargets` row (unlike every notification
     * seeded above): a training has no single "item owner" field to resolve a default recipient
     * from, its recipients are the (possibly several) overdue participants,
     * PluginGrcmanagerNotificationTargetTraining::addAdditionalTargets() resolves and adds every
     * one of them itself, unconditionally, regardless of any configured target row (see its own
     * docblock).
     */
    private function seedTrainingRenewalNotification(): void
    {
        global $DB;

        $itemtype = 'PluginGrcmanagerTraining';
        $event    = 'training_renewal_due';

        $alreadySeeded = $DB->request([
            'FROM'  => 'glpi_notifications',
            'WHERE' => ['itemtype' => $itemtype, 'event' => $event],
        ])->count() > 0;

        if ($alreadySeeded) {
            return;
        }

        $template = new NotificationTemplate();
        $templateId = $template->add([
            'name'     => 'GRC Manager - Renouvellement de formation en retard',
            'itemtype' => $itemtype,
            'comment'  => 'Notification envoyée par la tâche automatique GRC Manager lorsqu\'un '
                . 'participant est en retard de renouvellement pour une formation.',
        ]);

        $DB->insert('glpi_notificationtemplatetranslations', [
            'notificationtemplates_id' => $templateId,
            'language'                 => '',
            'subject'                  => '##training.action## : ##training.title##',
            'content_text'             => "##training.action## : ##training.title##\n\n"
                . "Format : ##training.format##\n"
                . "Renouvellement (mois) : ##training.renewalmonths##\n\n"
                . "Voir la formation : ##training.url##",
            'content_html'             => '<p><strong>##training.action## : ##training.title##</strong></p>'
                . '<p>Format : ' . "##training.format##<br>"
                . 'Renouvellement (mois) : ' . "##training.renewalmonths##</p>"
                . '<p><a href="##training.url##">Voir la formation</a></p>',
        ]);

        $notification = new Notification();
        $notificationId = $notification->add([
            'name'         => 'GRC Manager - Renouvellement de formation en retard',
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

        $this->unseedNotification('PluginGrcmanagerRisk');
        $this->unseedNotification('PluginGrcmanagerSupplierRisk');
        $this->unseedNotification('PluginGrcmanagerNonconformity');
        $this->unseedNotification('PluginGrcmanagerTraining');

        // Sprint 7 (tableaux de bord) : retire le tableau de bord natif seedé par
        // DefaultDashboardService::seed() ci-dessus, avant que ses tables ne disparaissent.
        DefaultDashboardService::remove();

        // GLPI 11 forbids $DB->query() for direct queries ("Executing direct queries is not
        // allowed!"), same lesson learned live on the sibling plugin glpi-vulnerability-manager,
        // see its TECH_DEBT.md. Migration's own dropTable() is the sanctioned way to drop a table
        // outside doQuery()/QueryBuilder.
        $migration->dropTable(self::RISKS_TABLE);
        $migration->dropTable(self::SUPPLIER_RISKS_TABLE);
        $migration->dropTable(self::RISK_MATRIX_CONFIG_TABLE);
        $migration->dropTable(self::CONTROLS_RISKS_TABLE);
        $migration->dropTable(self::CONTROLS_TABLE);
        $migration->dropTable(self::AUDITS_CONTROLS_TABLE);
        $migration->dropTable(self::NONCONFORMITIES_TABLE);
        $migration->dropTable(self::AUDITS_TABLE);
        $migration->dropTable(self::TRAININGS_USERS_TABLE);
        $migration->dropTable(self::TRAININGS_TABLE);
        $migration->dropTable(self::MANAGEMENT_REVIEWS_USERS_TABLE);
        $migration->dropTable(self::MANAGEMENT_REVIEWS_TABLE);
        $migration->dropTable(self::RISKS_ITEMS_TABLE);

        $migration->executeMigration();

        return true;
    }

    /**
     * Purges (not soft-deletes) the Notification and NotificationTemplate seeded for a given
     * itemtype by seedReviewReminderNotification()/seedCapaOverdueNotification(): both classes'
     * own cleanDBonPurge() cascades to glpi_notifications_notificationtemplates,
     * glpi_notificationtargets and glpi_notificationtemplatetranslations (confirmed by reading
     * GLPI 11 core, src/Notification.php and src/NotificationTemplate.php), so nothing is
     * orphaned. Generalized from Sprint 2's single-itemtype version to also cover Sprint 4's
     * PluginGrcmanagerNonconformity notification, called once per itemtype above.
     */
    private function unseedNotification(string $itemtype): void
    {
        global $DB;

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
