# Changelog

Toutes les évolutions notables de ce projet sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), et ce projet adhère au
[Semantic Versioning](https://semver.org/lang/fr/) (`MAJEUR.MINEUR.CORRECTIF`).

## [Non publié]

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
