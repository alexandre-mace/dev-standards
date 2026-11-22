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

**Last watch: 22 November 2026.**

## Security floors: the advisories behind them

| Floor | Advisories | What they close |
|---|---|---|
| PHP runtime ≥ 8.5.13 | 17 advisories of 19 November 2026, six critical: FPM request smuggling (CVE-2026-129289), remote code executions through the session serializer (CVE-2026-115560), partially overwritten session files (CVE-2026-126714) and a timezone cache use-after-free (CVE-2026-129496), plus two use-after-free in 8.5 only (CVE-2026-129474, CVE-2026-129182) | Clever published 8.5.13 the same day, and lifted the `zend.exception_ignore_args=On` it had set as a mitigation since 8.5.12 |
| | CVE-2026-109618, CVE-2026-109619 (8.5.12, 21 October 2026, CVSS v4 9.2) | two unauthenticated remote code executions on any PHP serving web requests; Clever published 8.5.12 the same day and redeploys PHP apps onto it automatically |
| | 8.5.11 | TLS hostname verification falling back to the CN, credentials leaking across origins on HTTP stream redirects, an FPM IPv6 ACL bypass |
| | CVE-2026-17543 (8.5.9) | a SQL injection in ext-pgsql through `pg_insert()`/`pg_update()`/`pg_select()`/`pg_delete()` |
| Symfony ≥ 8.1.11 | 21 advisories of 4 November 2026, six high: `DataUriNormalizer` reading any server file (CVE-2026-121623), the Serializer building `UploadedFile` from request data (CVE-2026-118515), a YAML exponential parse (CVE-2026-123792), a Filesystem `Path` escaping its base through `://` (GHSA-6prc-h956-hxjq), a `ScopingHttpClient` credential leak (CVE-2026-121971), X509 DN parsing (CVE-2026-118513) | the mediums include a HEAD request bypassing method-restricted `access_control` (GHSA-jrf8-gvm4-7cgh) and dotted keys bypassing `#[Ignore]` in `ObjectNormalizer` (GHSA-hqp2-7rr8-hf26) |
| | GHSA-7rpj-w9pf-39rr, CVE-2026-109449 (8.1.9, 14 October 2026) | a deeply nested expression crashes the process, and `#[Assert\ExpressionSyntax]` hands that parser to user input; the first fix, in 8.1.6, was incomplete |
| | 8.1.6, 8.1.7 | a Yaml inline lexer recursion reachable through `#[MapRequestPayload]` when the Yaml component is installed, and a ReDoS on `Cache-Control` |
| EasyAdmin ≥ 5.10.2 | twelve advisories of 17 November 2026 (GHSA-4374-hggj-v34v and following, listed in the v5.10.2 release notes) | CRUD routes ignoring the dashboard's `routeOptions` and bypassing `access_control`, stored XSS from an SVG in `ImageField`, an upload stored outside its directory |
| EasyAdmin ≥ 5.5.1 | GHSA-g2fm-8hr4-j82h (August 2026, CVSS 8.1) | the `routeName` of custom actions substituted after the firewall was evaluated, bypassing URL-pattern `access_control` |
| Twig ≥ 3.27 | CVE-2026-48805 to CVE-2026-48808 | |
| PHPUnit ≥ 13.4.4 | GHSA-9vw2-f4vp-x7gf | PHPT tests run with coverage in a fixed-name `/tmp/phpunit-phpt` directory another local user can plant code in |
| Vite ≥ 8.3.7 | GHSA-p4f5-76q4-ffq7 (10 November 2026, critical, CVSS v4 9.1) | remote code execution through the `__vite-browser-external` virtual module on a dev server exposed to the network |
| | three advisories of 6 October 2026 (8.3.3) | files outside `server.fs.allow`, the first bytes of any file through `?vite-wasm-instance`, cross-origin script execution through a custom middleware |
| Zod ≥ 4.6.6 | GHSA-mrhx-c7xc-m3w5 (high) | `$ZodArray` accumulates one issue per failing element with no cap, so a large array against a schema without a length limit runs the process out of memory |
| PostgreSQL 18.7 / 17.12 | 52 CVEs (upstream 12 November 2026, published by Clever on 13 November) | the reindexing after an in-place update and the `NOT NULL NO INHERIT` constraints that make a migration to 18 fail: the guideline names them (§8) |
| FrankenPHP ≥ 1.13.5 (the upgrade path, §1) | 17 advisories of 5 November 2026, GHSA-j99q-3wr8-xj43 the only high one | `php_server` running non-PHP files as PHP, and sixteen medium or low issues |

## Decisions re-validated

- **No API Platform**: re-validated in October 2026, still true with API Platform 5.0, a
  cleanup major that changes nothing in the reasoning.
- **PHP-FPM, not FrankenPHP**: benchmarks checked in August 2026. October 2026 confirmed
  the price: seventeen FrankenPHP advisories in one month, most of them in worker mode;
  November added seventeen more on a single day. Re-validated on 22 November 2026.
  Re-assess at every watch.
- **No Rector, no Mago**: decided on 30 October 2026, Mago at 1.59. Re-validated on
  22 November 2026 at Mago 1.61: its analyzer plugins still cover no Symfony container and
  no Doctrine mapping.

## Dates and issues to follow

- **PHP 8.6 and PostgreSQL 19** shipped upstream on 19 November 2026. Clever offers
  neither at the 22 November watch (`CC_PHP_VERSION` stops at 8.5, the add-ons at 18.7):
  its publication is what moves the reference.
- **sentry-symfony 6.0** is in beta (6.0.0-beta2, 19 November 2026).

## Symfony 8.2 radar (8.2.0-BETA2 on 13 November 2026, no RC yet)

Deprecations and behaviour changes **already merged** on the 8.2 branch (UPGRADE-8.2.md), worth anticipating now:

- **Hardened Serializer**: union-typed collections now denormalize their elements, and arrays denormalized into `list`-typed properties will have to satisfy `array_is_list()` (an exception in 9.0). Worth testing on our collection-carrying `#[MapRequestPayload]` DTOs.
- `#[IsCsrfTokenValid]` will return a **403** (`InvalidCsrfTokenException`) instead of redirecting to the login page.
- `File` constraint: `mimeTypes` and `extensions` will be checked independently (no more MIME restriction inferred from the extension).
- `framework.ide` deprecated in favour of the `SYMFONY_IDE` variable.

Merged since, still on the 8.2 branch (no beta tag at the 29 September watch):

- **FrameworkBundle is split** into bundles shipped by their own components (MessengerBundle, MailerBundle, SerializerBundle, ValidationBundle, RouterBundle and about twenty more), registered automatically when the component is installed. The `framework.*` keys keep working as aliases; `asset`, `translation` and `workflow` become root keys. `debug:config framework <key>` no longer resolves those sections.
- **HTTP errors below 500 are logged at `warning`**, no longer `error`: a `fingers_crossed` handler with `action_level: error`, and the Sentry alerting built on it, stop firing on 4xx. Check that no alert relied on them.
- **EventDispatcher**: `addListener()` / `addSubscriber()` / `removeListener()` / `removeSubscriber()` on the container's dispatcher are deprecated, tests included (register a listener service, or use a `ScopedEventDispatcher`). The autowiring alias `Symfony\Component\EventDispatcher\EventDispatcherInterface` is deprecated: type `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`.
- **Secrets vault**: when the project configures a vault directory that is missing at build time, env vars are no longer loaded from it, and the variable behind `framework.secret` is no longer derived from `SYMFONY_DECRYPTION_SECRET`: define it.
- **Scheduler**: leaving `framework.scheduler.use_messenger_routing` unset is deprecated (true in 9.0), and `Schedule::with()` is deprecated (clone or build a new schedule) and now returns an empty schedule.
- **Serializer**: denormalizing a property from its PHP name when `#[SerializedName]` or a name converter maps it to another key is deprecated.
- **RateLimiter**: `CompoundLimiter` stops at the first limiter that rejects, so list them from the most specific to the most global.

Merged between the 29 September and 28 October watches, still no beta tag:

- **Tests**: `KernelTestCase` reads `KERNEL_CLASS`, `APP_ENV` and `APP_DEBUG` from `$_SERVER` before `$_ENV`, so `phpunit.xml.dist` declares them with `<server>`, not `<env>`.
- **`#[RateLimit]` consumes on `kernel.controller`, before `#[IsGranted]`**, unless its key is a Closure or an Expression reading `args`: a request denied by the voter still spends a token. Size the public limiters with that in mind.
- **`#[IsGranted]` whose subject reads a `#[MapRequestPayload]`, `#[MapQueryString]` or `#[MapUploadedFile]` argument** now maps and validates it before voting, so an invalid payload answers 422 before a 403.
- **Error bodies rendered by `SerializerErrorRenderer` are served as `application/problem+json`**: the functional tests that assert the content type, and the OpenAPI drift gate (`symfony-guidelines.md` §13), see it.
- **doctrine-bundle ≥ 3.3.3** stops extending the FrameworkBundle cache warmer that 8.2 deprecates: take it before the upgrade.

Merged between the 28 October and 22 November watches (BETA1 on 4 November, BETA2 on 13 November):

- **Bundle options that select services resolve their env vars at compile time** (`security.firewalls.*.stateless`, `router.utf8`, `framework.test`, `asset_mapper.server`…): changing such a variable means rebuilding the container, not restarting PHP.
- **Messenger `BatchHandlerTrait`**: a full batch is processed in a dispatch of its own, no longer inside the transaction of the message that filled it.
- **A login with an unknown username costs one password hash**, so a credential-stuffing burst costs CPU: `login_throttling` is what bounds it.
- The deprecation of `DoctrinePingConnectionMiddleware` was reverted: it stays, since it resets closed entity managers.

Also worth watching, outside the core:

- **VichUploader 3.0 is stable** (7 September 2026), 2.x in maintenance only: the migration and its traps are a rule now (`symfony-guidelines.md` §4, File uploads).
- **Doctrine ORM 3.7** deprecates string sort directions in favour of the `SortDirection` enum, and `TypedExpression` in favour of `ExpressionWithReturnType`; it brings cursor-based pagination. It needs **EasyAdmin ≥ 5.6**, which silences the `SortDirection` deprecation. **DBAL 4.5** adds DateTime types stored in UTC and deprecates `SimpleArrayType` and `Column::getType()` (use `getTypeName()`).
- **Doctrine ORM 3.8** (14 November 2026) makes the second-level cache require a marker package (`doctrine/orm-plugin-second-level-cache`), so Doctrine can count its users before deciding to move it out or drop it, and keeps the `INDEX BY` keys in `OffsetPaginator` results; **DBAL 4.6** deprecates the mutable date/time types, and schema introspection now maps to the immutable ones.
- **Doctrine ORM 4**: still no alpha, only a `4.0.x-dev` branch (last commit on 14 November 2026) and a 4.0.0 milestone without a due date; the direction is confirmed (PHP 8.4 minimum, built entirely on native lazy objects).
- **Twig 3.29** brings documentation comments and deprecates macro calls without parentheses and duplicate macro definitions.
