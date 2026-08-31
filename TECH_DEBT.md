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
- ~~**`severity` fait à la fois office de sévérité et de catégorie de non-conformité.** La demande
  initiale (« severity/category ») a été résolue par une seule échelle ordinale
  (mineure/majeure/critique) plutôt que deux énumérations distinctes (par exemple séparer
  « non-conformité » et « observation » au sens strict ISO 27001), pour rester simple et cohérent
  avec l'échelle probabilité x impact déjà utilisée par le registre de risques. À réévaluer si un
  besoin réel de distinguer les deux axes apparaît.~~ **Résolu (issue #27)** : nouveau champ
  `finding_type` (non-conformité/observation, vocabulaire ISO 19011) ajouté en tant qu'axe
  indépendant de `severity`, avec CAPA obligatoire uniquement pour une vraie non-conformité (voir
  `PluginGrcmanagerNonconformity::getFindingTypes()`,
  `GlpiPlugin\Grcmanager\Services\Capa\CapaRequirementService` et `src/Install/Installer.php` pour
  la migration).
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

## Sprint 7 (Tableaux de bord, consolidation)

- **Tableau de bord seedé écrasé au réinstall si un administrateur l'a personnalisé.**
  `DefaultDashboardService::seed()` fait un upsert sur une clé fixe
  (`grcmanager-isms-overview`) à chaque `plugin:install`/`plugin:install --force` : un
  administrateur qui aurait déplacé/retiré des cartes depuis l'écran natif Tableaux de bord verrait
  ses changements écrasés par une réinstallation ou une mise à niveau ultérieure du plugin. Assumé
  pour ce sprint (comportement identique à n'importe quelle configuration par défaut réinitialisée
  à chaque mise à jour) plutôt qu'une détection « déjà personnalisé, ne pas toucher » plus
  complexe ; à réévaluer si ce comportement surprend un administrateur en conditions réelles.
- **Nom du tableau de bord seedé non re-traduit par langue de session.** `__(...)` n'est évalué
  qu'une fois, au moment de l'installation : le nom stocké dans `glpi_dashboards_dashboards.name`
  reste figé dans la langue active à ce moment-là pour tous les utilisateurs ensuite, contrairement
  aux libellés des 15 cartes elles-mêmes (résolus à chaque affichage). Même limite que les autres
  valeurs persistées en base par ce plugin plutôt que résolues dynamiquement à l'affichage (ex.
  `severity`/`status` bruts stockés en base, traduits uniquement par les badges de liste).
- **Aucune nouvelle carte de tableau de bord ajoutée à ce sprint.** L'inventaire des 15 cartes
  existantes n'a révélé ni carte cassée, ni doublon, ni lacune justifiant une carte « santé ISMS »
  consolidée supplémentaire : le nouveau tableau de bord par défaut (ci-dessus) répond au besoin
  de vue d'ensemble en réorganisant les cartes déjà posées, sans en ajouter une seizième
  redondante avec les bigNumbers déjà disponibles.

## Sprint 8 (Documentation et release v1.0.0)

- ~~**`docs/TUTORIAL.md` (27 captures) ne couvre que les Sprints 1-4.**~~ **Résolu après la
  publication de la v1.0.0** : le tutoriel compte désormais 42 captures, toutes réelles (rejouées
  sur l'instance réelle du plugin avec Playwright, exactement comme les 27 captures d'origine).
  Étape 7 (registre de risques fournisseurs/tiers, Sprint 5, lien vers un vrai `Supplier` GLPI),
  étape 8 (suivi d'une formation et de la réalisation d'un participant, Sprint 6), étape 9
  (enregistrement d'une revue de direction, Sprint 6, avec démonstration de l'auto-renseignement de
  `review_date`), étape 10 (tableau de bord ISMS seedé à l'installation, Sprint 7, capturé avec les
  données de démonstration natives GLPI explicitement désactivées pour montrer les vrais chiffres)
  et étape 11 (carte de suivi de version GitHub sur l'écran Configuration, Sprint 7) ont été
  ajoutées. Le texte d'introduction et l'Étape 1 ont été corrigés : le plugin ajoute bien « sept
  écrans » au menu Outils, plus la mention du registre de risques fournisseurs fantôme qui ne
  correspondait à aucun écran réel a été retirée.
## Lien registre de risques <-> actifs GLPI/CMDB (issue #25)

- **Lien risque <-> actif en accès direct `$DB`, pas en itemtype `CommonDBRelation`.** Même
  simplification assumée pour tous les autres liens de ce plugin depuis le Sprint 3 (voir
  ci-dessus) : `glpi_plugin_grcmanager_risks_items` est géré par de simples méthodes statiques sur
  `PluginGrcmanagerRisk` (`getLinkedAssets()`/`syncLinkedAssets()`/`getRisksLinkedToItem()`), pas par
  une vraie classe de liaison GLPI. Suffisant pour un nombre d'actifs liés par risque toujours
  faible en pratique (l'issue elle-même ne décrit que des exemples à un ou quelques actifs) ; à
  reconsidérer si un besoin réel de journalisation GLPI native (`Log`) sur ce lien apparaît, ou si
  un futur besoin de recherche/filtrage riche sur les actifs liés se présente (voir aussi le point
  "aucune colonne de recherche" ci-dessous).
- **Un multi-select par itemtype liable, jamais un unique widget polymorphe
  `Dropdown::showSelectItemFromItemtypes()`.** Ce widget natif GLPI existe précisément pour ce cas
  d'usage (choisir un itemtype puis un item de ce type), mais son fonctionnement réel dépend d'un
  second appel Ajax déclenché en JS au changement du premier `<select>` — irait à l'encontre du
  choix déjà assumé pour `showForm()` depuis le Sprint 1 ("HTML/PHP manuel, pas de logique JS
  avancée", voir ci-dessus). Un multi-select par itemtype (même widget `Dropdown::showFromArray(...,
  ['multiple' => true])` que `PluginGrcmanagerControl::showForm()` pour son propre lien vers les
  risques) reste cohérent avec ce choix, au prix de lister tous les actifs d'un type sans
  pagination ni recherche - assumé pour ce qui est explicitement une v1 de la fonctionnalité,
  chaque itemtype sans aucun enregistrement dans l'instance GLPI n'affichant simplement aucune
  ligne. À réévaluer si le volume réel d'actifs par type dans une instance de production rend cette
  liste impraticable (voir aussi la limitation similaire déjà assumée pour
  `PluginGrcmanagerControl::showForm()`/tous les risques du registre).
- **Onglet retour "Risques" posé sur une liste FIXE d'itemtypes (`LinkableItemtypes::
  DEFAULT_ITEMTYPES`), pas sur le résultat dynamique de `PluginGrcmanagerRisk::
  getLinkableItemtypes()`.** Un actif personnalisé actif reste bien liable depuis le formulaire du
  risque (`getLinkableItemtypes()` l'inclut), mais ne reçoit pas l'onglet "Risques" sur sa propre
  fiche : `Plugin::registerClass()`/`addtabon` s'exécute au chargement du plugin (listener
  `InitializePlugins`), avant que GLPI ne charge les définitions d'actifs personnalisés en mémoire
  (listener `CustomObjectsBoot`, plus tardif) - même limitation de séquencement déjà documentée par
  le plugin jumeau assetsign-glpi pour sa propre `Config::getAllManageableItemtypes()`. Assumé pour
  cette version plutôt que de risquer un enregistrement d'onglet dynamique non validé en direct.
- **Aucune colonne "actifs liés" dans `PluginGrcmanagerRisk::rawSearchOptions()`.** Une relation à
  plusieurs actifs polymorphes (many-to-many, cible variable selon la ligne) ne correspond à aucun
  des `datatype` natifs du moteur de recherche GLPI (`dropdown`, `itemlink`...), tous conçus pour
  une jointure vers UNE table cible fixe (voir par exemple `Document_Item`, dont le compte de
  documents liés n'est lui-même pas non plus exposé comme colonne de recherche générique dans le
  cœur GLPI). Une fiche de détail avec le lien fonctionnel (formulaire + onglet retour ci-dessus)
  est le livrable de cette issue ; une colonne de recherche resterait un besoin à traiter sur mesure
  (ex. sous-requête `COUNT(*)` par risque, sans filtre par actif précis) si un besoin réel de tri/
  filtre sur ce critère apparaît en usage réel.

## Classification C/I/D des actifs (issue #26)

- **Table `glpi_plugin_grcmanager_assetclassifications` (pas `..._asset_classifications`).**
  L'issue suggérait un nom avec underscore ; la dérivation réellement utilisée partout ailleurs
  dans ce plugin (suffixe de classe en minuscules, sans underscore ajouté à la frontière camelCase,
  voir `src/Install/Installer.php`) donne `assetclassifications`, cohérente avec `assetclassifications`,
  `supplierrisks`, `managementreviews`... Choisi délibérément pour ne pas introduire une seule table
  au nom incohérent avec toutes les autres du même plugin.
- **Aucun nettoyage automatique quand l'actif classifié lui-même est supprimé.** Une classification
  reste en base avec un couple itemtype/items_id orphelin si le `Computer` (ou autre actif) qu'elle
  décrit est supprimé côté GLPI : même limitation déjà assumée pour
  `glpi_plugin_grcmanager_risks_items` (issue #25, voir ci-dessus), qui ne se nettoie elle non plus
  que lorsque c'est le RISQUE qui est purgé, jamais quand c'est l'actif lié qui disparaît. Un
  registre indépendant du risque qui commettrait la même impasse pour son propre côté « actif » n'a
  pas semblé justifier un mécanisme de nettoyage plus riche pour cette première version ; à
  reconsidérer si un besoin réel de cohérence stricte apparaît (ex. hook générique sur la
  suppression de tout itemtype liable, plutôt qu'un cas par cas par registre).
- **Droit d'édition de l'onglet vérifié uniquement via `UPDATE` sur le droit plat
  `plugin_grcmanager`**, jamais `CREATE` séparément pour le cas "cet actif n'a encore aucune
  ligne de classification" : `displayTabContentForItem()` n'affiche le formulaire d'édition que
  si l'utilisateur a `UPDATE`, alors que `front/assetclassification.form.php` vérifie bien `CREATE`
  pour un premier enregistrement et `UPDATE` pour une modification. Un profil qui aurait `CREATE`
  sans `UPDATE` (combinaison jamais utilisée en pratique dans ce plugin, qui accorde toujours le
  droit plat en bloc, voir `ProfileRight::updateProfileRights()` dans `src/Install/Installer.php`)
  ne verrait donc pas le formulaire alors qu'il pourrait légitimement classifier un actif vierge.
  Assumé pour rester simple (une seule condition d'affichage) plutôt que de dupliquer la logique
  add-vs-update du contrôleur dans l'onglet lui-même.
- **Onglet posé sur la même liste FIXE d'itemtypes que l'issue #25**
  (`LinkableItemtypes::DEFAULT_ITEMTYPES`), pas sur `PluginGrcmanagerRisk::getLinkableItemtypes()`
  (qui ajoute aussi les actifs personnalisés actifs) : exactement la même limitation de
  séquencement `InitializePlugins`/`CustomObjectsBoot` déjà documentée pour l'onglet "Risques" de
  l'issue #25 ci-dessus, voir son propre point pour le détail complet. Un actif personnalisé actif
  reste malgré tout classifiable *si* un risque le lie déjà (le multi-select de
  `PluginGrcmanagerRisk::showForm()` l'inclut), mais ne reçoit pas son propre onglet "Classification
  C/I/D" sur sa fiche.
- **La suggestion douce sur le champ Impact (`hasHighClassificationAmongLinkedAssets()`) refait une
  requête par actif lié**, pas une seule requête groupée : cohérent avec `getLinkedAssets()`
  lui-même qui fait déjà une requête par actif pour résoudre son nom, et avec la limitation déjà
  assumée pour ce même formulaire depuis l'issue #25 ("un nombre d'actifs liés par risque toujours
  faible en pratique"). À revoir si ce nombre cesse d'être faible en usage réel.

## Registre des obligations légales, réglementaires et contractuelles (issue #30)

- **Lien optionnel vers un risque en colonne directe (`plugin_grcmanager_risks_id`), pas une table
  de liaison many-to-many.** Contrairement au lien contrôle <-> risque du Sprint 3
  (`glpi_plugin_grcmanager_controls_risks`, plusieurs risques par contrôle), l'issue #30 demande
  explicitement une cardinalité zéro-ou-un ("une obligation correspond au plus à une entrée de
  risque précise, si tant est qu'il y en ait une") : une colonne directe (même modèle que
  `users_id`/propriétaire sur chaque autre registre de ce plugin) est plus simple qu'une table de
  liaison dédiée pour cette cardinalité, et reste cohérente avec l'esprit "simples méthodes
  statiques, pas une vraie classe CommonDBRelation" déjà assumé pour tous les autres liens de ce
  plugin (voir Sprint 3 ci-dessus) - ici, pas même besoin d'une table du tout. Voir
  `GlpiPlugin\Grcmanager\Services\Compliance\ComplianceObligationRules::normalizeLinkedRiskId()`/
  `isLinkedToRisk()` pour la logique pure testée, et le docblock de
  `PluginGrcmanagerComplianceObligation` pour le raisonnement complet.
- **`ReviewReminderService` généralisé une seconde fois (après le Sprint 5) pour accepter un
  `$excludeCriteria` optionnel.** Le service appliquait jusqu'ici inconditionnellement
  `'status' => ['<>', 'closed']`, une colonne que `PluginGrcmanagerComplianceObligation` n'a pas
  (elle a `compliance_status`, qui grade la conformité, pas si l'obligation est encore suivie).
  Généralisé avec un paramètre de constructeur dont la valeur par défaut reproduit exactement
  l'ancien comportement figé (aucun changement pour `PluginGrcmanagerRisk`/
  `PluginGrcmanagerSupplierRisk`, qui ne passent jamais cet argument) ; l'obligation, elle, passe un
  tableau vide - même une obligation `compliant` reste due à sa date de revue. Ce fichier n'a
  toujours aucun test unitaire direct (dépendance runtime GLPI, voir `phpstan.neon.dist`) : la
  logique de fenêtre de rappel (30 jours, exclusion des dates nulles) qu'il applique est dupliquée
  intentionnellement et testée dans `ComplianceObligationRules::isReviewDue()`
  (`REMINDER_WINDOW_DAYS` doit rester synchronisé entre les deux fichiers si jamais modifié).
- **`seedReviewReminderNotification()` généralisé pour un contenu de notification à 2 lignes
  configurable.** Codait en dur "Catégorie"/"Niveau de risque" (`PluginGrcmanagerRisk`/
  `PluginGrcmanagerSupplierRisk`) ; l'obligation n'a ni catégorie ni niveau de risque
  (`type`/`compliance_status` à la place), d'où un nouveau paramètre `$detailLines` avec la même
  valeur par défaut que l'ancien comportement figé pour les deux registres de risques existants.
- **Aucun onglet retour sur `PluginGrcmanagerRisk`** contrairement au lien risque <-> actifs CMDB de
  l'issue #25 : une obligation n'est pas un itemtype "liable" au sens de `LinkableItemtypes`/
  `getLinkableItemtypes()` (ce ne sont pas des actifs CMDB), et le nombre d'obligations pouvant
  citer un même risque reste faible en pratique - consulter la fiche de l'obligation elle-même
  (qui affiche le lien vers le risque, voir `riskLink()`) a été jugé suffisant pour cette première
  version plutôt que d'ajouter un second onglet "Obligations" sur la fiche de chaque risque.
- **Aucun nettoyage automatique si le risque lié est supprimé.** `plugin_grcmanager_risks_id` reste
  en base avec un identifiant de risque orphelin si ce risque est purgé (ni erreur, ni lien affiché
  puisque `riskLink()` retourne une chaîne vide dès que `getFromDB()` échoue) - même limitation déjà
  assumée pour `glpi_plugin_grcmanager_risks_items` (issue #25) et
  `glpi_plugin_grcmanager_assetclassifications` (issue #26), voir leurs points respectifs ci-dessus.

## Objectifs ISMS et suivi de KPI dans le temps (issue #32)

- **Mesures manuelles, jamais auto-calculées depuis d'autres données du plugin.** Une mesure
  (`PluginGrcmanagerObjectiveMeasurement`) est toujours saisie à la main ("à cette date, nous en
  sommes à X"), jamais dérivée automatiquement d'un décompte réel ailleurs dans le plugin (ex. le
  nombre de non-conformités récurrentes pour un objectif qui viserait explicitement à les réduire).
  Assumé délibérément pour cette première version, même philosophie "une version minimale et
  testée vaut mieux qu'une version élaborée et non testée" que le reste de ce plugin (voir Sprint 2
  ci-dessus) : un calcul automatique demanderait de définir, objectif par objectif, QUELLE requête
  du plugin nourrit sa trajectoire, un mapping qui n'a pas de réponse générique évidente. Une
  évolution possible (non retenue ici) serait un futur champ optionnel "source de calcul" sur
  l'objectif, résolu par une nouvelle tâche Cron qui insérerait alors ses propres mesures.
- **`plugin_grcmanager_objectives_id` sur `PluginGrcmanagerObjectiveMeasurement` n'est pas une clé
  étrangère GLPI réelle**, même simplification déjà assumée pour
  `PluginGrcmanagerNonconformity.plugin_grcmanager_audits_id` (Sprint 4, voir ci-dessus) : pas de
  `ON DELETE CASCADE` natif si un objectif est supprimé, ses mesures resteraient orphelines en
  base. Un objectif n'ayant pas de bouton de suppression retiré côté formulaire (contrairement aux
  93 contrôles du Sprint 3), ce cas reste possible en pratique ; assumé pour cette première version
  plutôt que de complexifier `PluginGrcmanagerObjective::prepareInputForDelete()`/un hook de purge
  dédié, à reconsidérer si des mesures orphelines s'avèrent gênantes en usage réel.
- **Historique de mesures en simple tableau chronologique, pas de graphique.** L'issue elle-même
  suggère un "historique de mesures dans le temps" sans imposer de visualisation particulière ; un
  tableau simple montre une trajectoire de façon honnête sans introduire de nouvelle dépendance de
  rendu (bibliothèque de graphiques), cohérent avec le choix déjà assumé pour `showForm()` depuis
  le Sprint 1 (HTML/PHP manuel, pas de logique JS avancée). À réévaluer si un besoin réel de
  visualisation graphique apparaît en usage réel (le tableau de bord natif GLPI, lui, offre déjà
  des rendus `pie`/`bar`/`donut` pour la répartition PAR STATUT, voir
  `DashboardCardService::objectivesByStatus()`, mais pas pour une série temporelle par objectif).
- **Suppression d'une mesure sans confirmation serveur, uniquement une confirmation JS
  `confirm()`.** Même niveau de protection que les boutons purge/delete natifs de GLPI ailleurs
  dans ce plugin (voir Sprint 3, "pas de bouton de suppression dans l'écran, mais pas de blocage
  serveur non plus") : une requête POST forgée par un compte disposant du droit `plugin_grcmanager`
  pourrait encore supprimer une mesure sans passer par la boîte de dialogue JS. Assumé pour ce
  sprint, même niveau de risque que n'importe quel autre itemtype GLPI administrable de ce plugin.
- **Lien revue de direction <-> objectifs en accès direct `$DB`, pas en itemtype
  `CommonDBRelation`.** Même simplification assumée que tous les autres liens many-to-many de ce
  plugin depuis le Sprint 3 (voir ci-dessus) :
  `glpi_plugin_grcmanager_managementreviews_objectives` est géré par de simples méthodes statiques
  sur `PluginGrcmanagerManagementReview` (`getLinkedObjectives()`/`syncLinkedObjectives()`),
  suffisant pour un nombre d'objectifs discutés par revue toujours faible en pratique.
- **`.mo` régénérés sans `msgfmt`/gettext** (outil absent de l'environnement de développement
  utilisé pour cette issue) : compilés depuis les `.po` mis à jour avec un petit script Python
  interne (format binaire GNU MO standard, vérifié par relecture avec `gettext.GNUTranslations` de
  Python avant commit, y compris pour les entrées plurielles et les entrées déjà existantes). À
  recompiler avec le `msgfmt` réel du système au prochain changement de traduction si l'outil est
  disponible dans un environnement ultérieur, pour rester sur l'outillage standard de l'écosystème
  gettext plutôt qu'un compilateur maison, conservé ici uniquement parce qu'aucune alternative
  n'était disponible.

- **`docs/design/` ne contient qu'un seul document (`DEVELOPMENT_PLAN.md`), pas d'ADR dédiées.**
  Évalué lors de la même revue : il n'existe pas de série de fichiers "Architecture Decision
  Record" formels comme le ferait un projet plus mature. Jugé suffisant pour l'instant (pas
  bloquant pour la v1.0.0) car les décisions d'architecture significatives sont déjà documentées,
  juste dispersées différemment : `DEVELOPMENT_PLAN.md` donne la vue d'ensemble (répartition
  `inc/`/`src/Services/`/`src/Install/`/`front/`), et chaque choix de conception concret avec sa
  justification vit dans ce fichier `TECH_DEBT.md`, sprint par sprint, ce qui couvre en pratique le
  même besoin (« pourquoi tel choix a été fait, à quoi faire attention si on le change ») qu'une
  ADR. À réévaluer si le projet gagne des contributeurs externes qui auraient besoin d'un point
  d'entrée unique de type `ARCHITECTURE.md` plutôt que de devoir lire tout `TECH_DEBT.md`.
