# GLPI GRC Manager

> Plateforme de gouvernance, risque et conformité (GRC) et ISO 27001 générique, nativement
> intégrée à GLPI.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-alpha%20%E2%80%94%20sprint%201-orange)](ROADMAP.md)
[![GLPI](https://img.shields.io/badge/GLPI-11.x-green)](docs/design/DEVELOPMENT_PLAN.md)

🇫🇷 **Français** | [🇬🇧 English](README.en.md)

## Le problème

GLPI sait exactement quels matériels, logiciels et systèmes composent votre organisation. Ce
qu'il ne sait pas faire nativement, c'est répondre aux questions d'un responsable sécurité ou
d'un auditeur ISO 27001 :

> **Quels sont les risques organisationnels (pas seulement techniques) de mon organisation, qui
> les a acceptés et pourquoi, et suis-je conforme à l'Annexe A ?**

**GLPI GRC Manager** est un plugin **distinct** du plugin jumeau
[glpi-vulnerability-manager](https://github.com/parime/glpi-vulnerability-manager) : ce dernier
couvre le risque cyber piloté par CVE (CVSS/EPSS/KEV), tandis que GLPI GRC Manager couvre le
risque organisationnel générique (clause 6.1.2/8.2 ISO 27001) : humain, processus, physique,
tiers/fournisseur, avec acceptation, traitement, Déclaration d'Applicabilité (SoA), audits
internes et actions correctives.

## Ce que le plugin apporte (vision v1.0, voir ROADMAP.md)

- **Registre de risques génériques** : catégorie (humain/processus/physique/tiers/technique),
  probabilité, impact, niveau de risque calculé, décision de traitement (accepter/mitiger/
  transférer/éviter), propriétaire, justification, date de revue.
- **Déclaration d'Applicabilité (SoA)** : les 93 contrôles de l'Annexe A ISO 27001:2022 (clause
  6.1.3).
- **Programme d'audit interne** : non-conformités, actions correctives et préventives (CAPA).
- **Registre de risques fournisseurs/tiers.**
- **Suivi des formations de sensibilisation à la sécurité.**
- **Revues de direction.**

## État du projet

**Sprint 1 "Infrastructure plugin" terminé** et validé contre GLPI 11 réel : squelette de plugin
installable (`setup.php`/`hook.php`), droit GLPI dédié (`plugin_grcmanager`), et un premier
registre de risques génériques (`PluginGrcmanagerRisk`) fonctionnel de bout en bout (liste avec
badges colorés traduits, formulaire de création/édition, calcul automatique du niveau de risque).
Voir [ROADMAP.md](ROADMAP.md) et [docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md)
pour la suite.

## Installation

Pendant la phase de développement initiale (avant la première release), installez depuis le code
source :

```bash
cd /var/www/glpi/plugins
git clone https://github.com/parime/glpi-grc-manager.git grcmanager
cd grcmanager
composer install --no-dev
```

Puis, depuis GLPI : Configuration > Plugins > GLPI GRC Manager > Installer > Activer.

## Documentation

📖 **[Voir le tutoriel complet](docs/TUTORIAL.md)** : le flux entier du plugin, de l'activation au
tableau de bord, en passant par le registre de risques, la matrice probabilité x impact, la SoA et
les audits/CAPA, avec une capture d'écran réelle par étape (disponible en français et en anglais).

| Document | Contenu |
|---|---|
| [docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md) | Plan de développement par sprints |
| [ROADMAP.md](ROADMAP.md) | Roadmap publique par version |
| [CHANGELOG.md](CHANGELOG.md) | Historique des évolutions |

## Compatibilité cible

- GLPI 11.x
- PHP selon la matrice de compatibilité GLPI 11 (PHP 8.1 minimum)

## Licence

Distribué sous licence [GNU GPLv3](LICENSE). Projet gratuit, communautaire, sans fonctionnalité
payante obligatoire.

## Contribuer

Les contributions sont bienvenues. Voir [CONTRIBUTING.md](CONTRIBUTING.md),
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) et [GOVERNANCE.md](GOVERNANCE.md).

## Sécurité

Pour signaler une vulnérabilité, **ne pas ouvrir d'issue publique** : voir la procédure décrite
dans [SECURITY.md](SECURITY.md).

## Support

Voir [SUPPORT.md](SUPPORT.md) pour les canaux d'aide et de discussion.
