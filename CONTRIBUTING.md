# Contributing to mailkube-php

Thanks for helping improve **mailkube-php**, a [mailkube](https://mailkube.com) SDK.
Contributions of all kinds are welcome: bug reports, fixes, docs, and features.

By contributing you agree that your contributions are licensed under the project's
[Apache License 2.0](LICENSE) (inbound = outbound). **No CLA and no sign-off are required.**
Please also read our [Code of Conduct](CODE_OF_CONDUCT.md).

## Development setup

Requires PHP 8.3+ (with the `pcov` extension for coverage),
[Composer](https://getcomposer.org/), and Node.js (for the `jscpd` duplication check).

```bash
git clone https://github.com/mailkube/mailkube-php
cd mailkube-php

composer install
pre-commit install                            # php-cs-fixer + phpcs + phpstan + phpmd + jscpd hooks
pre-commit install --hook-type commit-msg     # Conventional Commits hook
```

## Quality gates

Every change must pass the same checks CI runs (see [.rules/SOLID_DRY_KISS.md](.rules/SOLID_DRY_KISS.md)):

```bash
composer format:check                                 # PSR-12 formatting (php-cs-fixer)
composer cs                                            # docs + type hints (phpcs)
composer analyse                                       # strict static analysis (phpstan, level max)
composer mess                                          # complexity (KISS) + design (SOLID) (phpmd)
composer test -- --coverage-clover=coverage.clover     # tests + coverage
./scripts/check-coverage.sh coverage.clover            # 90% coverage gate
npx --yes jscpd@4 --config .jscpd.json .               # duplication (DRY) gate, blocks at > 1%
npx --yes jscpd@4 --config .jscpd.examples.json examples/  # the same gate over examples/
for f in examples/*.php; do php -l "$f" || exit 1; done   # every example parses
./scripts/check-rule-index.sh                          # every .rules/*.md indexed in AGENTS.md
```

`pre-commit run --all-files` runs the format/lint/analysis/jscpd hooks in one shot.

**`examples/` is in scope for php-cs-fixer, phpcs, phpstan and phpmd.** It is runnable
documentation, which is the reason, not an exception to it: customers copy those files, and every
defect the SDK certification run surfaced lived there because no gate looked at it. Two carve-outs
remain, each for a reason:

- **Duplication** is measured by a *separate* pass, `.jscpd.examples.json`, at `minTokens: 100`
  instead of 50. Every example repeats the same opening — require the autoloader, read
  `MAILKUBE_FROM`, construct the client — and hoisting that into a shared helper would make each
  file unreadable on its own, which is the one thing an example must be. 100 clears that
  scaffolding (measured: the cliff is at 90) and still fails on a copy-pasted example.
- **Coverage** excludes them, because nothing in CI executes them: they need live credentials.

## Commit & PR conventions

This project follows **[Conventional Commits](https://www.conventionalcommits.org/)**. A CI check
enforces the **PR title** (PRs are **squash-merged** using it), and it drives releases: only
`feat:`, `fix:`, and `perf:` cut a new version. See [.rules/RELEASE.md](.rules/RELEASE.md).

Suggested scopes: `client`, `models`, `ci`, `deps`, `docs`.

```
feat(client): add retry with exponential backoff
fix(models): correct optional field serialization
docs: document the pagination helper
```

## Reporting bugs / requesting features

Open an issue using the templates. For **security vulnerabilities**, do not open a public
issue — follow [SECURITY.md](SECURITY.md) instead.
