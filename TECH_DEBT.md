# Dette technique connue

Journal des limites connues et compromis assumés, tenu à jour à chaque sprint (voir
[docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md) et
[DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md)).

## Sprint 1 (Infrastructure plugin)

- **Matrice de risque fixe, non administrable.** `RiskScoringService` (voir
  `src/Services/Risk/RiskScoringService.php`) utilise une matrice probabilité x impact 4x4 codée
  en dur. Le plugin jumeau glpi-vulnerability-manager (même auteur) a une matrice administrable
  depuis l'interface GLPI ; la même évolution est planifiée pour ce plugin en Sprint 2, voir
  ROADMAP.md.
- **`showForm()` en HTML/PHP manuel, pas en Twig.** Choix délibéré pour ce sprint (simplicité,
  moins de surface à valider en conditions réelles) plutôt qu'un template Twig comme le fait le
  plugin jumeau glpi-vulnerability-manager pour ses propres formulaires. À réévaluer si les
  formulaires futurs (SoA, audits) deviennent significativement plus complexes.
- **Pas de tâche Cron.** Aucun traitement asynchrone n'est nécessaire pour un registre de risques
  saisi manuellement ; sera réévalué si un sprint futur ajoute des rappels automatiques de date de
  revue.
