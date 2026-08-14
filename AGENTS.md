# Project Rules

`mailkube-php` is a public (Apache-2.0) mailkube SDK distributed on
Packagist as `mailkube/mailkube-php`. Load the relevant rule file from `.rules/` based on the task.

## Rule Index

> **Index every rule (required).** Every file in `.rules/` MUST have a row in the table below. When you
> add or rename a `.rules/` file, add or update its row in the **same change** — an unindexed rule is
> invisible, because this index is what drives progressive disclosure. The `docs` CI job (`scripts/check-rule-index.sh`)
> fails the build if `.rules/` and this index drift. This convention holds for every mailkube repo.

| Rule File | Load When |
|---|---|
| `.rules/SOLID_DRY_KISS.md` | Writing or changing any code — the enforced engineering standards (SOLID, DRY, KISS, coverage, docs) and how to run each gate locally. |
| `.rules/SDK_CONTRACT.md` | Adding a resource, verb, response model, paginated listing, or webhook event: the cross-SDK decisions (config, layering, naming, errors, pagination, webhooks) every mailkube SDK implements identically. Editing it changes what every SDK promises; mirror the change into the siblings by hand. |
| `.rules/SDK_DESIGN.md` | The same tasks, for the **PHP realization**: the layer-to-file map, the PSR-18 injection seam, named-argument verbs, and the `@phpstan-param` convention. |
| `.rules/RELEASE.md` | Touching `release.yml`, `.releaserc.json`, versioning, or Packagist publishing. |

## Key Conventions (always apply)

- **Composer + PSR-4** — code in `src/` under `Mailkube\`; tests in `tests/`.
- **`declare(strict_types=1);`** in every file; **php-cs-fixer** (PSR-12) for formatting.
- **phpstan level max** + strict-rules is non-negotiable; fix findings, don't lower the level.
- **Docblock** on every class and public method (`phpcs` Squiz commenting); native types carry the type info.
- **≥ 90% line coverage** (PHP has no branch metric) — enforced by `scripts/check-coverage.sh`; never lower the gate.
- **Max cyclomatic 10** (`phpmd` codesize) — split, don't waive.
- **Depend on interfaces at boundaries** (DIP); keep interfaces small (ISP) — unused params are a smell.
- **No duplication** — the `jscpd` gate blocks at > 1% duplicated code; extract shared logic.
- **Conventional Commits** for PR titles (squash-merged); only `feat:`/`fix:`/`perf:` cut a release.
- **No secrets in the repo** — local config lives in a git-ignored `.env`, excluded from the built image.
- **Releases are git tags** — Packagist ingests `vX.Y.Z` via its GitHub service hook; there is no publish step.
- **Releases commit nothing to `main`** — the git tag is the version, and the GitHub Release
  notes are the changelog; there is no `CHANGELOG.md` (see `.rules/RELEASE.md`).
