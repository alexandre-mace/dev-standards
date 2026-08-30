---
name: quality
description: Is the code clean? Runs every mechanical check the stack's guidelines mandate. Auto-detects the stack (Symfony+React, Next, TanStack Start) and runs what applies. Use before committing, or to check quality mid-work.
---

# Quality gate

Everything a machine can settle on its own, fast and headless.

- What needs a browser is `/live-test`. The full E2E suite is CI.
- The test suites make this the slow gate. That is the price of "the tests pass" being a
  fact rather than an assumption.

## Detect the stack

| Stack | Detected by |
|---|---|
| Symfony + React | `symfony/framework-bundle` in `composer.json` |
| Next | a `next.config.{js,ts,mjs}` |
| TanStack Start | `@tanstack/react-start` in `package.json` |
| Standalone React | `react` in `package.json`, none of the above |

A project can match several. Run every branch that does.

## Symfony

```bash
vendor/bin/phpstan analyse --no-progress
vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console doctrine:schema:validate --skip-sync
php -d memory_limit=256M bin/console lint:container
composer audit
bin/phpunit
```

- Never run PHP-CS-Fixer without `--dry-run`: report, do not rewrite the tree under the
  user.
- A tool not installed is SKIPPED, with the `composer require --dev` line. Not a FAIL.
- No `bin/phpunit` is SKIPPED, but say it: the guidelines mandate a suite, so its
  absence is a gap.

### Contract drift, when the SDK is generated from OpenAPI

A `types` target in the `Makefile`, or an `openapi.yaml` at the root:

```bash
make types
git diff --exit-code openapi.yaml assets/lib/api/
```

A non-empty diff is a FAIL: the backend contract moved, the SDK did not follow, the
frontend is about to break silently. Leave the regenerated files in place and say so.

## Next, TanStack Start

```bash
pnpm build
```

The build is the check: it type-checks and fails on compile errors. Never `next lint`
(removed in Next 16). On TanStack, the build surfaces route-tree and typed-router errors
that `tsc` alone misses.

## Any project with a `package.json`

```bash
pnpm tsc --noEmit      # skip: no tsconfig.json
pnpm lint              # skip: no lint script
pnpm format:check      # skip: no format:check script
pnpm test              # skip: no test script
```

Name the tool the project actually runs behind those scripts, Biome on Next and TanStack,
ESLint and Prettier on the Symfony stack. A report saying ESLint when Biome ran is a
report nobody can act on.

`pnpm test` is Vitest, mandated on all three stacks. Running it is what turns "the tests
pass" into a fact. Use whatever flag the project needs for a single non-watch run.

**Not run here**: Playwright, which needs a browser and minutes, and which `/live-test`
owns. Psalm taint analysis is a CI job. Say so rather than letting the report imply full
coverage.

## Report

```
Quality : <stack>
------------------------------------------------
PHPStan:              PASS / FAIL / SKIPPED (not installed)
PHP-CS-Fixer:         PASS / FAIL / SKIPPED (not installed)
Doctrine schema:      PASS / FAIL
Container lint:       PASS / FAIL
Composer audit:       PASS / FAIL (N vulnerabilities)
PHPUnit:              PASS / FAIL / SKIPPED (no suite)
Contract drift:       PASS / FAIL / n/a
Build:                PASS / FAIL
TypeScript:           PASS / FAIL / SKIPPED (no tsconfig)
Lint (Biome|ESLint):  PASS / FAIL / SKIPPED (no script)
Format (Biome|Prettier): PASS / FAIL / SKIPPED (no script)
Vitest:               PASS / FAIL / SKIPPED (no script)
Not run here:         Playwright (/live-test), Psalm (CI)
```

- Only the rows that apply.
- Every FAIL shows the real error output and the fix, never a paraphrase.
- **A vulnerability never blocks the run.** Report it at the end, apply the update, and
  commit that on its own: it does not belong in the feature's changeset.
- A SKIPPED row the guidelines mandate gets one closing line: it is a `/gap-code`
  finding, not something to fix inside this run.
