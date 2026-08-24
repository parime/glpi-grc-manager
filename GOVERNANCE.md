# Gouvernance du projet

## Modèle

GLPI GRC Manager suit un modèle **BDFL assisté de mainteneurs** (Benevolent Dictator For Life
assisté) le temps que la communauté grandisse : le fondateur du projet tranche les décisions
structurantes (architecture, roadmap, sécurité), assisté par les mainteneurs listés dans
[MAINTAINERS.md](MAINTAINERS.md). Ce modèle est destiné à évoluer vers une gouvernance plus
collégiale (comité technique) à mesure que le nombre de contributeurs réguliers augmente.

## Rôles

| Rôle | Responsabilités |
|---|---|
| **Fondateur / Lead maintainer** | Vision produit, arbitrage final, gestion de la sécurité, décisions d'architecture |
| **Mainteneur** | Revue et merge des PR, triage des issues, releases, animation communauté |
| **Contributeur** | Propose des PR, des issues, participe aux discussions |

Voir [MAINTAINERS.md](MAINTAINERS.md) pour la liste actuelle.

## Prise de décision

- **Décisions techniques structurantes** (architecture, modèle de données, sécurité) : proposées
  en PR, documentées dans [docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md),
  validées par au moins un mainteneur en plus de l'auteur.
- **Fonctionnalités** : discutées via issue avant développement, arbitrées selon la
  [ROADMAP.md](ROADMAP.md).
- **Désaccord** : en l'absence de consensus, le lead maintainer tranche, en motivant sa décision
  publiquement dans l'issue ou la PR concernée.

## Devenir mainteneur

Un contributeur régulier, dont les PR démontrent une compréhension solide de l'architecture, de la
sécurité et des standards du projet, peut être proposé comme mainteneur par un mainteneur
existant. La décision finale revient au lead maintainer.

## Releases

Les releases sur `main` sont exclusivement déclenchées par les mainteneurs, après validation CI
complète et revue de code : voir [CONTRIBUTING.md](CONTRIBUTING.md#stratégie-de-branches) et
[docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md).

## Évolution de la gouvernance

Ce document sera révisé publiquement (via PR) à mesure que le projet et sa communauté grandissent.
