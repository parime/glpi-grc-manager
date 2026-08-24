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
