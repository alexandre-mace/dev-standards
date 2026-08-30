---
name: quality
description: Run every mechanical check the stack's guidelines mandate. Auto-detects the stack (Symfony+React, Next, TanStack Start) and runs what applies. Use before committing, or to check quality mid-work.
---

# Quality gate

Run the checks that a machine can settle on its own. Detect the stack first, then
run only what applies to it.

**Where the line falls.** This skill runs everything that is fast and headless. What
needs a browser belongs to `/verify`; the full E2E suite belongs to CI. Keep this
gate under roughly 30 seconds so it can be run often, and never make it the reason
someone stops running it.

## Stack detection

- **Symfony + React**: `composer.json` contains `symfony/framework-bundle` (plus a
  `package.json`, almost always)
- **Next**: a `next.config.{js,ts,mjs}` exists
- **TanStack Start**: `package.json` depends on `@tanstack/react-start`
- **Standalone React**: a `package.json` with `react` and none of the above

A project can be more than one thing. Run every branch that matches.

## Symfony

```bash
vendor/bin/phpstan analyse --no-progress
vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console doctrine:schema:validate --skip-sync
php -d memory_limit=256M bin/console lint:container
composer audit
bin/phpunit
```

- Never run PHP-CS-Fixer without `--dry-run`: show violations, don't silently rewrite
  the working tree under the user.
- A tool that is not installed is SKIPPED, with the `composer require --dev` line to
  install it. Not a FAIL: a project that never had PHPStan isn't failing PHPStan.
- `bin/phpunit` absent is SKIPPED. But note it: the guidelines mandate a test suite,
  so its absence is a gap, not a neutral fact.

### Contract drift (Symfony + React)

If the project generates its frontend SDK from OpenAPI (a `types` target in the
`Makefile`, or an `openapi.yaml` at the root):

```bash
make types
git diff --exit-code openapi.yaml assets/lib/api/
```

A non-empty diff is a FAIL: the backend contract moved and the generated SDK was not
regenerated, so the frontend is about to break silently. This is in the Definition of
Done, and it is the cheapest gate in the whole stack. Leave the regenerated files in
place, staged or not, and say so.

## Next

```bash
pnpm build
```

The build **is** the check: it type-checks and fails on compile errors. Do not use
`next lint` (removed in Next 16).

## TanStack Start

```bash
pnpm build
```

Same reasoning. Vite's build surfaces the route-tree and typed-router errors that
`tsc` alone can miss.

## Every project with a `package.json`

```bash
pnpm tsc --noEmit      # skip if no tsconfig.json
pnpm lint              # skip if no lint script
pnpm format:check      # skip if no format:check script
pnpm test              # skip if no test script
```

`pnpm test` is Vitest, and the guidelines mandate it on all three stacks. Running it
here is what makes "the tests pass" a fact rather than an assumption. Pass whatever
flag the project needs for a single non-watch run.

**Not run here**: Playwright (`pnpm test:e2e`), which needs a browser and minutes,
and Psalm's taint analysis, which is a CI job. Say they were not run rather than
letting the report imply everything was covered.

## Report

```
Quality : <detected stack>
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
ESLint:               PASS / FAIL / SKIPPED (no script)
Prettier:             PASS / FAIL / SKIPPED (no script)
Vitest:               PASS / FAIL / SKIPPED (no script)
Not run here:         Playwright (CI), Psalm (CI)
```

Only show the rows that apply. For each FAIL, show the real error output and the fix,
never a paraphrase.

A SKIPPED row that the guidelines mandate (no test suite, no PHPStan) is worth one
line at the end: it is a gap for `/gap-analysis`, not something to fix inside this run.
