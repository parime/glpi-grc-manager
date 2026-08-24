# GLPI GRC Manager

> Generic Governance, Risk and Compliance (GRC) and ISO 27001 platform, natively integrated into
> GLPI.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-alpha%20%E2%80%94%20sprint%201-orange)](ROADMAP.md)
[![GLPI](https://img.shields.io/badge/GLPI-11.x-green)](docs/design/DEVELOPMENT_PLAN.md)

[🇫🇷 Français](README.md) | 🇬🇧 **English**

## The problem

GLPI knows exactly which hardware, software and systems make up your organization. What it can't
natively do is answer the questions a security officer or an ISO 27001 auditor actually asks:

> **What are my organization's risks, not just the technical ones, who accepted them and why, and
> am I compliant with Annex A?**

**GLPI GRC Manager** is a **separate** plugin from its sibling
[glpi-vulnerability-manager](https://github.com/parime/glpi-vulnerability-manager): that one
covers CVE-driven cyber risk (CVSS/EPSS/KEV), while GLPI GRC Manager covers generic organizational
risk (ISO 27001 clause 6.1.2/8.2): people, process, physical, third-party, with acceptance,
treatment, a Statement of Applicability (SoA), internal audits and corrective actions.

## What the plugin brings (v1.0 vision, see ROADMAP.md)

- **Generic risk register**: category (people/process/physical/third-party/technical),
  probability, impact, computed risk level, treatment decision (accept/mitigate/transfer/avoid),
  owner, justification, review date.
- **Statement of Applicability (SoA)**: the 93 ISO 27001:2022 Annex A controls (clause 6.1.3).
- **Internal audit program**: non-conformities, corrective and preventive actions (CAPA).
- **Supplier/third-party risk register.**
- **Security awareness training tracking.**
- **Management reviews.**

## Project status

**Sprint 1 "Plugin infrastructure" complete** and validated against a real GLPI 11: installable
plugin skeleton (`setup.php`/`hook.php`), a dedicated GLPI right (`plugin_grcmanager`), and a
first generic risk register (`PluginGrcmanagerRisk`) working end-to-end (list with translated
color-coded badges, create/edit form, automatic risk-level computation). See
[ROADMAP.md](ROADMAP.md) and [docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md)
for what's next.

## Installation

During the initial development phase (before the first release), install from source:

```bash
cd /var/www/glpi/plugins
git clone https://github.com/parime/glpi-grc-manager.git grcmanager
cd grcmanager
composer install --no-dev
```

Then, from GLPI: Setup > Plugins > GLPI GRC Manager > Install > Enable.

## Documentation

| Document | Content |
|---|---|
| [docs/design/DEVELOPMENT_PLAN.md](docs/design/DEVELOPMENT_PLAN.md) | Sprint-by-sprint development plan |
| [ROADMAP.md](ROADMAP.md) | Public roadmap by version |
| [CHANGELOG.md](CHANGELOG.md) | Change history |

## Target compatibility

- GLPI 11.x
- PHP per the GLPI 11 compatibility matrix (PHP 8.1 minimum)

## License

Distributed under the [GNU GPLv3](LICENSE) license. Free, community-driven project, with no
mandatory paid feature.

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md),
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) and [GOVERNANCE.md](GOVERNANCE.md).

## Security

To report a vulnerability, **do not open a public issue**: see the procedure described in
[SECURITY.md](SECURITY.md).

## Support

See [SUPPORT.md](SUPPORT.md) for help and discussion channels.
