[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

**Politique de sécurité**

### Signaler une vulnérabilité

**Ne créez jamais d'issue publique pour signaler une faille de sécurité.**

Utilisez l'un des canaux privés suivants :

1. **GitHub Security Advisories** (recommandé) : onglet *Security* du repository →
   *Report a vulnerability*. Ce canal est chiffré et privé entre vous et les mainteneurs.
2. **Email** : voir l'adresse de contact sécurité listée dans [MAINTAINERS.md](MAINTAINERS.md).

Merci d'inclure :

- une description du problème et de son impact potentiel ;
- les versions concernées (plugin, GLPI, PHP) ;
- les étapes de reproduction ou un PoC si possible ;
- votre évaluation de la sévérité (CVSS si vous en avez un).

### Engagement de réponse

| Étape | Délai cible |
|---|---|
| Accusé de réception | 72 heures |
| Analyse et confirmation | 7 jours |
| Correctif ou plan de mitigation | selon sévérité, communiqué après confirmation |

La sévérité est évaluée avec CVSS v3.1. Les correctifs critiques font l'objet d'une release
`hotfix/*` prioritaire (voir [CONTRIBUTING.md](CONTRIBUTING.md#stratégie-de-branches)).

### Divulgation coordonnée

Nous suivons un principe de divulgation coordonnée (*coordinated disclosure*) :

- le rapporteur est tenu informé de l'avancement ;
- un correctif est publié avant toute divulgation publique des détails techniques ;
- le rapporteur est crédité dans le changelog et l'advisory GitHub, sauf demande contraire ;
- un délai raisonnable (90 jours par défaut) est proposé avant divulgation publique si aucun
  correctif n'est trouvé, à discuter au cas par cas avec le rapporteur.

### Versions supportées

Tant que le plugin n'a pas atteint sa première release stable (`1.0.0`), seule la branche `dev`
est suivie en matière de sécurité. À partir de `1.0.0`, ce tableau sera tenu à jour :

| Version | Supportée |
|---|---|
| `dev` (pré-1.0) | ✅ |

### Périmètre

Sont dans le périmètre : le code du plugin (`front/`, `inc/`, `src/`), ses migrations SQL, son
pipeline CI/CD, et ses dépendances directes.

Sont hors périmètre : les vulnérabilités du cœur GLPI (à signaler à l'équipe GLPI).

## 🇬🇧 English

**Security policy**

### Reporting a vulnerability

**Never create a public issue to report a security flaw.**

Use one of the following private channels:

1. **GitHub Security Advisories** (recommended): the repository's *Security* tab →
   *Report a vulnerability*. This channel is encrypted and private between you and the
   maintainers.
2. **E-mail**: see the security contact address listed in [MAINTAINERS.md](MAINTAINERS.md).

Please include:

- a description of the issue and its potential impact;
- the versions affected (plugin, GLPI, PHP);
- the reproduction steps or a PoC if possible;
- your severity assessment (a CVSS score if you have one).

### Response commitment

| Step | Target time |
|---|---|
| Acknowledgment | 72 hours |
| Analysis and confirmation | 7 days |
| Fix or mitigation plan | depending on severity, communicated after confirmation |

Severity is assessed using CVSS v3.1. Critical fixes get a priority `hotfix/*` release (see
[CONTRIBUTING.md](CONTRIBUTING.md#branch-strategy)).

### Coordinated disclosure

We follow a coordinated disclosure principle:

- the reporter is kept informed of progress;
- a fix is published before any public disclosure of technical details;
- the reporter is credited in the changelog and the GitHub advisory, unless they ask otherwise;
- a reasonable delay (90 days by default) is offered before public disclosure if no fix is
  found, to be discussed case by case with the reporter.

### Supported versions

Until the plugin reaches its first stable release (`1.0.0`), only the `dev` branch is tracked for
security purposes. From `1.0.0` onward, this table will be kept up to date:

| Version | Supported |
|---|---|
| `dev` (pre-1.0) | ✅ |

### Scope

In scope: the plugin's code (`front/`, `inc/`, `src/`), its SQL migrations, its CI/CD pipeline,
and its direct dependencies.

Out of scope: vulnerabilities in GLPI core (to be reported to the GLPI team).
