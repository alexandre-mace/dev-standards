---
name: quality
description: Run all quality checks on the codebase. Auto-detects project type (Symfony, Next.js, or both) and runs the appropriate checks. Use before committing or to verify code quality.
---

# Quality Assurance Checks

Run quality checks adapted to the current project. Detect the project type first, then run only the relevant checks.

## Project detection

Detect the project type by checking for these files in the working directory:

- **Symfony**: `composer.json` contains `symfony/framework-bundle`
- **Next.js**: `next.config.js`, `next.config.ts`, or `next.config.mjs` exists
- **React (standalone)**: `package.json` contains `react` but no Next.js config

A project can be both (e.g. Symfony + React).

## Symfony checks

Run these only if Symfony is detected:

### PHPStan (static analysis)

```bash
vendor/bin/phpstan analyse --no-progress
```

If not installed, report as SKIPPED and suggest: `composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/phpstan-deprecation-rules`

### PHP-CS-Fixer (code style)

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

If not installed, report as SKIPPED and suggest: `composer require --dev friendsofphp/php-cs-fixer`

Do NOT run without `--dry-run` — show violations only, never auto-fix.

### Doctrine schema validation

```bash
php bin/console doctrine:schema:validate --skip-sync
```

### Symfony container lint

```bash
php -d memory_limit=256M bin/console lint:container
```

### Composer security audit

```bash
composer audit
```

Reports known vulnerabilities in PHP dependencies.

### PHPUnit tests

```bash
bin/phpunit
```

If `bin/phpunit` does not exist, skip.

## Next.js checks

Run these only if Next.js is detected:

### Next.js build check

```bash
pnpm next build --dry-run 2>/dev/null || pnpm next lint
```

## Shared frontend checks (React, Next.js, or Symfony+React)

Run these if `package.json` exists:

### TypeScript type check

```bash
pnpm tsc --noEmit
```

If no `tsconfig.json` exists, skip.

### ESLint

```bash
pnpm lint
```

If no `lint` script in `package.json`, skip.

### Prettier

```bash
pnpm format:check
```

If no `format:check` script in `package.json`, skip.

## Reporting

After all checks, show a summary table adapted to the detected project type:

```
Quality Report — [Symfony + React | Next.js | etc.]
----------------------------------------------------
PHPStan:              PASS / FAIL / SKIPPED (not installed)
PHP-CS-Fixer:         PASS / FAIL / SKIPPED (not installed)
Doctrine schema:      PASS / FAIL
Container lint:       PASS / FAIL
Composer audit:       PASS / FAIL (N vulnerabilities)
PHPUnit:              PASS / FAIL / SKIPPED (no bin/phpunit)
TypeScript:           PASS / FAIL / SKIPPED (no tsconfig)
ESLint:               PASS / FAIL / SKIPPED (no lint script)
Prettier:             PASS / FAIL / SKIPPED (no format:check script)
```

Only show rows for checks that apply to the detected project type.

For each FAIL, show the errors and suggest fixes.
