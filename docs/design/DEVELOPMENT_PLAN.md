# Plan de développement

Statut : Sprint 2 terminé. Ce document découpe la Version 1.0 (voir [ROADMAP.md](../../ROADMAP.md))
en itérations livrables, et liste les risques techniques identifiés. Il est mis à jour à chaque
fin de sprint.

## Méthode

Cycle appliqué à chaque sprint : Analyse → Conception → Développement → Tests → Revue de code →
Documentation → Validation → intégration dans `dev`. Aucune étape n'est sautée (voir
[CONTRIBUTING.md](../../CONTRIBUTING.md) et [DEFINITION_OF_DONE.md](../../DEFINITION_OF_DONE.md)).
Développement en petites itérations : jamais l'ensemble du plugin en une fois. Architecture reprise
du plugin jumeau [glpi-vulnerability-manager](https://github.com/parime/glpi-vulnerability-manager)
(même auteur) : `setup.php`/`hook.php` en racine, classes GLPI legacy sous `inc/`, logique
pure-PHP testable sous `src/Services/`, installeur sous `src/Install/`, écrans sous `front/`.

## Découpage Version 1.0

| Sprint | Statut | Objectif | Livrables clés |
|---|---|---|---|
| **1. Infrastructure plugin** | ✅ Terminé | Squelette installable | `setup.php`, `hook.php`, migration initiale (`src/Install/Installer.php`), droit GLPI dédié (`plugin_grcmanager`), vérification version GLPI/PHP, désinstallation propre, premier registre de risques génériques (`PluginGrcmanagerRisk`) fonctionnel de bout en bout (liste avec badges colorés traduits et lien cliquable, formulaire, calcul automatique du niveau de risque via `RiskScoringService`) ; **validé de bout en bout contre GLPI 11 réel** |
| **2. Registre de risques génériques (v2)** | ✅ Terminé | Matrice administrable | Matrice probabilité x impact configurable depuis l'interface GLPI (`front/config.php`, `RiskMatrixConfig`, au lieu de la matrice fixe du Sprint 1, voir `TECH_DEBT.md`), filtres réellement fonctionnels par catégorie/probabilité/impact/niveau/traitement/statut et vue « Mes risques » par propriétaire, rappels de date de revue (tâche Cron `cronReviewreminder()` + notification GLPI native) ; **validé de bout en bout contre GLPI 11 réel** |
| **3. Déclaration d'Applicabilité (SoA)** | ⏳ À venir | Clause 6.1.3 | Modèle de données des 93 contrôles Annexe A ISO 27001:2022, statut d'applicabilité par contrôle, justification, lien vers les risques traités |
| **4. Audits internes et CAPA** | ⏳ À venir | Programme d'audit | Programme d'audit interne, non-conformités, actions correctives et préventives (CAPA), suivi de clôture |
| **5. Risques fournisseurs/tiers** | ⏳ À venir | Registre dédié | Registre de risques fournisseurs/tiers, avec les mêmes mécanismes d'acceptation/traitement que le registre générique |
| **6. Formations et revues de direction** | ⏳ À venir | Suivi organisationnel | Suivi des formations de sensibilisation à la sécurité, enregistrement des revues de direction |
| **7. Dashboards** | ⏳ À venir | Restitution | Intégration au framework Dashboard natif de GLPI (`Hooks::DASHBOARD_CARDS`, cartes déjà posées au Sprint 1 : risques ouverts, par niveau, par catégorie, en attente de revue) |
| **8. Documentation et release** | ⏳ À venir | v1.0.0 | Documentation utilisateur/développeur complète, CI/CD verte, changelog, tag signé |

Chaque sprint se termine par une revue par rapport à la
[Definition of Done](../../DEFINITION_OF_DONE.md) avant de passer au suivant. Le contenu détaillé
(tâches, estimation) de chaque sprint est géré via les issues et milestones GitHub, pas dupliqué
dans ce document.

## Environnement de développement et tests

- Instance GLPI 11 réelle (Docker) pour toute validation, jamais une simple relecture de code.
- Niveaux de tests : unitaires (`tests/Unit`, logique pure sous `src/Compatibility/` et
  `src/Services/Risk/`), installation réelle sur GLPI (job CI dédié).
- CI GitHub Actions : syntaxe PHP, PHPStan, PHP_CodeSniffer, PHPUnit, Semgrep, Gitleaks, Trivy,
  installation réelle sur GLPI (Docker) ; voir [.github/workflows/ci.yml](../../.github/workflows/ci.yml).

## Analyse des risques techniques

| Risque | Impact | Probabilité | Mitigation |
|---|---|---|---|
| Périmètre ISO 27001 très large (93 contrôles Annexe A, audits, CAPA, tiers, formations, revues) | Blocage de la v1.0 | Moyenne | Découpage strict en sprints indépendants (voir tableau ci-dessus), chacun livrable et validable seul |
| Chevauchement fonctionnel avec le plugin jumeau glpi-vulnerability-manager | Confusion utilisateur, double saisie | Moyenne | Séparation stricte actée dans l'issue #89 du plugin jumeau : ce plugin ne traite aucune donnée CVE/CVSS, l'intégration bidirectionnelle (v2.0, voir ROADMAP.md) reste optionnelle |
| Modèle de données figé trop tôt (ex. matrice de risque fixe du Sprint 1) | Refonte coûteuse plus tard | Faible | Documenté explicitement dans `TECH_DEBT.md`, évolution planifiée dès le Sprint 2 |
| Divergence entre modèle GLPI et modèle du plugin (évolutions du core GLPI 11→12) | Rupture de compatibilité | Moyenne | Aucune dépendance à des mécanismes non documentés/non stables de GLPI ; vérification de version au chargement |

## Critères de livraison Version 1.0

Repris de la Definition of Done, complétés par les critères produit :

- Fonctionnel : registre de risques génériques, SoA, audits/CAPA, risques tiers, formations,
  revues de direction, dashboards.
- Technique : installation et désinstallation propres, migrations idempotentes, compatibilité
  GLPI 11.x vérifiée.
- Qualité : tests verts, CI/CD verte, analyse de sécurité sans alerte non justifiée.
- Documentation : README, guide utilisateur, guide administrateur, guide développeur.
- Open Source : licence GPLv3, repository propre, release publiée avec changelog.
