# Symfony + React watch

The dated material of the tech watch for this stack: what is merged on the next versions
and worth anticipating, the advisories behind each security floor, and when a deliberate
decision was last re-validated. `symfony-guidelines.md` keeps the floors and the rules,
with no date to go stale; this file keeps the why and the when.

`/gap-sota` reads it at the start of a watch and updates it at the end:

- a radar item whose release has shipped becomes a rule in the guidelines, or nothing if
  it changes nothing we write, and leaves the radar;
- a floor that moves gets its advisories here, and the guideline its new number;
- a decision checked again gets its new date.

**Last watch: 28 October 2026.**

## Security floors: the advisories behind them

| Floor | Advisories | What they close |
|---|---|---|
| PHP runtime ≥ 8.5.12 | CVE-2026-109618, CVE-2026-109619 (8.5.12, 21 October 2026, CVSS v4 9.2) | two unauthenticated remote code executions on any PHP serving web requests; Clever published 8.5.12 the same day and redeploys PHP apps onto it automatically |
| | 8.5.11 | TLS hostname verification falling back to the CN, credentials leaking across origins on HTTP stream redirects, an FPM IPv6 ACL bypass |
| | CVE-2026-17543 (8.5.9) | a SQL injection in ext-pgsql through `pg_insert()`/`pg_update()`/`pg_select()`/`pg_delete()` |
| Symfony ≥ 8.1.9 | GHSA-7rpj-w9pf-39rr, CVE-2026-109449 (8.1.9, 14 October 2026) | a deeply nested expression crashes the process, and `#[Assert\ExpressionSyntax]` hands that parser to user input; the first fix, in 8.1.6, was incomplete |
| | 8.1.6, 8.1.7 | a Yaml inline lexer recursion reachable through `#[MapRequestPayload]` when the Yaml component is installed, and a ReDoS on `Cache-Control` |
| EasyAdmin ≥ 5.5.1 | GHSA-g2fm-8hr4-j82h (August 2026, CVSS 8.1) | the `routeName` of custom actions substituted after the firewall was evaluated, bypassing URL-pattern `access_control` |
| Twig ≥ 3.27 | CVE-2026-48805 to CVE-2026-48808 | |
| PHPUnit ≥ 13.4.4 | GHSA-9vw2-f4vp-x7gf | PHPT tests run with coverage in a fixed-name `/tmp/phpunit-phpt` directory another local user can plant code in |
| PostgreSQL 18.6 / 17.11 | 28 vulnerabilities (published by Clever on 20 August 2026) | three issues need extra steps after the update: the guideline names them (§8) |

## Decisions re-validated

- **No API Platform**: re-validated in October 2026, still true with API Platform 5.0, a
  cleanup major that changes nothing in the reasoning.
- **PHP-FPM, not FrankenPHP**: benchmarks checked in August 2026. October 2026 confirmed
  the price: seventeen FrankenPHP advisories in one month, most of them in worker mode.
  Re-assess at every watch.
- **No Rector, no Mago**: decided on 30 October 2026, Mago at 1.59.

## Dates and issues to follow

- **doctrine/dbal#7530**, open at the 28 October 2026 watch: DBAL maps SQLSTATE 23001 to
  `ForeignKeyConstraintViolationException`. At the release that ships it, the guideline's
  middleware (§8) goes.
- **PostgreSQL**: the next upstream minor is due on 12 November 2026; Clever's publication
  is what moves the reference.

## Symfony 8.2 radar (expected November 2026)

Deprecations and behaviour changes **already merged** on the 8.2 branch (UPGRADE-8.2.md), worth anticipating now:

- **Hardened Serializer**: union-typed collections now denormalize their elements, and arrays denormalized into `list`-typed properties will have to satisfy `array_is_list()` (an exception in 9.0). Worth testing on our collection-carrying `#[MapRequestPayload]` DTOs.
- `#[IsCsrfTokenValid]` will return a **403** (`InvalidCsrfTokenException`) instead of redirecting to the login page.
- `File` constraint: `mimeTypes` and `extensions` will be checked independently (no more MIME restriction inferred from the extension).
- `framework.ide` deprecated in favour of the `SYMFONY_IDE` variable.

Merged since, still on the 8.2 branch (no beta tag at the 29 September watch):

- **FrameworkBundle is split** into bundles shipped by their own components (MessengerBundle, MailerBundle, SerializerBundle, ValidationBundle, RouterBundle and about twenty more), registered automatically when the component is installed. The `framework.*` keys keep working as aliases; `asset`, `translation` and `workflow` become root keys. `debug:config framework <key>` no longer resolves those sections.
- **HTTP errors below 500 are logged at `warning`**, no longer `error`: a `fingers_crossed` handler with `action_level: error`, and the Sentry alerting built on it, stop firing on 4xx. Check that no alert relied on them.
- **EventDispatcher**: `addListener()` / `addSubscriber()` on the container's dispatcher are deprecated, tests included (register a listener service, or use a `ScopedEventDispatcher`). The autowiring alias `Symfony\Component\EventDispatcher\EventDispatcherInterface` is deprecated: type `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`.
- **Secrets vault**: without a `config/secrets/` directory, env vars are no longer loaded from it, and the variable behind `framework.secret` is no longer derived from `SYMFONY_DECRYPTION_SECRET`: define it.
- **Scheduler**: leaving `framework.scheduler.use_messenger_routing` unset is deprecated (true in 9.0), and `Schedule::with()` is deprecated (clone or build a new schedule) and now returns an empty schedule.
- **Serializer**: denormalizing a property from its PHP name when `#[SerializedName]` or a name converter maps it to another key is deprecated.
- **RateLimiter**: `CompoundLimiter` stops at the first limiter that rejects, so list them from the most specific to the most global.

Merged between the 29 September and 28 October watches, still no beta tag:

- **Tests**: `KernelTestCase` reads `KERNEL_CLASS`, `APP_ENV` and `APP_DEBUG` from `$_SERVER` before `$_ENV`, so `phpunit.xml.dist` declares them with `<server>`, not `<env>`.
- **`#[RateLimit]` consumes on `kernel.controller`, before `#[IsGranted]`**: a request denied by the voter still spends a token. Size the public limiters with that in mind.
- **`#[IsGranted]` whose subject reads a `#[MapRequestPayload]` argument** now maps and validates the payload before voting, so an invalid payload answers 422 before a 403.
- **Error bodies from `ProblemNormalizer` are served as `application/problem+json`**: the functional tests that assert the content type, and the OpenAPI drift gate (`symfony-guidelines.md` §13), see it.
- **doctrine-bundle ≥ 3.3.3** stops extending the FrameworkBundle cache warmer that 8.2 deprecates: take it before the upgrade.

Also worth watching, outside the core:

- **VichUploader 3.0 is stable** (7 September 2026), 2.x in maintenance only: the migration and its traps are a rule now (`symfony-guidelines.md` §4, File uploads).
- **Doctrine ORM 3.7** deprecates string sort directions in favour of the `SortDirection` enum, and `TypedExpression` in favour of `ExpressionWithReturnType`; it brings cursor-based pagination. It needs **EasyAdmin ≥ 5.6**, which silences the `SortDirection` deprecation. **DBAL 4.5** adds DateTime types stored in UTC and deprecates `SimpleArrayType` and `Column::getType()` (use `getTypeName()`).
- **Doctrine ORM 4**: still no alpha, only a `4.0.x-dev` branch, with no commit since September 2026 and a 4.0.0 milestone without a due date; the direction is confirmed (PHP 8.4 minimum, built entirely on native lazy objects).
- **PHP 8.6 and PostgreSQL 19** are both due on 19 November 2026 upstream. Neither moves the reference until Clever publishes it (`symfony-guidelines.md`, the PHP section and §8).
- **Twig 3.29** brings documentation comments and deprecates macro calls without parentheses and duplicate macro definitions.
