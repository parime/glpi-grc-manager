# Dette technique connue

Journal des limites connues et compromis assumés, tenu à jour à chaque sprint (voir
[docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md) et
[DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md)).

## Sprint 1 (Infrastructure plugin)

- ~~**Matrice de risque fixe, non administrable.**~~ **Résolu au Sprint 2**, voir
  `RiskMatrixConfig`/`front/config.php` ci-dessous.
- **`showForm()` en HTML/PHP manuel, pas en Twig.** Choix délibéré pour ce sprint (simplicité,
  moins de surface à valider en conditions réelles) plutôt qu'un template Twig comme le fait le
  plugin jumeau glpi-vulnerability-manager pour ses propres formulaires. À réévaluer si les
  formulaires futurs (SoA, audits) deviennent significativement plus complexes. Le nouvel écran de
  configuration du Sprint 2 (`front/config.php`), lui, est en Twig dès le départ : un formulaire
  de grille 5x4 s'y prêtait mal en HTML manuel.
- ~~**Pas de tâche Cron.**~~ **Résolu au Sprint 2**, voir `PluginGrcmanagerRisk::cronReviewreminder()`
  ci-dessous.

## Sprint 2 (Registre de risques génériques v2)

- **Rappel de revue sans déduplication.** `ReviewReminderService::notify()` relance
  `NotificationEvent::raiseEvent()` pour chaque risque encore dû à chaque exécution du Cron
  (quotidienne par défaut) : un risque resté en retard plusieurs jours sans être traité génère une
  notification à chaque passage, pas une seule fois. Assumé pour ce sprint (« une version minimale
  et testée vaut mieux qu'une version élaborée et non testée ») : un administrateur maîtrise déjà
  la fréquence via Configuration > Actions automatiques. Une évolution possible (non retenue ici)
  serait un horodatage de dernier rappel par risque, sur le modèle du `glpi_alerts` que GLPI core
  utilise pour ses propres alertes de certificats (voir `Certificate::cronCertificate()`).
- **Écran de configuration à un seul onglet.** `front/config.php`/`config_form.html.twig` ne
  gèrent que la matrice de risque pour l'instant, contrairement à l'écran multi-onglets du plugin
  jumeau glpi-vulnerability-manager (11 écrans différents). À réévaluer en onglets si un sprint
  futur ajoute d'autres réglages ici plutôt que de multiplier les écrans de configuration.

## Sprint 3 (Déclaration d'Applicabilité / SoA)

- **Lien contrôle <-> risque en accès direct `$DB`, pas en itemtype `CommonDBRelation`.**
  `glpi_plugin_grcmanager_controls_risks` est géré par de simples méthodes statiques sur
  `PluginGrcmanagerControl` (`getLinkedRisks()`/`syncLinkedRisks()`), pas par une vraie classe
  `PluginGrcmanagerControl_Risk` sur le modèle `Left_Right` de GLPI core (ex. `Document_Item`).
  Suffisant pour un simple multi-select dans `showForm()` avec un nombre de risques par contrôle
  toujours faible ; à reconsidérer si un sprint futur a besoin de journalisation GLPI native
  (`Log`) sur ce lien, ou d'un widget de recherche/relation plus riche.
- **Pas de bouton de suppression dans l'écran, mais pas de blocage serveur non plus.**
  `showForm()` masque le bouton supprimer/purger (`candel => false`, les 93 contrôles sont un
  catalogue fixe seedé à l'installation), mais `front/control.form.php` conserve les branches
  delete/purge du contrôleur générique (même forme que `front/risk.form.php`) : une requête POST
  forgée par un compte disposant du droit `plugin_grcmanager` pourrait encore supprimer un
  contrôle. Assumé pour ce sprint (même niveau de risque que n'importe quel autre itemtype GLPI
  administrable), à revisiter seulement si un besoin réel de blocage serveur strict apparaît.
- **Justification obligatoire vérifiée côté serveur uniquement, pas de bascule JS côté client.**
  `prepareInputForAdd()`/`prepareInputForUpdate()` bloque bien l'enregistrement (message d'erreur
  réel, vérifié en direct) si `applicability` != "yes" et que `justification` est vide, mais le
  champ ne se marque pas visuellement obligatoire tant que l'utilisateur n'a pas essayé
  d'enregistrer (pas de JS conditionnel sur le `<select>` applicability). Cohérent avec le choix
  déjà fait pour `showForm()` du registre de risques (HTML/PHP manuel, pas de logique JS
  avancée) ; à réévaluer si un formulaire Twig plus riche est introduit pour ce module.

## Sprint 4 (Audits internes et CAPA)

- **Lien audit <-> contrôle en accès direct `$DB`, pas en itemtype `CommonDBRelation`.** Même
  simplification assumée qu'au Sprint 3 pour le lien contrôle <-> risque (voir ci-dessus) :
  `glpi_plugin_grcmanager_audits_controls` est géré par de simples méthodes statiques sur
  `PluginGrcmanagerAudit` (`getLinkedControls()`/`syncLinkedControls()`), suffisant pour un
  multi-select dans `showForm()` avec un nombre de contrôles par audit toujours faible.
- **`plugin_grcmanager_audits_id` sur `PluginGrcmanagerNonconformity` n'est pas une clé étrangère
  GLPI réelle.** Pas de `ON DELETE CASCADE` natif si un audit est supprimé : ses non-conformités
  restent en base avec un identifiant d'audit orphelin (affiché comme un lien vide par
  `PluginGrcmanagerNonconformity::getAuditTitle()`, pas une erreur, mais pas nettoyé non plus). La
  colonne n'utilise pas non plus un vrai `datatype => 'dropdown'` GLPI avec join natif
  (contrairement à la colonne « Auditeur »/« Responsable » qui, elle, pointe vers `glpi_users` en
  dropdown natif) : elle est en `datatype => 'specific'`, résolue par une requête directe
  (`getAuditTitle()`), filtrable uniquement par identifiant numérique brut, pas par titre d'audit.
  Assumé pour une première version plutôt que de risquer un join GLPI mal configuré non validé en
  direct ; à reconsidérer si le nombre d'audits croît au point de rendre le filtrage par ID
  impraticable.
- **`severity` fait à la fois office de sévérité et de catégorie de non-conformité.** La demande
  initiale (« severity/category ») a été résolue par une seule échelle ordinale
  (mineure/majeure/critique) plutôt que deux énumérations distinctes (par exemple séparer
  « non-conformité » et « observation » au sens strict ISO 27001), pour rester simple et cohérent
  avec l'échelle probabilité x impact déjà utilisée par le registre de risques. À réévaluer si un
  besoin réel de distinguer les deux axes apparaît.
- **Rappel de CAPA en retard sans déduplication**, même limite assumée que
  `ReviewReminderService::notify()` au Sprint 2 (voir ci-dessus) : une action encore en retard
  plusieurs jours après son échéance génère une notification à chaque exécution quotidienne de la
  tâche Cron, pas une seule fois.
- **Catégories de risque d'un audit stockées en liste séparée par des virgules**, pas en table de
  liaison : `risk_categories` est un `varchar` sur `PluginGrcmanagerAudit`, résolu vers/depuis
  `PluginGrcmanagerRisk::getCategories()` par simple `explode()`/`implode()`
  (`PluginGrcmanagerAudit::splitRiskCategories()`). Cohérent avec le fait que les catégories de
  risque ne sont pas des entités GLPI mais une énumération fixe, comme pour les autres colonnes
  enum de ce plugin ; le filtre de liste associé (`getSpecificValueToSelect()`) ne permet de
  filtrer que sur une seule catégorie à la fois (recherche « contient »), pas une combinaison
  ET/OU de plusieurs catégories.

## Sprint 5 (Risques fournisseurs/tiers)

- **`showForm()` en HTML/PHP manuel, pas en Twig**, même choix assumé que tous les formulaires
  précédents de ce plugin (voir Sprint 1 ci-dessus) : pas de bascule JS conditionnelle sur le champ
  Fournisseur bien qu'il soit obligatoire côté serveur (`PluginGrcmanagerSupplierRisk::
  validateSupplierAndComputeRiskLevel()`), seulement une aide textuelle (`form-hint`) et un message
  d'erreur réel si l'enregistrement est tenté sans fournisseur, vérifié en direct.
- **Pas de déduplication du rappel de revue**, même limite assumée qu'au Sprint 2 pour le registre
  générique (voir `ReviewReminderService` ci-dessus, désormais partagée par les deux tâches Cron
  `PluginGrcmanagerRisk::cronReviewreminder()` et `PluginGrcmanagerSupplierRisk::cronReviewreminder()`) :
  un risque fournisseur resté en retard plusieurs jours génère une notification à chaque exécution
  quotidienne, pas une seule fois.
- **Le champ `category` reste l'énumération générique du registre principal**
  (people/process/physical/third_party/technical), y compris sur ce registre dédié aux tiers, où
  `third_party` est donc à la fois le nom du registre et une valeur possible du champ (une entrée
  ici peut tout autant être catégorisée "technique" ou "processus" que "tiers/fournisseur" au sens
  strict) : assumé pour respecter la consigne « mêmes champs catégorie/probabilité/impact que
  PluginGrcmanagerRisk » plutôt que d'introduire une seconde énumération plus étroite ; à
  réévaluer si l'usage réel montre que la valeur "third_party" du champ catégorie n'apporte plus
  d'information utile sur ce registre spécifique.
- **`RiskAssessmentTrait` extrait au Sprint 5** (`src/Traits/RiskAssessmentTrait.php`) : les
  énumérations catégorie/probabilité/impact/traitement/statut et le calcul
  `computed_score`/`risk_level` étaient dupliqués mot pour mot entre `PluginGrcmanagerRisk` (Sprint
  1-2) et le nouveau `PluginGrcmanagerSupplierRisk`. Regroupés dans un trait plutôt que laissés
  dupliqués, pour que la matrice de notation ne puisse jamais diverger entre les deux registres.
  Non couvert par PHPStan (`phpstan.neon.dist`) comme le reste du code dépendant du runtime GLPI
  (`RiskMatrixConfig::load()`, `Dropdown`, `__()`), même raison que
  `src/Services/Risk/RiskMatrixConfig.php`.

## Sprint 6 (Formations et revues de direction)

- **Liens participants/participants-formation et participants/revue de direction en accès direct
  `$DB`, pas en itemtype `CommonDBRelation`**, même simplification assumée qu'aux Sprints 3-4 pour
  les liens contrôle <-> risque et audit <-> contrôle (voir ci-dessus) :
  `glpi_plugin_grcmanager_trainings_users` et `glpi_plugin_grcmanager_managementreviews_users` sont
  gérées par de simples méthodes statiques (`PluginGrcmanagerTraining::syncParticipants()`/
  `getParticipants()`, `PluginGrcmanagerManagementReview::syncAttendees()`/`getAttendees()`), pas
  par une vraie classe de liaison GLPI. Suffisant pour un multi-select dans `showForm()` avec un
  nombre de participants toujours faible ; à reconsidérer si un besoin réel de journalisation GLPI
  native (`Log`) sur ces liens apparaît.
- **Suppression d'un participant = perte de son historique de réalisation.**
  `PluginGrcmanagerTraining::syncParticipants()` retire la ligne
  `glpi_plugin_grcmanager_trainings_users` correspondante dès qu'un participant est désélectionné du
  multi-select puis la sauvegarde effectuée (supprimer-puis-réinsérer, même approche que
  `PluginGrcmanagerAudit::syncLinkedControls()`) : son statut/date de réalisation précédent n'est
  conservé nulle part. Assumé pour ce sprint plutôt qu'une table d'historique séparée ; à
  réévaluer si un besoin réel de conserver une trace même après retrait d'un participant apparaît.
- **Rappel de renouvellement de formation sans déduplication**, même limite assumée que
  `ReviewReminderService::notify()` au Sprint 2 et `OverdueCapaService` au Sprint 4 (voir
  ci-dessus) : un participant resté en retard de renouvellement plusieurs jours génère une
  notification à chaque exécution quotidienne de la tâche Cron
  (`PluginGrcmanagerTraining::cronRenewaldue()`), pas une seule fois.
- **`PluginGrcmanagerManagementReview` n'a volontairement ni notification GLPI native ni tâche
  Cron dédiée**, contrairement à `PluginGrcmanagerTraining` (rappel de renouvellement) et à tous
  les autres itemtypes de ce plugin ayant une échéance (revue de risque, CAPA en retard) : une
  revue de direction n'a pas de date d'échéance récurrente à surveiller au sens où l'entend ce
  plugin (`review_date` est une date de tenue, pas une date limite dépassable), donc rien à
  notifier automatiquement. Une évolution possible (non retenue ici) serait un rappel de
  planification si aucune revue n'a été enregistrée depuis un intervalle donné, sur le modèle des
  rappels de revue de risque.
- **Décisions et actions d'une revue de direction en texte libre, sans lien fort vers le mécanisme
  CAPA existant** (`PluginGrcmanagerNonconformity`). Assumé délibérément (voir le docblock de
  `inc/managementreview.class.php`) : une décision de revue de direction n'est pas toujours une
  action corrective liée à un audit (elle peut être une approbation budgétaire, un changement de
  politique, une acceptation de risque...), forcer chaque décision dans le flux CAPA
  dénaturerait ce que demande réellement la clause 9.3. À réévaluer si un besoin réel de suivi
  structuré (responsable, échéance, statut) par décision individuelle apparaît.
- **`showForm()` en HTML/PHP manuel, pas en Twig**, même choix assumé que tous les formulaires
  précédents de ce plugin (voir Sprint 1 ci-dessus).
