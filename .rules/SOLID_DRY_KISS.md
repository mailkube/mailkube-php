# Engineering Standards: SOLID · DRY · KISS · Coverage · Docs

These are **enforced by CI** — a PR that violates them cannot merge. This file tells you the exact
thresholds and how to satisfy each gate locally *before* pushing.

## The gates

| Gate | Rule | Enforced by |
|---|---|---|
| **Coverage** | ≥ 90% line | `phpunit --coverage-clover` + `scripts/check-coverage.sh` (the `test` CI job) |
| **DRY** | ≤ 1% duplicated code | `jscpd` (the `dry` CI job) — `src/` at `minTokens: 50`, `examples/` at 100 |
| **Examples parse** | every `examples/*.php` is valid PHP | `php -l` (the `examples` CI job) |
| **KISS** | cyclomatic complexity ≤ 10 per unit | `phpmd` `codesize` ruleset (the `test` CI job) |
| **Documentation** | every class + public method has a docblock | `phpcs` (`Squiz.Commenting.*`) + slevomat type hints |
| **SOLID** | see below — approximated by analysis + review | `phpmd` `design`/`unusedcode` + phpstan strict + PR checklist |
| **Strict typing** | no findings at phpstan level max | `phpstan` + `phpstan-strict-rules` (the `test` CI job) |
| **Formatting** | PSR-12 clean | `php-cs-fixer fix --dry-run` (the `test` CI job) |

> **Coverage is line only.** PHPUnit/pcov report covered-vs-total statements but no reliable branch
> metric, so there is nothing further to gate on. Keep tests exercising every meaningful path
> anyway — the number is a floor.

## Run the gates locally

```bash
composer format:check                                 # PSR-12 formatting (php-cs-fixer)
composer cs                                            # docs + type hints (phpcs)
composer analyse                                       # strict static analysis (phpstan, level max)
composer mess                                          # complexity (KISS) + design (SOLID) (phpmd)
composer test -- --coverage-clover=coverage.clover     # tests + coverage report
./scripts/check-coverage.sh coverage.clover            # 90% coverage gate
npx --yes jscpd@4 --config .jscpd.json .               # duplication (DRY) gate
npx --yes jscpd@4 --config .jscpd.examples.json examples/  # the same gate over examples/
for f in examples/*.php; do php -l "$f" || exit 1; done   # every example parses
./scripts/check-rule-index.sh                          # every .rules/*.md indexed in AGENTS.md
```

`pre-commit run --all-files` runs the php-cs-fixer + phpcs + phpstan + phpmd + jscpd hooks in one shot.

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

## SOLID, concretely (paradigm-neutral guidance)

SOLID is not a single analyzer rule; keep these in mind and confirm them in the PR checklist:

- **S**ingle responsibility — a class/method does one thing; if you need "and" to describe it, split it.
- **O**pen/closed — extend via new classes/strategies, not by editing stable call sites.
- **L**iskov — subtypes honor their base's contract (types, exceptions, invariants).
- **I**nterface segregation — small, focused `interface`s; unused parameters (phpmd `UnusedFormalParameter`) are a smell.
- **D**ependency inversion — depend on an `interface` at I/O and network boundaries, and inject it.

## Requesting a waiver

If a threshold is genuinely wrong for a specific spot, add a **scoped, commented** ignore
(e.g. `@SuppressWarnings(PHPMD.CyclomaticComplexity)` with a `// reason`, or a `@phpstan-ignore-line`)
and call it out in the PR. Blanket relaxations (lowering the coverage threshold, dropping a phpstan
level, disabling a ruleset globally) require maintainer sign-off.
