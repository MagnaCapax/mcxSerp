# Development

Start here if you change this repository, by hand or with a coding agent.

## Read first

1. [`AGENTS.md`](../AGENTS.md): the rules for this repository, and the specification the library was built from (interfaces, models, provider field mappings, what not to build).
2. [Engineering principles](engineering-principles.md): the organisation-wide rules with their reasons and the mistakes that taught them. Adapted copy; the canonical one is in mcxPikkumies.
3. [Decision records](../docs/adr/): accepted ADRs are binding. A significant change ships with a new one ([template](../docs/adr/0000-template.md)). Start with [ADR-0001](../docs/adr/0001-architecture-layout-and-languages.md), the layout and languages of this repository.
4. [README](../README.md): installation, quick start and the public API.

## Before you commit

- `php tests/run.php` passes: it prints `Tests: N, Failures: 0` and exits 0. Read the summary and the exit code; do not pipe it through anything that hides them. It loads small stand-ins for the PHPUnit and PSR interfaces from `tests/stubs/`, so it needs no Composer install and no network; provider tests use `tests/Http/MockHttpClient.php` with saved responses from `tests/fixtures/`. There is no CI in this repository, so this local run is the only check.
- New behaviour has a test that fails when the behaviour is broken (break it once and see). A provider change gets a fixture and a test for its error paths, not only the happy path.
- Review the change with the [review unit](https://github.com/MagnaCapax/mcxPikkumies/tree/main/pikkumies/tools/development/code-review): correctness, an adversarial pass, weakness classes, and conformance to `AGENTS.md`, the ADRs and the principles. Any agent can follow its `skill.md`.
- No API keys in code, tests or fixtures, no provider, model or vendor name beyond the search API providers this library integrates, and nothing that is not meant for the public: this repository is public.

## Other MagnaCapax repositories

Each has its own `AGENTS.md`, decision records and docs. When your change touches another project, read its records first.

| Repository | What it is | Docs | Decisions |
|---|---|---|---|
| [PMSS](https://github.com/MagnaCapax/PMSS) | Pulsed Media Seedbox Software, the platform Pikkumies ships on | [docs](https://github.com/MagnaCapax/PMSS/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/PMSS/tree/main/docs/adr) |
| [mcxPikkumies](https://github.com/MagnaCapax/mcxPikkumies) | Lightweight helper agent that runs on the server it manages | [docs](https://github.com/MagnaCapax/mcxPikkumies/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxPikkumies/tree/main/docs/adr) |
| [mcxVirsilipas](https://github.com/MagnaCapax/mcxVirsilipas) | Prepaid token-metering proxy for model APIs | [docs](https://github.com/MagnaCapax/mcxVirsilipas/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxVirsilipas/tree/main/docs/adr) |
| [mcxForge](https://github.com/MagnaCapax/mcxForge) | Bare-metal qualification, repair and installation suite | [docs](https://github.com/MagnaCapax/mcxForge/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxForge/tree/main/docs/adr) |
| [mcxRescue](https://github.com/MagnaCapax/mcxRescue) | Rescue live system for bare-metal servers | [docs](https://github.com/MagnaCapax/mcxRescue/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxRescue/tree/main/docs/adr) |
| [mcxTemplate](https://github.com/MagnaCapax/mcxTemplate) | Templating engine for distribution installs | [docs](https://github.com/MagnaCapax/mcxTemplate/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxTemplate/tree/main/docs/adr) |
| [nodeAgent](https://github.com/MagnaCapax/nodeAgent) | Server telemetry and hardware health agent | [docs](https://github.com/MagnaCapax/nodeAgent/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/nodeAgent/tree/main/docs/adr) |
| [mcxSerp](https://github.com/MagnaCapax/mcxSerp) | This repository | [docs](https://github.com/MagnaCapax/mcxSerp/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxSerp/tree/main/docs/adr) |
| [mcxSauces](https://github.com/MagnaCapax/mcxSauces) | Scripts and configs for running systems and services | [docs](https://github.com/MagnaCapax/mcxSauces/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxSauces/tree/main/docs/adr) |
| [mcxBMCView](https://github.com/MagnaCapax/mcxBMCView) | BMC console screenshot and keyboard automation | [docs](https://github.com/MagnaCapax/mcxBMCView/tree/main/docs) | [ADRs](https://github.com/MagnaCapax/mcxBMCView/tree/main/docs/adr) |
