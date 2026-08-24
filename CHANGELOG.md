# Changelog

Toutes les évolutions notables de ce projet sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), et ce projet adhère au
[Semantic Versioning](https://semver.org/lang/fr/) (`MAJEUR.MINEUR.CORRECTIF`).

## [Non publié]

### Added

- **Risques fournisseurs/tiers** : nouveau registre dédié, `PluginGrcmanagerSupplierRisk`, avec
  exactement les mêmes mécanismes d'acceptation/traitement que le registre générique
  (`PluginGrcmanagerRisk`) : catégorie/probabilité/impact/niveau de risque calculé, traitement
  (accepter/mitiger/transférer/éviter), propriétaire, justification, date de revue, statut. Chaque
  risque fournisseur est rattaché à un vrai `Supplier` natif de GLPI (`suppliers_id`, jamais un
  concept fournisseur propre à ce plugin), rattachement obligatoire (validation serveur réelle,
  vérifiée en direct). Le calcul de notation (`computed_score`/`risk_level` à partir de
  `probability`/`impact` via la matrice administrable existante) est désormais partagé avec le
  registre générique via un nouveau trait `GlpiPlugin\Grcmanager\Traits\RiskAssessmentTrait`
  (`src/Traits/RiskAssessmentTrait.php`), qui regroupe aussi les énumérations et le rendu des
  badges colorés traduits, pour que les deux registres ne puissent jamais diverger sur leur
  notation ; `PluginGrcmanagerRisk` a été refactoré pour utiliser ce même trait, sans changement de
  comportement. Même mécanisme de rappel de revue que le Sprint 2
  (`GlpiPlugin\Grcmanager\Services\Risk\ReviewReminderService`, généralisée pour piloter les deux
  tâches Cron `PluginGrcmanagerRisk::cronReviewreminder()` et
  `PluginGrcmanagerSupplierRisk::cronReviewreminder()` à partir de la même implémentation), nouvelle
  notification GLPI native dédiée (`inc/notificationtargetsupplierrisk.class.php`). Liste filtrable
  par catégorie/probabilité/impact/niveau/traitement/statut et par le Fournisseur lié (vrai filtre
  GLPI natif, jointure réelle sur `glpi_suppliers`), badges colorés traduits, lien cliquable, vue
  « Mes risques fournisseurs » (`front/supplierrisk.php`), formulaire dédié
  (`front/supplierrisk.form.php`). 2 nouvelles cartes de tableau de bord : risques fournisseurs par
  niveau, nombre de fournisseurs ayant au moins un risque élevé/critique encore ouvert
  (`DashboardCardService`, même signature accumulateur-safe que les cartes précédentes). Nouvelles
  chaînes traduites dans `locales/fr_FR.po`/`locales/en_GB.po`.
- **Audits internes et CAPA (clauses 9.2 et 10.2 ISO/IEC 27001:2022)** : deux nouveaux itemtypes.
  `PluginGrcmanagerAudit` porte le programme d'audit interne (titre, périmètre libre, catégories de
  risque couvertes, contrôles Annexe A couverts via un lien many-to-many
  `glpi_plugin_grcmanager_audits_controls`, auditeur, statut planifié/en cours/terminé/annulé, date
  planifiée/réalisée avec auto-renseignement de la date réalisée dès le passage au statut
  « Terminé », conclusion). `PluginGrcmanagerNonconformity` porte le cycle constat -> action
  corrective/préventive -> clôture que la clause 10.2 impose : rattachement à un audit, sévérité
  (mineure/majeure/critique), cause racine, action corrective, action préventive, responsable,
  échéance, statut ouverte/en traitement/clôturée/vérifiée, date de vérification de clôture
  (auto-renseignée à la date du jour dès le passage au statut « Vérifiée »). Validation serveur
  réelle : impossible de clôturer ou vérifier une non-conformité sans action corrective renseignée
  (vérifiée en direct). Nouvelle tâche Cron quotidienne
  (`PluginGrcmanagerNonconformity::cronOverduecapa()`) qui notifie le responsable de chaque action
  corrective/préventive dont l'échéance est dépassée et qui n'est ni clôturée ni vérifiée
  (`OverdueCapaService`, `inc/notificationtargetnonconformity.class.php`), même mécanisme de
  notification GLPI native que les rappels de revue de risque du Sprint 2. Listes filtrables avec
  badges colorés traduits et lien cliquable (`front/audit.php`, `front/nonconformity.php`),
  formulaires dédiés, vue « Mes actions correctives/préventives ». 3 nouvelles cartes de tableau de
  bord : non-conformités ouvertes, actions correctives/préventives en retard, audits internes par
  statut (`DashboardCardService`, même signature accumulateur-safe que les cartes précédentes).
  Nouvelles chaînes traduites dans `locales/fr_FR.po`/`locales/en_GB.po`.
- **Déclaration d'applicabilité (SoA, clause 6.1.3 ISO/IEC 27001:2022)** : nouvel itemtype
  `PluginGrcmanagerControl` portant les 93 mesures réelles de l'Annexe A (37 organisationnelles,
  8 humaines, 14 physiques, 34 technologiques), seedées de façon idempotente à l'installation
  (`Installer::seedControls()`, comptées en direct sur une instance GLPI 11 réelle : 93/93). Pour
  chaque mesure : applicabilité (applicable/non applicable/partiellement applicable), justification
  obligatoire dès que la mesure n'est pas pleinement applicable (validation serveur réelle,
  vérifiée en direct), statut de mise en œuvre (non démarré/en cours/mis en œuvre/vérifié), lien
  many-to-many vers le registre de risques (`glpi_plugin_grcmanager_controls_risks`). Liste
  filtrable par thème/applicabilité/statut avec badges colorés traduits et lien cliquable
  (`front/control.php`), formulaire dédié (`front/control.form.php`). 3 nouvelles cartes de
  tableau de bord : contrôles SoA revus, par applicabilité, par état de mise en œuvre
  (`DashboardCardService`, même signature accumulateur-safe que les cartes du registre de
  risques). 93 nouvelles chaînes traduites (thèmes, statuts, et l'intitulé officiel de chacune des
  93 mesures) dans `locales/fr_FR.po`/`locales/en_GB.po`.
- **Matrice de risque administrable** (`front/config.php`, onglet unique pour l'instant) : la
  grille probabilité x impact utilisée par `RiskScoringService` pour calculer `risk_level` n'est
  plus codée en dur, elle est éditable depuis l'interface GLPI (5x4 menus déroulants colorés) et
  stockée dans `glpi_plugin_grcmanager_riskmatrixconfig`, sur le modèle du plugin jumeau
  glpi-vulnerability-manager. Les valeurs par défaut reproduisent exactement la matrice fixe du
  Sprint 1 : aucun changement pour les installations existantes tant qu'un administrateur ne
  modifie pas la grille (voir `RiskMatrixDefaults`).
- **Filtres de liste réellement fonctionnels** sur catégorie, probabilité, impact, niveau de
  risque, traitement et statut (`PluginGrcmanagerRisk::getSpecificValueToSelect()`) : le Sprint 1
  affichait des badges traduits mais ne permettait de filtrer qu'en tapant la valeur brute non
  traduite dans une case de texte ; ces six colonnes affichent maintenant un vrai menu déroulant
  traduit dans le formulaire de recherche.
- **Vue « Mes risques »** sur la liste (`front/risk.php`) : lien direct vers les risques dont
  l'utilisateur connecté est propriétaire.
- **Rappels de date de revue** : nouvelle tâche Cron GLPI (`PluginGrcmanagerRisk::cronReviewreminder()`,
  quotidienne) qui identifie les risques dont la date de revue est dépassée ou approche (30 jours)
  et déclenche une notification GLPI native (`review_due`) au propriétaire du risque
  (`ReviewReminderService`, `inc/notificationtargetrisk.class.php`), en plus de la carte de
  tableau de bord « Risques en attente de revue » déjà livrée au Sprint 1.

### Fixed

- `front/risk.php` transmettait un tableau de paramètres vide à `Search::showList()`, ignorant
  silencieusement tout critère de recherche présent dans l'URL (tri, pagination, filtres), corrigé
  en fusionnant `$_GET` via `Glpi\Search\Input\QueryBuilder::manageParams()`, le même mécanisme que
  `Search::show()` utilise pour les écrans natifs de GLPI.

## [0.1.0] - 2026-08-24

### Added

- **Squelette de plugin GLPI 11 installable** : `setup.php` (version, compatibilité GLPI 11.0.0 à
  11.99.99, PHP 8.1 minimum), `hook.php`, droit GLPI dédié `plugin_grcmanager` (attribué en full
  au profil Super-Admin à l'installation), désinstallation propre.
- **Registre de risques génériques** (`PluginGrcmanagerRisk`, clause 6.1.2/8.2 ISO 27001) :
  titre, description, catégorie (humain/processus/physique/tiers/technique), probabilité, impact,
  niveau de risque calculé automatiquement (`RiskScoringService`, matrice probabilité x impact
  4x4), décision de traitement (accepter/mitiger/transférer/éviter), propriétaire, justification,
  date de revue, statut. Liste avec badges colorés traduits et lien cliquable vers le formulaire
  dès le premier commit (leçon déjà apprise sur le plugin jumeau glpi-vulnerability-manager,
  appliquée ici sans attendre une correction après coup).
- **Entrée de menu** sous Outils, et **4 cartes de tableau de bord** (`Hooks::DASHBOARD_CARDS`,
  signature accumulateur-safe `?array $cards = null` dès le premier commit, même leçon apprise sur
  le plugin jumeau) : risques ouverts, risques par niveau, risques par catégorie, risques en
  attente de revue.
- **CI complète** : syntaxe PHP, PHPStan, PHP_CodeSniffer, PHPUnit, Semgrep, Gitleaks (CLI, scan de
  plage de commits), Trivy, installation réelle sur GLPI 11.0.8 (Docker), hygiène du dépôt.
- **Workflows d'auto-tag et de release** (déclenchement, construction d'archive, validation
  d'installation réelle, publication GitHub Release) : préparés mais non exécutés pour de vrai à
  ce stade (Sprint 1, pas de v1.0).
- Documentation bilingue (FR/EN) : README, plan de développement par sprints, roadmap publique.
