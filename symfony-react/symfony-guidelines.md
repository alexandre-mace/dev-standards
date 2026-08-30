# Symfony Guidelines

> **Last watch: 30 August 2026** (`/gap-sota`), start from this date on the next run. Reference versions verified: PHP 8.5.10 (project floor 8.4; **≥ 8.5.9 required**, security) · Symfony 8.1.5 (8.0 unmaintained; 8.2 expected Nov. 2026, see Radar) · Doctrine ORM 3.6.8 / doctrine-bundle 3.3.1 / DBAL 4.4.4 · PHPUnit 13.3 (dama 8.6.0 compatible: see §13) · PHPStan 2.2.9 (Turbo) · PHP-CS-Fixer 3.95 (`@Symfony` ruleset; `@PHP85Migration` available) · Foundry 2.12.1 (**≥ 2.10.3**) · dama 8.6 · Eris 1.1 · EasyAdmin 5.5.1 (**≥ 5.5.1 required**, security) · Twig ≥ 3.27 (currently 3.28) · sentry-symfony 5.13 · nelmio/api-doc-bundle 5.11 · PostgreSQL ≥ 17 (18.3 available on CleverCloud) · Symfony Reprise 1.1 (Vite integration, see reactony §6).

## Routing: what to read for which task

Read the **playbook + Definition of Done** (below) and the **anti-patterns (§18)** for every task; then only the sections that apply, not the whole file.

| Task | Sections |
|---|---|
| New API route | §3 Controller · §4 Dto · §2 Domain |
| File upload | §4 (+ reactony §2) |
| Business rule / calculation | §2 Domain · §15 What to create when |
| Entity / DB query | §8 Entity · §7 Repository |
| External API / rate limit / webhook | §6 Api · Logging & Sentry |
| Console command / cron | §11 Command · §17 Scheduler |
| Async external call | §16 Messenger |
| Writing tests | §13 Testing (+ reactony §9 on the frontend) |
| Before committing | §14 QA |

## Principles

1. **Domain/ holds the rules**: it decides, validates, computes. It depends on nothing external.
2. **Service/ does the work**: it persists, sends, calls APIs. It makes things happen.
3. **The controller orchestrates**: it wires Domain + Service + Api together. Extract into Service/ when it repeats or overflows.
4. **Native PHP enums**: for any finite set of values (statuses, types, categories)
5. **Constructor property promotion + `readonly`**: modern injection, `private readonly` properties, no manual assignment
6. **No pre-emptive abstraction**: no interface, no ValueObject, no Aggregate unless genuinely needed
7. **`#[IsGranted]`**: method-level security on API routes, no URL-pattern `access_control` for `/api/`

### Deliberate architecture decisions: what we do NOT use, and why

Written down so it isn't re-litigated every session (in an AI-first codebase, the model would otherwise propose these migrations on a loop).

- **No API Platform.** The app is **hybrid** (an API plus many server-rendered Twig pages and EasyAdmin), the endpoints are *action-shaped* rather than CRUD-resource, and the OpenAPI→TS pipeline (`make types`) already exists. API Platform would add a second paradigm plus state processors for any bespoke logic, so **more** concepts, not fewer. Our `#[MapRequestPayload]` + ObjectMapper + `#[Serialize]` controllers get about 80% of its concision without the cost. (Re-validated June 2026: still true with API Platform 4.2.)
- **No RSC / React Server Components** (see `reactony.md`): Symfony + Twig **is** the server layer already; the React islands are the intentional client leaves. Bolting RSC on means running a Node rendering server next to PHP, for a role PHP already fills.
- **PHP-FPM, not FrankenPHP (for now).** Checked against serious benchmarks (August 2026): FrankenPHP's classic mode is at parity with FPM, and worker mode only buys back the bootstrap on a SQL-dominated app (1.2x to 1.5x in practice, not the 3x to 4x of hello-world benchmarks), at the price of a whole class of persistent-state bugs (memory leaks, stateful services) and higher RAM. At moderate traffic, FPM costs nothing measurable. The upgrade path, the day a trigger appears (CPU spikes, a Mercure or realtime need, shrinking an instance): **classic** mode first (GA CleverCloud runtime, HTTP/3 + Early Hints, low risk), then worker mode under conditions (sentry-symfony ≥ 5.12, an audit of stateful services and `ResetInterface`, `max_requests` around 1000, a RAM alert, the least critical platform first). Re-assess at every watch.

---

## Feature playbook: one-shot

The standard sequence for implementing a full-stack feature. Following it in order avoids round-trip questions and lands a coherent feature the first time.

### 1. Model the backend contract

- **Reading**: identify the entity and its read `#[Groups]` (or write a Formatter for computed URLs, aggregates or cross-entity data)
- **GET filter**: a DTO in `Dto/` with `#[MapQueryString]`
- **Writing**:
  - Payload maps 1:1 to the entity → `#[MapRequestPayload]` on the entity + `#[Assert\...]` on the fields
  - Subset of a large entity → allowlist DTO (`#[Map(target: Entity::class)]`) + `ObjectMapper`
  - No entity → plain DTO
- **File upload**: a **flat** DTO with a `?UploadedFile` property + `#[Assert\File(maxSize: 'XM')]` through `#[MapRequestPayload]` (SF 8.1). `#[MapUploadedFile]` as the fallback. The identifier goes in the route.

### 2. Controller route

- `#[Route(path: '/api/...', methods: [...], format: 'json')]`: `format: 'json'` is **mandatory**
- `#[IsGranted('ROLE_USER')]` on the method or the class: mandatory on `/api/`
- Simple CRUD → EntityManager directly in the controller. Business logic → extract Domain + Service.
- Non-critical external call → catch + `logger->error()` + swallow (the user flow must not break because an upstream is flaky)

### 3. Type generation

- `make types` (or the project equivalent) → TS types + Zod + SDK + queryOptions/mutationOptions into `assets/lib/api/`
- CI: `make types && git diff --exit-code openapi.yaml assets/lib/api/` catches the drift

### 4. React component

- **Multi-field with validation**: RHF `Controller` + shadcn `Field` + `zodResolver(zSchema from zod.gen)` + `useMutation` + `handleSdkError` + per-field `form.setError`
- **Simple action / toggle / inline edit**: `useMutation` + SDK + `handleSdkError` + toast
- **Dynamic read**: `useQuery({ ...getXOptions({...}) })`
- **Twig mount point**: wrap the root in `<QueryClientProvider>` + `<Toaster>` (factor it into a reusable `<AppProviders>`)
- **Upload**: guard `file.size` on the frontend, with the limit aligned to the backend `Assert\File(maxSize)`

### 5. Cache invalidation

- Any mutation that changes data read elsewhere → `queryClient.invalidateQueries({ ...getXOptions(...) })` in `onSuccess`

### 6. Tests

- **Functional PHPUnit** required for every non-trivial new API route (HTTP contract + DB state)
- **Pure domain** → unit PHPUnit with no mocks + Foundry to build the entities
- **Money math** (calculators, fees, tiers) → property-based through Eris
- **Playwright E2E** required for every new user journey (funnel, payment, signature)
- **React form component** (Zod + RHF) → Vitest + RTL + MSW for the 422 violations
- **Simple React component** (shadcn passthrough) → skip
- **Refactoring a fragile component** → safety net first: tests that pin the current behaviour **before** touching it
- **OpenAPI diff** in CI: `make types && git diff --exit-code openapi.yaml assets/lib/api/`

Details and setup: section 13.

### 7. Quality gate

`/quality` (or the equivalent) must be green before merge: PHPStan (level 9+), PHP-CS-Fixer, `doctrine:schema:validate --skip-sync`, `lint:container`, ESLint, Prettier, `tsc --noEmit`.

### Definition of Done

A feature is only "done" when **every** one of these is green:

- [ ] `/quality` passes
- [ ] `make types` produces no drift in `assets/lib/api/` (CI gate: `git diff --exit-code`)
- [ ] `doctrine:schema:validate --skip-sync` OK
- [ ] `lint:container` OK
- [ ] A functional test written for every non-trivial new API route
- [ ] A property-based test (Eris) added for any new money / fees / tier calculation
- [ ] A Playwright spec added for every new user journey
- [ ] `/live-test` run: golden path plus at least one edge case exercised in a browser, console and network clean
- [ ] `#[IsGranted]` and `format: 'json'` present on the new `/api/` routes
- [ ] No anti-pattern from the dedicated section (notably `useEffect+fetch`, `$request->get()`, `new RetryableHttpClient`, `any` outside the RHF `setError`)

---

## PHP 8.4+ (8.5 runtime)

PHP 8.5 is the **current stable** (GA 20 Nov. 2025) and production runs on 8.5; the `composer.json` floor stays `>= 8.4`. The 8.4 features to use everywhere: explicit nullables (`?Type $param = null`, the implicit form is deprecated), asymmetric visibility (`public private(set)`), property hooks, `array_find()`/`array_any()`/`array_all()`.

**PHP 8.5 features usable now**: the pipe operator (`$slug = $titre |> trim(...) |> strtolower(...)`), `clone($obj, ['prop' => $val])` for readonly withers, `#[\NoDiscard]` on methods whose return value must not be ignored, `array_first()`/`array_last()`. To avoid (deprecated in 8.5): non-canonical casts (`(integer)`, `(boolean)`, `(double)`) and `__sleep()`/`__wakeup()` (soft-deprecated in favour of `__serialize()`/`__unserialize()`).

**Security floor: runtime ≥ 8.5.9.** The 8.5.8 (July 2026, OpenSSL CVE) and above all 8.5.9 patches are security releases; 8.5.9 fixes CVE-2026-17543 in particular, a SQL injection in ext-pgsql through `pg_insert()`/`pg_update()`/`pg_select()`/`pg_delete()` (also fixed in 8.4.24 for the 8.4 branch).

## Symfony 8.2 radar (November 2026)

Deprecations and behaviour changes **already merged** on the 8.2 branch (UPGRADE-8.2.md), worth anticipating now:

- **Hardened Serializer**: union-typed collections now denormalize their elements, and arrays denormalized into `list`-typed properties will have to satisfy `array_is_list()` (an exception in 9.0). Worth testing on our collection-carrying `#[MapRequestPayload]` DTOs.
- `#[IsCsrfTokenValid]` will return a **403** (`InvalidCsrfTokenException`) instead of redirecting to the login page.
- `File` constraint: `mimeTypes` and `extensions` will be checked independently (no more MIME restriction inferred from the extension).
- `Schedule::with()` deprecated (clone or build a new schedule); `framework.ide` deprecated in favour of the `SYMFONY_IDE` variable.

Features announced for 8.2: a `concurrency` option on Messenger (parallel message processing), rate-limited Mailer transports, a `Cron` constraint, single-use signed URLs.

Also worth watching, outside the core:

- **VichUploader v3 imminent**: 2.10 is announced as the last minor of the 2 branch, and 3.0.0-rc4 is published. Plan the upgrade.
- **Doctrine ORM 4**: no alpha published; the direction is confirmed (PHP 8.4 minimum, built entirely on native lazy objects), release hoped for late 2026 / early 2027.
- **Twig 3.29** will bring documentation comments; **Twig 4 is still in alpha**, don't get ahead of it.

---

## 1. `src/` structure
```
src/
├── Controller/          # Orchestration: wires Domain + Service + Api
├── Domain/              # Pure business rules (see section 2)
├── Dto/                 # DTOs for API requests (MapRequestPayload, MapQueryString)
├── Entity/              # Doctrine entities
├── Repository/          # Doctrine queries (specialised ones included)
├── Service/             # Execution: persist, API calls, uploads, PDF... (see section 5)
├── Api/                 # Authenticated external APIs (Hubspot, Discord...)
├── Message/             # DTOs for Messenger (see section 15)
├── MessageHandler/      # Async handlers for those messages (see section 15)
├── Command/             # Console commands (with #[AsCronTask] for the scheduler)
├── Form/                # Symfony Form types
├── EventListener/       # Doctrine/HTTP listeners
├── Factory/             # Entity creation with complex initialisation
├── Twig/                # Twig extensions
└── Security/            # Auth handlers
```

### Direction of dependencies

```
Controller  →  Domain + Service + Api + Repository + Dto
Command     →  Domain + Service + Api + Repository
Service     →  Domain + Api + Repository
Domain      →  Entity only (+ other Domain)
```

Domain/ depends on nothing else. Service/ may call Domain/. The controller wires it all together.

---

## 2. Domain/: the rules

Every subfolder of `Domain/` is a **business context**.

### Purity is the direction of dependencies, not the absence of attributes

The rule that matters: **Service → Domain ✓**, **Domain → Service ✗**. A Service fetches the data and calls a pure Domain method; Domain never asks a Service to go and fetch something.

Concretely, a Domain constructor **does not inject**: a Repository, `EntityManagerInterface`, `HttpClientInterface`, `LoggerInterface`, `Filesystem`, another `Service`, an `Api/` class, `UrlGeneratorInterface`. Everything Domain needs is passed as a method parameter.

Symfony framework attributes, on the other hand, are **freely allowed in Domain**, the same way `Entity/` uses `#[ORM\Column]`:

- `#[Assert\…]` (Validator)
- `#[Groups]` (Serializer)
- `#[OA\…]` (Nelmio OpenAPI)

That is declarative metadata, not a runtime dependency. The project is Symfony and stays Symfony: no effort spent staying "framework-agnostic".

**Domain/ answers questions like:** "can this step be completed?", "what is this user's score?", "which manager owns this department?"

**If a class needs to go and fetch data** (repository, API, filesystem), it belongs in Service/. If it also holds pure business rules, extract those rules into a separate Domain/ class and call them from the Service.

### Business DTOs in Domain

DTOs that represent **business data structures** (an endpoint's input or output, a cron payload, an exchange format with a partner) belong in `Domain/<Context>/`, next to the Rules and Calculators of the same context. They are domain objects, just like an entity. They can carry `#[Assert\…]`, `#[OA\…]` and `#[Groups]` without a problem.

```php
// src/Domain/Import/FarmImportRequestInput.php
namespace App\Domain\Import;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class FarmImportRequestInput
{
    #[Assert\NotBlank, Assert\Uuid]
    #[OA\Property(type: 'string', format: 'uuid')]
    public string $farmUuid;
}
```

`src/Dto/` stays in use for **generic technical DTOs** with no clear business context (GET filters reused across several entities, cross-cutting orchestration payloads). When in doubt: `Domain/<Context>/` when the DTO describes a business exchange, `src/Dto/` when it describes a technical one.

### PHP enums

For any finite set of business values. The label lives in the enum, through `label()`.

```php
enum AdvertStatus: string
{
    case Published = 'published';
    case Pending = 'pending';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Publié',
            self::Pending => 'En attente',
            self::Refused => 'Refusé',
        };
    }

    public static function forForm(): array
    {
        return array_combine(
            array_map(fn(self $c) => $c->label(), self::cases()),
            array_map(fn(self $c) => $c->value, self::cases()),
        );
    }
}
```

Doctrine supports enums natively:

```php
#[ORM\Column(enumType: AdvertStatus::class)]
private AdvertStatus $status = AdvertStatus::Pending;
```

**Naming**: singular (`AdvertStatus`, `MemberType`).

#### Enums exposed in the back office (EasyAdmin v5)

Two rules to avoid fighting the framework:

1. **Let EA handle the round-trip.** As soon as the Doctrine column has `#[ORM\Column(enumType: MyEnum::class)]`, `ChoiceField::new('myField')` detects the cases on its own. Don't add `setChoices()` or `choice_value()`: it breaks the edit form. (We hit this: a redundant `setChoices()` forced a polymorphic `choice_value` to patch it back up.)

2. **Implement `Symfony\Contracts\Translation\TranslatableInterface` on the enum** so EA calls `trans()` and shows a human label instead of the raw value. Reuse `label()`:

   ```php
   use Symfony\Contracts\Translation\TranslatableInterface;
   use Symfony\Contracts\Translation\TranslatorInterface;

   enum AdvertStatus: string implements TranslatableInterface
   {
       case Published = 'published';
       // ...

       public function label(): string { /* ... */ }

       public function trans(TranslatorInterface $translator, ?string $locale = null): string
       {
           return $this->label();
       }
   }
   ```

   No translation files needed: `trans()` returns the string directly.

3. **Never a `TextField` on a property that returns an enum.** EA's `text.html.twig`
   renders `title="{{ field.value }}"` with the raw value, so the Twig escaper throws
   "Object of class X could not be converted to string". Neither `TranslatableInterface`
   nor `->formatValue()` protects you: both act on `formattedValue` while `field.value`
   stays the enum. Still true in EA 5.5.1. The crash only fires when the index holds at
   least one row and someone opens the page, so a rarely visited CRUD carries a silent
   bomb. A custom `->setTemplatePath()` is the tolerated alternative, and it reads
   `field.value.label`, never `{{ field.value }}`.

   The whole family is worth one architecture test: walk the getters of every
   `CrudController` by reflection and fail on any enum-returning property configured as
   something other than a `ChoiceField` without a custom template.

#### Migrating a column from `string` to `enumType`

The getter now returns a `BackedEnum`, so every string comparison built on it goes
**silently false**. No error, no log, the branch just stops running. On one project the
`{% if entity.type == 'expense' %}` left behind by such a migration stayed false for six
hours in production, until an unrelated crash on `|title` revealed it.

Sweep, in this order:

1. `grep -rn "\.field ==\|\.field|title\|\.field|capitalize" templates/`
2. `entity.field == 'x'` becomes `entity.field.value == 'x'`, or
   `entity.field == constant('App\\Domain\\X::Case')` to be strict.
3. `entity.field|title` becomes `entity.field.label`. `|title` and `|capitalize` call
   `mb_convert_case()`, which throws on an object.
4. The EasyAdmin CRUDs: a legacy `ChoiceField::new('f')->setChoices(EnumX::forForm())`
   submits strings, and the typed setter throws a `TypeError`. Drop the `setChoices()`
   (rule 1 above) rather than patching it.

A render test on the show and edit pages catches the crashes. It does not catch the
comparisons gone false, which need an assertion on what the page displays.

#### Custom actions (EasyAdmin v5): never read `entityId` from the query

With EA v5's *pretty URLs*, the `entityId` of a custom action (`#[AdminRoute(path: '/{entityId}/…')]`) is a **route parameter**, not a query param. Reading it from the query always returns `null` (symptom: the action believes no entity is selected). (Since EA **5.1**, pretty URLs are the **default mode** and `usePrettyUrls()` has been removed, so stop calling it.)

```php
// ❌ BROKEN since the v5 migration: query->get('entityId') is always null
$id = $context->getRequest()->query->get('entityId');

// ✅ EA has already resolved the entity into the context
$entity = $context->getEntity()->getInstance();
// or type-hint the entity in the signature: public function foo(MyEntity $e): Response
```

Testing an admin action: the `admin` firewall is separate, so use a form login `testadmin`/`testpass` (`config/packages/test/security.yaml`); `loginUser()` doesn't work reliably with an in-memory provider.

⚠️ **Security: EasyAdmin ≥ 5.5.1 required** (GHSA-g2fm-8hr4-j82h, August 2026, CVSS 8.1): the `routeName` of custom actions was substituted **after** the firewall was evaluated, allowing URL-pattern `access_control` rules to be bypassed. The `#[IsGranted]` attributes on the controllers stayed effective: our "security by attribute, not by URL pattern" rule was exactly the defence in depth that paid off here. In passing, EA 5.2 to 5.5 brought Twig components (Switch, Modal, Pagination, Sidebar), an official Theming API, filters and sorting on nested properties, and tab persistence.
### Reference data

For large data sets (departments, regions) that aren't enums:

```php
final class Departments
{
    public const ALL = ['01' => 'Ain', '02' => 'Aisne', /* ... */];
    public static function get(string $code): string { /* ... */ }
}
```

### Business rules (conditions, validations)

```php
class StepRules
{
    public function isCompletable(UserStep $userStep): bool
    {
        return $userStep->getUserActions()
            ->filter(fn(UserAction $a) => !$a->getIsCompleted() && $a->getRelatedAction()->getIsMandatory())
            ->isEmpty();
    }
}
```

### Calculator

Business calculations with no side effects. **A Calculator receives all its data as parameters**: it never depends on a repository or a service. That is what makes it pure and testable without mocks.

```php
class ScoreCalculator
{
    public function calculate(array $answers): array { /* ... */ }
}
```

**If a calculation needs data from a repository**, don't put the repository inside the Calculator. Write a Service/ that fetches the data and calls the Calculator:

```php
// Domain/: pure, testable
class ScoreCalculator
{
    public function calculate(array $answers): array { /* ... */ }
}

// Service/: orchestrates: fetches the data, delegates the calculation
class ScoreService
{
    public function __construct(
        private readonly AnswerRepository $answerRepository,
        private readonly ScoreCalculator $calculator,
    ) {}

    public function computeForUser(User $user): array
    {
        $answers = $this->answerRepository->findByUser($user);
        return $this->calculator->calculate($answers);
    }
}
```

### Reference data vs Rules

- **Reference data**: static data with no logic (arrays, constants). A list of departments, region codes.
- **Rules**: conditions and thresholds, with methods returning a boolean or a decision. `isEligible()`, `shouldNotify()`.

### Resolver

Resolves a business value from a key.

```php
class ProjectManagerResolver
{
    public function getByDepartment(string $department): ?string { /* ... */ }
}
```

### Domain exceptions

```php
class StepNotCompletableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct("Toutes les actions obligatoires ne sont pas complétées.");
    }
}
```

---

## 3. Controller: the orchestrator

The controller wires Domain + Service + Api together. It is the first place orchestration goes.

For **simple CRUD** (persist + flush with no logic), the controller uses the EntityManager directly. No need to route a plain save through a Service/.

```php
// Simple CRUD → EntityManager straight in the controller
$this->entityManager->persist($notification);
$this->entityManager->flush();
return $this->json(['ok' => true], 201);

// Business logic → Domain + Service
public function completeStep(UserStep $userStep): Response
{
    // Business rule (Domain)
    if (!$this->stepRules->isCompletable($userStep)) {
        $this->addFlash('error', 'Actions obligatoires incomplètes.');
        return $this->redirectToRoute('...');
    }

    // Execution (Service)
    $this->stepHandler->complete($userStep);

    // External call (Api)
    $this->notificationApi->notify($userStep->getUser());

    return $this->redirectToRoute('...');
}
```

### URL naming convention
One rule, no exception: **one canonical name per feature, spelled out in French for user-visible URLs, English for the API**, never an abbreviation on the URL side.

| Surface | Convention | Example |
|---|---|---|
| Public pages | full French, namespaced by feature | `/aides-agricoles/diagnostic`, `/aides-agricoles/{slug}` |
| Dashboard pages | `/tableau-de-bord/` prefix + the same feature name | `/tableau-de-bord/aides-agricoles/{slug}` |
| Embeds | `/embed/` prefix + the feature name | `/embed/aides-agricoles` |
| REST API | `/api/` prefix + the plural feature name + plural English resources | `/api/aides-agricoles/leads`, `/api/aides-agricoles/diagnostics`, `/api/aides-agricoles/auth/login` |
| Symfony route names | `app_<feature>_<action>` (pages) or `api_<feature>_<resource>_<action>` (API) | `app_aides_agricoles_diagnostic_page`, `api_aides_agricoles_diagnostics_create` |
| Code (classes, folders, namespaces) | an abbreviation is tolerated where one already exists | `AidesAgriController`, `assets/components/aides_agri/` |

**Rules**:
- **No partner branding in the URL** (never `/diagnostic-aides-<partner>-<company>`, always `/aides-agricoles/diagnostic`).
- **Plural for collections** (`/leads`, `/aides`, `/diagnostics`), singular only when the resource has no "list" (`{slug}` is already the identifier).
- **HTTP verbs rather than verbs in the URL**: `POST /diagnostics` is the create, never `/diagnostics/save`.
- **Logical sub-resources**: `/diagnostics/chat`, `/auth/login`, rather than everything flat (`/check-email`, `/login`, `/register` at the feature root).
- **Catch-all last**: with both `/aides-agricoles/{slug}` and `/aides-agricoles/signaler-une-aide`, the slug swallows the other. Either declare the specific route first, or put `priority: -10` on the catch-all.
- **Internal code ≠ URL**: it is fine for `AidesAgriController` to serve `/aides-agricoles/...`. URL consistency wins, and the code can keep its historical abbreviation to avoid a noisy refactor at every product rename.

### `format: 'json'` is mandatory

**Every** `/api/` route must carry `format: 'json'`. Without it, 422 errors arrive as HTML instead of JSON and the frontend cannot parse them. A frequent source of bugs.

```php
#[Route('/api/mon-endpoint', methods: ['POST'], format: 'json')]
```

### API response: `#[Serialize]` (SF 8.1)

The mirror image of `#[MapRequestPayload]` (input): `#[Serialize]` is the **output**. The controller returns the DTO, array or entity directly; the attribute handles JSON encoding, content negotiation and the HTTP status. No more `$this->json(...)` or `new JsonResponse(...)`.

```php
/** @return array<int, ModelSave> */
#[Route('/api/farm-model/saves', methods: ['GET'], format: 'json')]
#[Serialize(context: ['groups' => ['model_save:read']])]
public function getModelSaves(): array
{
    return $this->getUser()->getModelSaves()->toArray();
}
```

Options: `#[Serialize(code: 201, headers: [...], context: ['groups' => [...]])]`. The `#[Groups]` behave **exactly** as with `$this->json(..., ['groups' => ...])`, verified on a real case: byte-identical output, **zero drift** in OpenAPI, SDK or Zod.

**Convention: the default for *new* `/api/*` endpoints returning a DTO or entity.** The attribute is new (8.1, May 2026), so **adopt it going forward** and migrate existing code **opportunistically** (no big bang on `JsonResponse` calls that work; many return trivial `['status' => 'ok']` where it adds nothing).

⚠️ **Gotcha**: a **prose** docblock on the action leaks into Nelmio's OpenAPI `summary` (and into the generated SDK's JSDoc). Keep the docblock **tag-only** (`@return ...`); put implementation notes in an internal `//` comment.

### Targeted tools: reach for them on need, never by default

Modern and correct, but adding them with no pressure betrays the "minimal code" principle. Listed so you know what to reach for when the **specific** pain shows up:

- **`JsonStreamer` (8.1)**: an encoder generated at cache warmup (no runtime reflection), 50% less RAM and roughly 2x faster. **For** a **large list endpoint under memory pressure** (farm search, map). Not before the profiler asks for it.
- **`cuyz/valinor`**: the most type-safe hydrator around (`list<string>`, `positive-int`, `int<0,42>`, shaped arrays, recursive validation, "a valid object or a throw with a precise message"). **For** parsing **external or untrusted JSON into value objects** (data files, Airtable blobs, scrapers). NOT for HTTP requests, where `#[MapRequestPayload]` + `#[Assert]` is enough; avoid a third hydrator by default.
- **`Clock` (`ClockInterface`)**: injectable, deterministic time. **For** time-dependent business logic and its tests; no direct `new \DateTimeImmutable()` in the domain.

### API route security: `#[IsGranted]`

`/api/` routes are not protected by `access_control` (which works by URL pattern). Use `#[IsGranted]` at method or class level:

```php
#[IsGranted('ROLE_USER')]
#[Route('/api/parcours/upload-image/{fieldId}', methods: ['POST'], format: 'json')]
public function uploadProjectImage(string $fieldId, ...): Response { /* ... */ }
```

For controllers where **every** route needs the same role, put `#[IsGranted]` on the class:

```php
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    // Every method inherits #[IsGranted('ROLE_USER')]
}
```

### Reading request data

Don't use `$request->get()` (removed in Symfony 8.0). Reach into the bags directly:

```php
$request->query->get('page');       // query string (?page=2)
$request->request->get('field');    // body (POST)
$request->attributes->get('id');    // route params (also available as a typed parameter)
```

Prefer the mapping attributes (`#[MapRequestPayload]`, `#[MapQueryString]`) over direct bag access.

### Catching around a flush leaves you with a closed EntityManager

`UnitOfWork::commit()` closes the EntityManager in a `finally` block, and not only for
Doctrine's own exceptions: anything a listener throws during the flush (an upload, a
business hook, an event) gets you there. So inside the `catch`, the EM is closed.

Re-rendering the form then works only while everything the page needs is already
hydrated. One extra lazy load, an unloaded relation or an `EntityType` whose choices have
not been resolved yet, and you get "The EntityManager is closed" and a 500: exactly the
failure the catch was written to avoid. The dependency is implicit and invisible when
reading the code.

Two ways out. Redirect instead of rendering, which restarts on a fresh EM at the cost of
what the user had typed. Or keep the render and pin it with a functional test that
replays the failure. To make a listener added inside a test visible to the request, call
`$this->client->disableReboot()`: otherwise the kernel restarts, builds an EM without the
listener, and the test goes green for the wrong reason.

### When to extract into Service/

**Always start in the controller.** Controller size is not a problem in itself: a controller with many well-organised CRUD actions can be long without that being a concern. Extract into a `Service/*Handler` when:

1. **Several places do the same thing**: the same combination of calls is duplicated across 2+ controllers, or across a controller and a command
2. **The controller holds extractable business logic**: calculations, complex validation, multi-step orchestration that would read better in a dedicated service

```php
// Service/StepCompletionHandler.php: justified: called from a Controller AND a Command
class StepCompletionHandler
{
    public function handle(UserStep $userStep): void
    {
        if (!$this->stepRules->isCompletable($userStep)) {
            throw new StepNotCompletableException();
        }
        $userStep->setIsCompleted(true);
        $this->entityManager->flush();
        $this->externalApi->sync($userStep->getUser());
        $this->notificationApi->notify($userStep->getUser());
    }
}
```

### Sub-modules

When a feature reaches 3+ controllers:

```
Controller/
├── Product/
│   ├── ProductController.php
│   ├── ProductReviewController.php
│   └── FavoriteProductController.php
```

---

## 4. Dto/: the API payloads

DTOs for `#[MapRequestPayload]`, `#[MapQueryString]` and `#[MapUploadedFile]`. The React counterpart (SDK, forms, 422 handling) lives in `docs/reactony.md`.

### When to create a DTO

- **GET filters**: `#[MapQueryString]` on a DTO (filters are not an entity)
- **POST write, dedicated entity**: no DTO, use the entity directly
- **POST write, subset of a large entity**: allowlist DTO + `ObjectMapper` (below)
- **POST write, no entity**: a plain DTO (`BugReportPayload`, `ChatbotQuestionPayload`)
### Allowlist DTO + ObjectMapper (partial update)

When the payload updates a subset of fields on an existing entity (a User profile, say), a DTO serves as the **allowlist** and the `ObjectMapper` (`symfony/object-mapper`) maps the fields onto the entity:

```php
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]
class SaveProfilePayload
{
    public ?string $firstName;    // Uninitialized if absent from the JSON, so ObjectMapper skips it
    public ?string $lastName;
    public ?string $phone;
}
```

**Important**: no constructor, no `= null`, no `readonly`. The properties stay uninitialized when the JSON doesn't carry them.

The ObjectMapper also supports richer transformations:

```php
#[Map(target: 'emailAddress')]     // map onto a different property name
public ?string $email;

#[Map(transform: 'strtolower')]    // transform the value before mapping
public ?string $username;

#[Map(if: false)]                  // skip this property
public ?string $debugOnly;
```

Symfony 8.1 rounds the component out:

- `#[Map(source: ...)]` can be declared **on the target class**: the input DTO stays attribute-free when it is the target that knows the mapping
- An `IsNotNull` condition: map the property only when the source value is non-null (an alternative to the "uninitialized property" pattern for partial updates; both are valid, and the uninitialized pattern remains the default documented here)
- `MapCollection(targetClass: ...)` to map collections of objects

```php
public function save(
    #[MapRequestPayload] SaveProfilePayload $payload,
    ObjectMapperInterface $objectMapper,
): Response {
    $objectMapper->map($payload, $this->getUser());
    // ...
}
```

### Coercing empty strings to `null` for nullable enums

React forms (react-hook-form) default uninitialized fields to `""`. Symfony's `BackedEnumNormalizer` calls `Enum::from('')`, which throws a `ValueError`, so the user gets a 500. For every nullable enum-backed field exposed to a React form, coerce the empty string to `null` before denormalization:

```php
public function save(Request $request, DenormalizerInterface $denormalizer): Response
{
    $data = $request->getPayload()->all();

    // react-hook-form sends "" when an enum select is left empty.
    // MemberType::from('') throws a ValueError, so a 500 without the coercion.
    foreach (['type', 'ppHorizonInstallation'] as $enumField) {
        if (isset($data[$enumField]) && '' === $data[$enumField]) {
            $data[$enumField] = null;
        }
    }

    $denormalizer->denormalize($data, User::class, 'array', [
        AbstractNormalizer::OBJECT_TO_POPULATE => $this->getUser(),
        AbstractNormalizer::GROUPS => ['user:write'],
    ]);
    // ...
}
```

The frontend alternative is to omit the key when it is empty. The backend stays defensive either way.

### File uploads: `UploadedFile` in the DTO (SF 8.1)

Since Symfony 8.1, `#[MapRequestPayload]` maps `UploadedFile` objects straight into the DTO on `multipart/form-data` requests (`$request->request` and `$request->files` are merged before denormalization). That is the default pattern for an endpoint taking a file plus text fields: one parameter, one validation surface:

```php
class UploadAvatarPayload
{
    public ?string $caption = null;

    #[Assert\NotNull]
    #[Assert\Image(maxSize: '5M')]
    public ?UploadedFile $avatar = null;
}

public function upload(#[MapRequestPayload] UploadAvatarPayload $payload): Response { /* ... */ }
```

Rules:
- **Flat DTO**: keep the upload payload flat, an `UploadedFile` inside a nested object remains a smell (flatten it). The historical bug that made it actually *break* ([#64571](https://github.com/symfony/symfony/issues/64571)) is **fixed** (PR #64576, merged 6.4 and up on 16/06/2026); the reason to keep the DTO flat is now style, not a technical blocker.
- The identifier goes in the route (`{fieldId}`), not in the payload.
- `#[MapUploadedFile]` remains the fallback (a lone file with no text fields, or a case that doesn't fit the flat DTO). Never `$request->files->get()` or `$request->request->get()`.
- Once adopted, check that Nelmio describes the multipart body properly in `openapi.yaml`: the drift gate catches an SDK regression.

### Other `#[MapRequestPayload]` options (SF 8.1)

- `mapWhenEmpty: true`: denormalize even an empty payload
- Dynamic `validationGroups` through a `Closure`/`Expression` evaluated on the resolved arguments
- Variadic arguments: `#[MapRequestPayload] Price ...$prices` maps a JSON array of DTOs

---

## 5. Service/: the execution

Service/ makes things happen: it persists, sends, uploads, exports, scrapes, formats.

| Pattern | Use |
|---------|-------|
| `*Handler` | Reused orchestration (Domain + Api + persist) |
| `*Formatter` | Entity → array transformation when `#[Groups]` isn't enough (cross-entity data, S3 URLs) |
| `*PdfGenerator` | PDF export |
| `*CsvExporter` | CSV export |
| `*FileUploadHandler` | File uploads |
| `Scrapers/*Scraper` | Scraping external sites |

> PDF: for documents with contractual value (invoices, contracts), the target is `sensiolabs/GotenbergBundle` (Chromium rendering, a path to Factur-X). dompdf stays acceptable for simple documents (a CSS 2.1 engine, no flexbox or grid), with one constraint: **dompdf ≥ 3.1.6**, the security release fixing six advisories (including a chroot validation bypass).

For simple serialization, prefer `#[Groups]` directly on the entity (see reactony.md).

Services use **constructor property promotion** with `private readonly`, systematically:

```php
class StepCompletionHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StepRules $stepRules,
        private readonly NotificationApi $notificationApi,
    ) {}
}
```

---

## 6. Api/
External APIs with authentication. URLs and credentials in `.env`, injected through `services.yaml`.

### Retry configured at the DI level, not in the service

`config/packages/http_client.yaml` already configures scoped clients (`webflow.client`, `discord.client`…) with `retry_failed`: 3 attempts, exponential backoff from 1s to 10s on status codes `[0, 429, 500, 502, 503, 504]`.

**Do not re-implement** a retry loop on top: not with `RetryableHttpClient` inside the service, not with a hand-rolled `while`. Services inject the scoped client directly and let the DI layer own the retries:

```php
// OK: the scoped client handles the retry
public function __construct(private HttpClientInterface $httpClient) {}

public function estimate(array $data): array
{
    $response = $this->httpClient->request('POST', $url, ['json' => $data]);
    if (200 !== $response->getStatusCode()) {
        $this->logger->error('Upstream non-200', ['status' => $response->getStatusCode()]);
        throw new \RuntimeException('...');
    }
    return $response->toArray(false);
}
```

A case we hit (since fixed): a service wrapped `RetryableHttpClient` in its own `while` loop with a post-loop check that threw on `$retryCount === MAX_RETRIES`, so a third attempt returning 200 threw anyway. Result: six 500s in production.

Also worth knowing: HttpClient has an **anti-SSRF allow-list** (SF 8.1, to enable when a called URL depends on user input) and an **RFC 9111-compliant HTTP cache** (SF 7.4, relevant for cacheable upstream responses such as geodata or reference data).

### Rate Limiter on public endpoints: `#[RateLimit]`

Endpoints that accept unauthenticated traffic, or that are cheap to fire en masse, must be rate-limited. The textbook cases: login, geodata/autocomplete, file upload, password reset, any public POST.

Since Symfony 8.1, the `#[RateLimit('limiter_name')]` attribute (method or class, repeatable) replaces manual `RateLimiterFactory` injection: the default key is IP + method + path, and the 429 plus `Retry-After`/`X-RateLimit-*` headers are automatic. Options: `methods:`, `key:` (an Expression, per user rather than per IP for instance), `tokens:`. Injecting `RateLimiterFactory` and calling `consume()->ensureAccepted()` is now only justified for key logic that cannot be expressed as an Expression.

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        anonymous_api:
            policy: 'sliding_window'
            limit: 60
            interval: '1 minute'
        upload:
            policy: 'token_bucket'
            limit: 10
            rate: { interval: '1 minute', amount: 10 }
```

```php
#[RateLimit('anonymous_api')]
#[Route('/api/geodata', methods: ['GET'], format: 'json')]
public function geodata(#[MapQueryString] GeodataQuery $query): JsonResponse
{
    // the 429 and Retry-After are handled by the attribute
}
```

For calendar quotas (monthly, or daily aligned on midnight), use `anchor_at` on a fixed window (SF 8.1). On the frontend, `handleSdkError` must treat 429 as a non-422 error (a user-side retry is fine).

Endpoints to rate-limit by default in a Symfony + React project:
- `POST /api/profile/save` and other authenticated mutations (a loose limit, 60/min/user)
- `POST /api/*/upload` (a tight limit, 10/min)
- `GET /api/*/geodata` and external autocompletes (a loose limit, since a debounced frontend still calls them fast)
- Endpoints hitting a paid API (OpenAI, Hubspot forms…)

### Webhook component: the target pattern for inbound webhooks

Once a project accumulates 3+ different inbound webhooks (Webflow, Hubspot, Stripe, Mailjet…), migrate from bespoke controllers to the `symfony/webhook` component. Benefits: a uniform abstraction (`AbstractRequestParser` + `RemoteEvent` + `#[AsRemoteEventConsumer]`), async retry through the `remote_event` transport, standardised signature checking.

There is no built-in parser for Webflow, Hubspot or Stripe: you write your own. With only one or two webhooks, a bespoke controller stays acceptable, but the target convention in Symfony 8 is the component. Don't add a third custom webhook without evaluating the migration.

---

## Logging & Sentry

The Sentry Monolog handler is configured at the `ERROR` level (see `config/packages/sentry.yaml`). Lower levels do **not** reach Sentry.

| Level | Destination | Use for |
|---|---|---|
| `$logger->notice()` | Clever logs only | Debug info, traces |
| `$logger->warning()` | Clever logs only | Non-blocking anomaly (flaky upstream, retry) |
| `$logger->error()` | Clever logs + **Sentry** | Anything worth a human looking at |
| `$logger->critical()` | Clever logs + **Sentry** | Blocking error / data corruption |

The rule: if you expect a human to react, it's `error`. Otherwise it's `warning` or `notice`.

Version constraint: **sentry-symfony ≥ 5.12** starts the runtime context before the router and the firewall, which stops logs and breadcrumbs leaking between requests on persistent workers (FrankenPHP, RoadRunner). No effect under classic PHP-FPM, but the floor is free and prepares that mode.

### Globally ignored exceptions

`ignore_exceptions` in `sentry.yaml` filters out exceptions that aren't application bugs:

- `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`: a 404 is "resource not found", not a bug. It stays in the Clever access logs if an audit is needed.
- `Symfony\Component\ErrorHandler\Error\FatalError` + `FatalErrorException`: raised by PHP, already captured by the other handlers.

To add a class to the ignore list, avoid broad categories (ignoring every `HttpException` would mask real bugs). Prefer the precise class.

### Catch upstream + log + swallow

The pattern for non-critical external APIs (Webflow, Hubspot, Airtable): catch, log at `error`, don't rethrow. The next cron will retry naturally.

```php
try {
    $this->hubspotApi->updateContact($email, $data);
} catch (\Exception $e) {
    $this->logger->error('HubSpot updateContact failed', [
        'email' => $email,
        'error' => $e->getMessage(),
    ]);
    // swallow: don't block the user flow, Sentry already captured it through the monolog handler
}
```

---

## 7. Repository/

Holds **every** query tied to an entity. No separate Finder layer.

```php
class StepRepository extends ServiceEntityRepository
{
    public function findByUserAndVersion(User $user, string $version): array { /* ... */ }

    public function findByStepIdOrFail(string $stepId): Step
    {
        return $this->findOneBy(['stepId' => $stepId])
            ?? throw new NotFoundHttpException();
    }
}
```

### The inverse side of a `OneToOne` is always eager

`#[ORM\OneToOne(mappedBy: '...')]`, the side without the foreign key, cannot be made
lazy. Doctrine says so in its own code: "to-one inverse sides can not be lazy"
(`BasicEntityPersister`). It has to run the query to know whether the association is
null, so hydrating the owner entity emits one `SELECT` per row **even when the code never
calls the getter**. On a list endpoint that is an N+1 nothing in the controller reveals.

For a read-only list that does not need whole entities, hydrate partially:

```php
$qb->select('f.id AS id', 'f.name AS name')->getArrayResult();
```

No entity hydrated, no eager load. Keep the security joins and the 404 checks.

To prove an endpoint does not emit a query, count them: `$client->enableProfiler()`, then
`getContainer()->get('doctrine.debug_data_holder')->reset()` right before the request (the
DAMA transaction makes the profiler accumulate the fixtures' queries too), then filter
`$profile->getCollector('db')->getQueries()`.

---

## 8. Entity/

- **`Timestampable` trait** on every business entity: `DateTimeImmutable` throughout (no mutable `DateTime`)
- **TypedFieldMapper** (Doctrine ORM 3.x): the column type is inferred from the PHP type. No explicit `type:` needed when the property is typed:

```php
// Doctrine ORM 3.x: the type is inferred from the PHP type
#[ORM\Column]
private \DateTimeImmutable $createdAt;

#[ORM\Column]
private string $title;

// An explicit type only when the PHP type isn't enough (text vs string)
#[ORM\Column(type: 'text')]
private string $description;
```

- **Native lazy objects**: with doctrine-bundle 3.x this is the **only** mode, nothing to enable, and the `enable_native_lazy_objects` option is deprecated (bundle 3.1): remove it from the config if it is still there. ORM 4 will make them mandatory.
- **Single-column indexes**: `#[ORM\Column(index: true)]` (ORM 3.5+) instead of a class-level `#[ORM\Index]`
- **Doctrine enums**: `#[ORM\Column(enumType: MyEnum::class)]`
- **Simple computed getters**: fine as long as they only depend on `$this` (`isExpired()`, `getFullName()`)
- Logic that depends on other entities or services goes in Domain/

### Id generation on Postgres: `IDENTITY`, explicitly

```php
#[ORM\Id]
#[ORM\GeneratedValue(strategy: 'IDENTITY')]
#[ORM\Column]
private ?int $id = null;
```

Doctrine says it in its own deprecation message: SEQUENCE was the recommendation for
DBAL 3, IDENTITY is the one from DBAL 4 on, and the `identity_generation_preferences`
that forced SEQUENCE is meant to be removed after the upgrade. On DBAL 4 the platform
emits `GENERATED BY DEFAULT AS IDENTITY`, a real identity column, so the old objection
that IDENTITY meant `SERIAL` no longer holds.

Declare the strategy on the entity rather than relying on the default: with no explicit
value, Doctrine warns that "relying on non-optimal defaults for ID generation is
deprecated".

A project still carrying `PostgreSQLPlatform: sequence` in `doctrine.yaml` after moving
to DBAL 4 is a `/gap-code` finding, at Moyenne priority. The divergence is defensible,
SEQUENCE hands you the id before the flush and lets inserts batch, but it has to be a
decision written down, not a leftover. Converting an existing schema is a real migration
with a cutover, not a config edit.

---

## 9. EventListener/

For automatic actions triggered by the framework, not by business code:

- **Login**: track the last connection
- **Maintenance**: block requests while the site is in maintenance
- **Analytics**: log page views

Don't use it for business logic. If it is a rule ("when X happens, do Y"), it belongs in Domain/ or Service/.

---

## 10. Twig/

Twig extensions are **display only**. Format a department, a category, an emoji.

No business logic and no queries in there. If it is a calculation, it belongs in Domain/. The Twig extension only calls Domain and returns the formatted result.

Use the `#[AsTwigFunction]` / `#[AsTwigFilter]` attributes instead of `AbstractExtension` + `getFunctions()`/`getFilters()`. The benefit: **lazy loading** (the class is only instantiated when the function or filter is used).

```php
class AppExtension
{
    #[AsTwigFunction('formatDepartment')]
    public function formatDepartment(string $id): string { ... }

    #[AsTwigFilter('unslug')]
    public function unslug(string $text): string { ... }

    #[AsTwigTest('expired')]
    public function isExpired(\DateTimeImmutable $date): bool { ... }
}
```

Version constraint: **Twig ≥ 3.27** is mandatory (it fixes CVE-2026-48805 through 48808). Twig 4 is in alpha, don't get ahead of it.

---

## 11. Command/

Commands are orchestrators, like controllers. Same rules: Domain + Service + Api directly, EntityManager for simple CRUD, extraction into Service/ when it repeats.

`#[AsCommand]` is **mandatory** (Symfony 8.0 removed `getDefaultName()`/`getDefaultDescription()`).

Use the **invokable** pattern: no `extends Command`, no `execute()`, no `parent::__construct()`. The logic goes in `__invoke()`, and arguments and options are `#[Argument]`/`#[Option]` attributes on the parameters.

> `Command` is still imported for the `SUCCESS`/`FAILURE` constants: that is its only use.

```php
#[AsCommand(name: 'app:my-command', description: 'Does something')]
class MyCommand
{
    public function __construct(
        private readonly MyService $service,
    ) {}

    public function __invoke(SymfonyStyle $io, #[Option] bool $dryRun = false): int
    {
        // ...
        return Command::SUCCESS;
    }
}
```

### Enums in arguments

PHP enums are supported natively as an argument or option type:

```php
public function __invoke(
    SymfonyStyle $io,
    #[Argument(description: 'Export format')] ExportFormat $format,
): int { /* ... */ }
```

### Complex commands: `#[MapInput]`

When a command has many arguments and options, group them into a DTO with `#[MapInput]`:

```php
class CreateUserInput
{
    #[Argument(description: 'The username')]
    public string $username;

    #[Option(description: 'Activate immediately')]
    public bool $activate = false;
}

#[AsCommand(name: 'app:create-user')]
class CreateUserCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] CreateUserInput $input): int
    {
        // $input->username, $input->activate
        return Command::SUCCESS;
    }
}
```

### Value resolvers in the console (SF 8.1)

The controller resolvers work in `__invoke()`: `#[Argument, MapEntity] User $user` (automatic lookup plus a clean error when it isn't found), `#[Option, MapDateTime(format: 'Y-m-d')]`, and service injection straight as a parameter (`#[Autowire]`, `#[Target]`). `#[AsCommand]` also goes on **methods**, to group several small related commands into one class with shared dependencies.

**What stays in a Command**: the progress bar, DB retry/reconnect, batch sizing, log orchestration, CLI argument handling.

**What moves to Service/**: parsing and scraping, business rules, complex persistence, sending notifications. A Command carrying more than roughly 100 lines of business logic is a signal to extract.

---

## 12. Factory/

For when creating an entity needs more than a plain `new`: initialising relations, computed defaults, cascading creation.

For a plain `new Entity()` with a few setters, no Factory needed.

---

## 13. Testing

The standard stack, shared by every Symfony + React project. No "light" for some and "heavy" for others: the same thing everywhere. A solo developer on critical platforms (money, PII, government audit) needs the net at its maximum and uniform.
### The pyramid

| Layer | Tool | Role | Speed |
|---|---|---|---|
| Unit | **PHPUnit** + pure Domain | calculators, enums, value objects, no DB | very fast |
| Property-based | **Eris** on Domain money math | amount computers, discount appliers, fees | fast |
| Integration | **PHPUnit** + a transactional DB (DAMA bundle) | services orchestrating Doctrine, repositories, listeners | fast |
| Functional | **PHPUnit** `WebTestCase` + `KernelBrowser` | the HTTP contract of the API endpoints | medium |
| E2E | **Playwright** + **`@axe-core/playwright`** | multi-page user journeys, a11y | slow |
| Contract drift | `make types && git diff --exit-code openapi.yaml assets/lib/api/` in CI | rejects a PR that drifts the front/back contract | instant |

Every layer runs on **every PR**. No mutation testing: Eris (property-based) already covers the critical money math, and adding Infection on top isn't worth its cost at this project size.

### When not to test

- getters/setters, one-to-one mappers, Doctrine passthrough wrappers
- pure configuration (classes that only expose env vars)
- dead or deprecated code on its way out

Write a test because the code's complexity justifies it, not because you happen to be in the file. If a service orchestrates six others with a lot of plumbing, that usually means it should be split up, not that it needs a test mocking everything.

### Foundry: test factories

The modern Symfony standard for building test entities. It replaces hand-written fixtures and the repeated `$entity = new Entity(); $entity->setX(...);` in `setUp()`.

```bash
composer require --dev zenstruck/foundry
```

Since Foundry 2.9, the `Factories` / `ResetDatabase` traits are **deprecated**: wire the PHPUnit extension instead (nothing left to declare in the test cases):

```xml
<!-- phpunit.xml.dist -->
<extensions>
    <bootstrap class="Zenstruck\Foundry\PHPUnit\FoundryExtension">
        <parameter name="enabled-auto-reset" value="true"/>
    </bootstrap>
</extensions>
```

```php
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class InvestmentFactory extends PersistentObjectFactory
{
    public static function class(): string { return Investment::class; }

    protected function defaults(): array
    {
        return [
            'relatedUser' => UserFactory::new(),
            'amount' => self::faker()->numberBetween(100, 10_000),
            'shares' => self::faker()->numberBetween(1, 100),
            'fund' => Fund::FUND_A_TECH_ID,
            'status' => InvestmentStatus::STATUS_IDENTITY,
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}

// In a test:
$investment = InvestmentFactory::createOne(['amount' => 5000, 'status' => InvestmentStatus::STATUS_PAYMENT]);
$batch = InvestmentFactory::createMany(10, ['fund' => Fund::FUND_B_TECH_ID]);
```

One factory per critical entity. The other, passthrough entities can stay on a direct `new Entity()`.

Version constraint: **Foundry ≥ 2.10.3**. The 2.10.2 and 2.10.3 patches (July 2026) fix real persistence bugs: persist deferred until the object graph is fully instantiated, and Doctrine events on nested entities.

### DAMA Doctrine Test Bundle: automatic transactional rollback

`dama/doctrine-test-bundle` wraps **every test** in a transaction and rolls back at tearDown. No more hand-written `DatabaseTransactionTestCase`, no more wiping between tests, and the suite runs five times faster.

```bash
composer require --dev dama/doctrine-test-bundle
```

`config/bundles.php`:

```php
return [
    // …
    DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['test' => true],
];
```

`phpunit.xml.dist`:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
</extensions>
```

Every `KernelTestCase` / `WebTestCase` inherits the rollback automatically. Tests become **isolated** and **fast** for free. `#[SkipDatabaseRollback]` (dama 8.5+) disables the rollback on a test that genuinely has to commit.

PHPUnit constraint: **PHPUnit 13** is the current line (13.3.x). Verified against the sources (August 2026): dama's apparent "ceiling" only exists in the bundle's `require-dev` (its CI matrix), the only runtime constraint of the tagged v8.6.0 is `conflict: phpunit < 11`, and `master` carries **no code change** relative to it. So **dama v8.6.0 installs and works with `phpunit ^13`**; the earlier advice to pin a `master` commit was pointless, don't follow it. PHPUnit 12 stays supported (bugfixes until 5 February 2027): `^12.5` and `^13` are both viable lines, and dama is no longer a deciding factor. Moving to 13.3, note `#[Retry]`/`#[Repeat]` (and `--retry`/`--repeat`) for replaying tests, and the deprecation of `--cache-result`/`--do-not-cache-result` in favour of `--record-test-run-history`/`--do-not-record-test-run-history`. PHPUnit 11: bugfix support ended in February 2026; PHPUnit 12+ no longer accepts doc-comment annotations, attributes only.

### Unit example: a Domain calculator

```php
public function testCalculateScores(): void
{
    $calculator = new ScoreCalculator();
    $scores = $calculator->calculate(['1' => 'B', '2' => 'C']);
    $this->assertSame(50, $scores[0]['score']);
}
```

### Property-based example: money math (Eris)

Testing an amount computer by hand misses the edge cases (tiers, rounding, accumulation). Eris throws hundreds of random inputs at it and asserts **invariants**.

```bash
composer require --dev giorgiosironi/eris
```

Constraint: `^1.1` (PHPUnit 12/13 support; the `regex()` generator now requires `ilario-pierbattista/reverse-regex`).

```php
use Eris\Generator;
use Eris\TestTrait;

final class InvestmentAmountComputerPropertyTest extends TestCase
{
    use TestTrait;

    public function testTotalAmountIsAlwaysAtLeastSharesPrice(): void
    {
        $this->forAll(
            Generator\choose(1, 1000),                  // shares
            Generator\elements('FUND_A', 'FUND_B'),
            Generator\bool(),                           // taxDeduction
        )->then(function (int $shares, string $fund, bool $taxDeduction): void {
            $computer = new InvestmentAmountComputer(/* deps */);
            $result = $computer->compute($shares, $fund, $taxDeduction);

            // Invariant: the total can never fall below the nominal price of the shares
            $this->assertGreaterThanOrEqual(
                $shares * SharePrice::nominal($fund),
                $result->totalAmount,
            );
        });
    }
}
```

Target: the **five to ten money-calculation services**, not the whole codebase. It is the most powerful tool there is for catching the financial bugs no hand-written test will ever cover.

For time-based rules (`"-18 years"`, deadlines), the date comparison constraints are clock-aware in Symfony 8.1: inject a `MockClock` into the test instead of computing dates relative to `now()`.

### Integration example: a repository or listener hitting the DB

With DAMA active, there is no boilerplate left. Extend `KernelTestCase` directly:
```php
final class InvestmentRepositoryTest extends KernelTestCase
{
    public function testFindActiveByUserExcludesArchived(): void
    {
        $user = UserFactory::createOne();
        InvestmentFactory::createOne(['relatedUser' => $user, 'status' => InvestmentStatus::STATUS_VALIDATED]);
        InvestmentFactory::createOne(['relatedUser' => $user, 'status' => InvestmentStatus::STATUS_ARCHIVED]);

        $repo = self::getContainer()->get(InvestmentRepository::class);
        $active = $repo->findActiveByUser($user);

        $this->assertCount(1, $active);
    }
}
```

### Functional example: an endpoint contract test

```php
final class ValidateSharesTest extends WebTestCase
{
    public function testValidPayloadCreatesInvestmentInIdentityStatus(): void
    {
        $user = UserFactory::createOne();
        $client = self::createClient();
        $client->loginUser($user->_real());

        $client->request('POST', '/tunnel/validate/shares',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['shares' => 5, 'fund' => 'FUND_B', 'taxDeduction' => true]),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $investment = self::getContainer()->get(InvestmentRepository::class)
            ->findOneBy(['relatedUser' => $user->_real()]);
        self::assertSame(InvestmentStatus::STATUS_IDENTITY, $investment?->getStatus());
    }
}
```

### E2E with Playwright

`tests/` covers the HTTP contract but doesn't catch multi-page regressions: a five-step funnel, interactive JS, redirects. Playwright drives the app in a real Chromium.

```bash
pnpm add -D @playwright/test @axe-core/playwright
pnpm exec playwright install --with-deps chromium
```

The only choices in `playwright.config.ts` you can't guess:

- **`fullyParallel: false` and `workers: 1`.** The DB is shared, so parallelism makes it non-deterministic.
- **Three projects**: a `setup` that seeds and authenticates once, saving its `storageState`; a public project for the specs that need no auth; and an authenticated project declaring `dependencies: ['setup']` and reusing that `storageState`.
- `trace: 'on-first-retry'` and `screenshot: 'only-on-failure'`, otherwise a CI failure can't be diagnosed.

**The seed command** (`app:e2e:seed`) is idempotent, wipes then inserts, prefixes its rows with `__e2e__` so they stay isolable, and depends on no external API.

**Accessibility is checked inside the spec**, not in a separate pass: a violation fails the test.

```ts
const a11y = await new AxeBuilder({page}).analyze();
expect(a11y.violations).toEqual([]);
```

Keep `@axe-core/playwright` at `^4.13`: every bump of the axe engine surfaces new violations, to be handled at the bump rather than ignored.

**Never call an external integration from an E2E**, same constraints as in `tests/`. Three ways: empty key environment variables so the services fall into their no-op branch, a service override in the `e2e` environment config, or an injected `MockHttpClient`.

### Contract drift: the OpenAPI diff in CI

The frontend SDK is generated from `openapi.yaml` (Nelmio) through `make types`. If the backend runtime drifts from the checked-in dump, the frontend breaks silently. A free CI gate:

```bash
make types
git diff --exit-code openapi.yaml assets/lib/api/
```

If the command fails, the PR either forgot to regenerate the SDK or introduced an unacknowledged breaking change. No extra Python or Node dependency.

On top of that, [oasdiff](https://github.com/oasdiff/oasdiff) (a GitHub Action) classifies the `openapi.yaml` diff as breaking or non-breaking: `git diff` says there is drift, oasdiff says whether it breaks the contract.

### Mandatory safeguard: `tests/bootstrap.php` refuses remote databases

A test accidentally pointed at the staging or production DB wipes everything. Useful paranoia: at boot, if the URL smells remote, `exit 1` before PHPUnit even starts.

```php
$databaseUrl = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');
foreach (['clever-cloud', 'production', '.rds.amazonaws.com', /* project-specific patterns */] as $needle) {
    if (str_contains(strtolower($databaseUrl), $needle)) {
        fwrite(\STDERR, "Refusing to run tests: DATABASE_URL looks remote ($needle)\n");
        exit(1);
    }
}
```

### Mocking external APIs with `MockHttpClient`

A test run must never touch an external API (HubSpot, Slack, third-party webhooks). The pattern: replace the `HttpClientInterface` injected into each API wrapper with a `MockHttpClient` returning `200 {}` for everything.

Declare it in `config/services_test.yaml`, which MicroKernelTrait loads **after** `config/services.yaml`: so `config/packages/test/services.yaml` is **not** enough, a trap that cost three attempts.

```yaml
services:
    test.mock_http_client:
        class: App\Test\TestMockHttpClient  # wraps MockHttpClient with a factory returning '{}'

    App\Api\HubspotApi:
        autowire: false
        arguments:
            $httpClient: '@test.mock_http_client'
            # plus every other __construct arg, because autowire: false
```

For clients that don't go through `HttpClientInterface` (the Google SDK, say), mock at service level in the `setUp()` of the test concerned.

### Safety net first, before a big refactor

Before refactoring a large, fragile component (over 500 lines, many branches, no tests), first write the tests that pin its **current** visible behaviour: happy path, error cases, business guards. Refactor **afterwards**, keeping the suite green. Refactor first and you have no way of knowing you broke nothing.

That goes double for anything on a payment or subscription path: a silent regression costs revenue.

### CI: orchestrating the pyramid

**On every push, not only on pull requests.** A flow that merges branches directly, with
no PR, never fires a `pull_request` trigger: the suite is then configured for an event
that never happens. Trigger on `push` to the feature branches, `preprod` and `main`, and
add `pull_request` on top if the project ever uses them.

```yaml
on:
  push:
    branches: ['**']
  pull_request:
```

Jobs (GitHub Actions):

```yaml
jobs:
  quality:
    # PHPStan, CS-Fixer, lint:container, doctrine:schema:validate, ESLint, tsc
  contract-drift:
    # make types && git diff --exit-code openapi.yaml assets/lib/api/
  phpunit:
    # vendor/bin/phpunit (Unit + Integration + Functional + property-based through Eris)
  vitest:
    # pnpm test
  playwright:
    # pnpm test:e2e (with sharding past 20 specs)
```

For Playwright on large suites: shard the matrix (`shardIndex: [1,2,3,4]`, `shardTotal: 4`) then add a `merge-reports` job aggregating the blob reports.

---

## 14. Quality Assurance: static analysis and formatting

Static analysis replaces the IDE inspections (PHPStorm, the Symfony plugin). These tools are bundled in the **`/quality` skill** (Claude Code), to run before committing or to check quality mid-development.

### PHPStan: static analysis
PHPStan (v2.x) with the `phpstan-symfony`, `phpstan-doctrine` and `phpstan-deprecation-rules` extensions. Target: **level 9 minimum, level 10 (`max`) recommended**, climbing with a baseline rather than staying stuck on the excuse of legacy code. Level 8 is no longer the standard. `phpstan-strict-rules` + `bleedingEdge.neon` as the state-of-the-art option.

```bash
composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/phpstan-deprecation-rules
```

```neon
# phpstan.neon
includes:
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-doctrine/extension.neon
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon

parameters:
    level: max
    paths:
        - src
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
```

What `phpstan-symfony` adds over plain PHPStan:
- Correct types for `ContainerInterface::get()` and `AbstractController::getParameter()`
- Analysis of Console commands (argument and option types)
- Type inference for Messenger's `HandleTrait`

```bash
vendor/bin/phpstan analyse
```

Since PHPStan 2.2.6, **Turbo**: an optional native PHP extension (precompiled binaries shipped in the Composer package, loaded automatically on PHP 8.3+) speeds analysis up by 10 to 30% with bit-identical output. Nothing to configure, just update. (Phar users: `pie install phpstan/turbo`.)

#### `class.nameCase` never goes in the baseline

macOS is case-insensitive, Linux is not. A `use` written `Google\Service\GroupsSettings`
when the real class is `Groupssettings` autoloads fine locally and hard-crashes in CI and
in production with "Class not found". PHPStan reports it as
`Class X referenced with incorrect case` under the `class.nameCase` identifier, so
baselining that identifier hides a live bug behind a green local build. Fix these, never
tolerate them. Same reflex for a file renamed with only a case change: git on macOS may
not record it.

#### AbstractAppController: typing `getUser()`

`AbstractController::getUser()` returns `UserInterface|null`, so PHPStan doesn't know it is your `User` entity. Write a base controller that types the return:

```php
abstract class AbstractAppController extends AbstractController
{
    protected function getUser(): User
    {
        $user = parent::getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        return $user;
    }
}
```

Every controller extends `AbstractAppController` instead of `AbstractController`.

#### Native PHP types rather than PHPDoc

When PHP can express the type natively, use the PHP type, not a `@param`/`@return` PHPDoc. PHPDoc is reserved for types PHP doesn't support (`array<string, mixed>`, `Collection<int, User>`, `list<string>`).

PHP-CS-Fixer converts them automatically with the `phpdoc_to_param_type`, `phpdoc_to_return_type` and `phpdoc_to_property_type` rules.

#### Doctrine collections: generics

Annotate `Collection` properties with the generic type so PHPStan understands the loops:

```php
/** @var Collection<int, UserAction> */
#[ORM\OneToMany(targetEntity: UserAction::class, mappedBy: 'user')]
private Collection $userActions;
```

### PHP-CS-Fixer: formatting

Applies the formatting conventions automatically. On a **Symfony** project, use the **`@Symfony`** ruleset: it is the style Symfony uses internally, idiomatic to the ecosystem, and it already handles property hooks and asymmetric visibility. Add the migration set for the target PHP version, **`@PHP85Migration`** (stable since CS-Fixer 3.91; the runtime is on 8.5), placed **after** `@Symfony`. It doesn't touch `concat_space`, but if you stack another set on top, re-assert `concat_space: { spacing: 'none' }` explicitly to keep the Symfony style.

⚠️ **Do not stack `@PER-CS3x0` on top of `@Symfony`**: the two contradict each other on `concat_space` (`@Symfony` uses `'none'` → `'a'.'b'`; `@PER-CS3x0` uses `'one'` → `'a' . 'b'`). Stacked after `@Symfony`, `@PER-CS3x0` wins and reformats the whole repo into a non-Symfony style. `@PER-CS3x0` (the PHP-FIG standard, successor to PSR-12, released July 2025) is the right choice for **framework-agnostic libraries**, not for a Symfony project.

```bash
composer require --dev friendsofphp/php-cs-fixer
```

```bash
# Check (/quality):
vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply:
vendor/bin/php-cs-fixer fix
```

### Symfony validations

```bash
# Doctrine mappings (replaces PHPStorm's real-time validation)
php bin/console doctrine:schema:validate --skip-sync

# DI container compilation
php -d memory_limit=256M bin/console lint:container
```

### Psalm: taint analysis (optional, in CI)

Psalm detects security vulnerabilities (SQL injection, XSS, command injection) by statically analysing data flow, a capability PHPStan doesn't have. Recommended in CI for projects handling user input.

```bash
composer require --dev vimeo/psalm
vendor/bin/psalm --taint-analysis
```

### Composer audit: vulnerabilities

Checks for known vulnerabilities in the PHP dependencies.

```bash
composer audit
```

### Removing a bundle: Flex deletes the files its recipe owned

`composer remove` on a package that has a Flex recipe triggers that recipe's
`unconfigure`, and `CopyFromRecipeConfigurator::unconfigure()` deletes every file the
recipe is recorded as owning, modified by hand or not. Removing
`symfony/webpack-encore-bundle` this way takes `package.json`, `assets/app.js`,
`assets/styles/app.css` and `webpack.config.js` with it, and strips the
`###> symfony/webpack-encore-bundle ###` block from `.gitignore`, which is where
`/node_modules/` and `/public/build/` were declared. The next commit then carries
`node_modules/`.

So: commit a clean state first, so `git checkout <file>` can bring anything back, and
read `git status` afterwards looking for unexpected `D` lines and a shrunken
`.gitignore`. `symfony.lock` lists what each recipe owns.

### After a Symfony version bump

Two failures that neither the pre-commit hook nor PHPStan will show you.

**Purge every environment's cache, not just dev.** Composer's post-install `cache:clear`
covers dev; `var/cache/test` stays compiled against the old version. A minor bump can
remove an internal class, and the PHP files written by `symfony/cache` still reference
it, so every Twig page 500s and the test suite drowns in false failures. `rm -rf
var/cache/dev var/cache/test`, then `cache:clear` (raise `memory_limit`, the default
128M is not enough).

**Run the whole PHPUnit suite.** PHPStan analyses `src/` only, so an incompatibility in
`tests/` sails through the hook. A new method on `KernelTestCase` colliding with a test
helper of the same name is a fatal error at suite load, not a failing assertion.

### Summary

| Tool | Role | When |
|-------|------|-------|
| PHPStan level 9-10 | Types, null-safety, logic, deprecations, Symfony/Doctrine inspections | `/quality` |
| PHP-CS-Fixer | Formatting + PHPDoc to native type conversion | `/quality` |
| `doctrine:schema:validate` | Doctrine mappings | `/quality` |
| `lint:container` | DI compilation | `/quality` |
| `composer audit` | Dependency vulnerabilities | `/quality` |
| Psalm taint analysis | Security (SQLi, XSS) | CI |

> All of these except Psalm are bundled in the global `/quality` skill, which auto-detects the project type (Symfony, Next.js, or both). For the frontend quality tools (ESLint, TypeScript), see `docs/reactony.md`, Quality Assurance section.

### Pre-commit: husky + lint-staged

The universal guardrail: **no commit gets through unless it respects the rules**, whether it comes from a human or an agent.

```bash
pnpm add -D husky lint-staged
pnpm husky init
```
`.husky/pre-commit`:

```sh
pnpm lint-staged
```

`package.json`:

```json
"lint-staged": {
    "*.php": [
        "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --path-mode=intersection --"
    ],
    "*.{ts,tsx}": [
        "eslint --fix",
        "prettier --write"
    ]
}
```

lint-staged only runs on **staged** files, so it stays fast even on a large project. The `fix` / `--write` steps re-stage the auto-corrected files.

> **PHP-CS-Fixer gotcha**: with several paths as arguments (the lint-staged case), you need an explicit `--config=<path>` and `--path-mode=intersection` so the config's finder correctly narrows to the files passed. The `--` separates options from paths.

That intersection is also a blind spot: the hook only sees staged files, while CI runs
`--dry-run` on the whole repository. A violation sitting in a file nobody touched passes
the commit and turns CI red. When CI fails on CS-Fixer after a clean commit, read the CI
diff to find the guilty file instead of searching the commit's own changes.

### Project-wide checks in the pre-commit hook

lint-staged works at file level. But the checks that need the whole project (static analysis, DI validation, generated-type drift) are **fast enough for a pre-commit hook** on an average Symfony project: no excuse for relegating them to CI only. Measure the real cost before deciding.

An example `.husky/pre-commit`:

```sh
#!/usr/bin/env sh
set -e

pnpm exec lint-staged

pnpm tsc --noEmit
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php -d memory_limit=256M bin/console lint:container -q
php -d memory_limit=512M bin/console doctrine:schema:validate --skip-sync -q

# Detect drift between generated types and current entities/DTOs
make types > /dev/null
if ! git diff --quiet -- openapi.yaml assets/lib/api/; then
    echo "✖ Generated types drifted. Re-stage with: git add openapi.yaml assets/lib/api/"
    exit 1
fi
```

Orders of magnitude observed on a mid-sized Symfony + React project:

| Check | Typical time |
|---|---|
| `lint:container` | ~0.5s |
| `schema:validate --skip-sync` | ~0.3s |
| `make types` + drift | ~2s |
| `tsc --noEmit` | ~8s |
| PHPStan level 9/10 (full) | ~8s |
| **Pre-commit total** | **~15-20s** |

Not tolerable on a project where you commit ten times an hour, but at a normal feature rhythm (2 to 5 commits per feature) it is the price of a "no silent regression at commit time" guarantee. If the project grows and this passes ~30s, degrade to "PHPStan + tsc in CI only, the rest in the pre-commit hook".

> **TypeScript 7** (the native Go port) has been GA since July 2026, published under the standard `typescript` npm package, `tsc` binary unchanged: `tsc --noEmit` drops from ~8s to ~1s. Migrate in two steps from `^5.9`: 5.9 → 6.0 (absorb the new defaults) → 7.0. Details in reactony §8.

**Only the tests (unit + functional) stay OUT of the pre-commit hook**: they can climb to several minutes. Those belong in CI.

### When to run `/quality` during a session

In an AI-assisted dev session, run `/quality` **before declaring a task finished** whenever code changed. It catches errors during the session (immediate feedback) instead of letting them show up only at commit time (delayed feedback, expensive to debug). The pre-commit hook stays the final net, not the first resort.

---

## 15. What to create when

| Need | Where it goes |
|--------|-------------|
| Business rule, condition, validation | `Domain/MyContext/MyRules.php` |
| Finite set of values | `Domain/MyContext/MyEnum.php` (PHP enum) |
| Large reference data | `Domain/MyContext/MyData.php` |
| Pure calculation (data as parameters) | `Domain/MyContext/MyCalculator.php` |
| Calculation that needs data (a repo) | `Service/MyService.php` → calls the Calculator |
| Entity → JSON serialization (simple) | `#[Groups]` on the entity |
| Entity → array transformation (complex) | `Service/MyFormatter.php` |
| Lookup-based resolution | `Domain/MyContext/MyResolver.php` |
| Business error | `Domain/MyContext/Exception/MyException.php` |
| Reused or complex orchestration | `Service/MyHandler.php` |
| API payload (subset of an entity) | `Dto/MyPayload.php` + `#[Map(target:)]` + `ObjectMapper` |
| API payload (no entity) | `Domain/<Context>/MyPayload.php` (business exchange) or `Dto/` (technical) + `#[MapRequestPayload]` |
| GET filters | `Dto/MyFilterDto.php` + `#[MapQueryString]` |
| Authenticated external API | `Api/MyServiceApi.php` |
| Scraping / export / upload | `Service/My*Handler.php` |
| Query on an entity | `Repository/MyRepository.php` |
| Complex entity creation | `Factory/MyFactory.php` |
| HTTP route | `Controller/MyController.php` |
| Twig form | `Form/MyFormType.php` |
| Scheduled job | `Command/MyCommand.php` + `#[AsCronTask]` |
| Async external call | `Message/MyMessage.php` + `MessageHandler/MyHandler.php` |
| API route security | `#[IsGranted('ROLE_USER')]` on the method or class |

---

## 16. Messenger: async external calls

Calls to external services (Hubspot, Discord, Slack, emails) are dispatched asynchronously through Symfony Messenger. The controller dispatches a message DTO and the worker consumes it in the background.

### Transport

Doctrine (PostgreSQL, the `messenger_messages` table). The `SendEmailMessage`, `ChatMessage` and `SmsMessage` messages stay **sync**, because their templates receive Doctrine entities that don't serialize.
Target PostgreSQL version: **≥ 17** (the CleverCloud default; 18.3 is available, with io_uring and UUIDv7). An add-on still on 15 or 16 is a gap for `/gap-code` to raise, the upgrade on Clever being cheap.

### Message: the DTO

A `final readonly class` holding **scalars** only (int, string, bool). No entity, no complex object: the message has to be serializable.

```php
namespace App\Message;

final readonly class SyncProfileToHubspot
{
    public function __construct(
        public int $userId,
    ) {}
}
```

### Handler: the execution

`#[AsMessageHandler]` + a `final readonly class`. The handler reloads the entity from the DB by its ID, **checks it still exists** (it may have been deleted between dispatch and processing), then calls the existing services.

```php
namespace App\MessageHandler;

use App\Message\SyncProfileToHubspot;
use App\Repository\UserRepository;
use App\Service\HubspotSyncHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncProfileToHubspotHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private HubspotSyncHandler $hubspotSyncHandler,
    ) {}

    public function __invoke(SyncProfileToHubspot $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if (!$user) {
            return;
        }

        $this->hubspotSyncHandler->handleProfileUpdate($user);
    }
}
```

### Dispatching from the controller

Always **after** the persist and flush, so the entity is in the DB when the handler reloads it.

```php
$entityManager->flush();
$bus->dispatch(new SyncProfileToHubspot($user->getId()));
```

### Special case: a deleted entity

If the entity will be deleted right after the dispatch, pass the data you need as scalars (the email, say) rather than the ID.

### Retry and failed

3 retries with exponential backoff (1s, 2s, 4s). After 3 failures the message goes to the `failed` transport. CRITICAL errors surface in Sentry.

Since Symfony 8.1: **decoding** failures (a corrupted message, a renamed class) also go through the retry/failed pipeline instead of being silently lost; `messenger:consume --fetch-size=N` fetches in batches for high volumes; and the PostgreSQL Doctrine transport (LISTEN/NOTIFY) no longer blocks multi-transport consumption by priority.

### Routing (`config/packages/messenger.yaml`)

```yaml
routing:
    Symfony\Component\Mailer\Messenger\SendEmailMessage: sync
    Symfony\Component\Notifier\Message\ChatMessage: sync
    'App\Message\*': async
```

---

## 17. Scheduler: observable crons

Crons are declared directly on the commands with `#[AsCronTask]`. No shell scripts, no `cron.json`.

```php
#[AsCronTask('0 3 * * *', timezone: 'Europe/Paris')]
#[AsCommand(name: 'app:analytics', description: 'Daily analytics')]
class AnalyticsCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        ini_set('memory_limit', '4096M');
        // ...
        return Command::SUCCESS;
    }
}
```

The scheduler is consumed by the same worker as Messenger (the `scheduler_default` transport). Errors surface in Sentry and the Symfony logs.

For memory-hungry commands, use `ini_set('memory_limit', ...)` inside the command rather than a PHP CLI flag.

---

## 18. Forbidden anti-patterns

Hard rules, everywhere. If you find them in existing code, that code is to refactor, not to copy.

**Controller / Route**
- An `/api/` route without `format: 'json'`: the 422s go out as HTML and the frontend cannot parse them
- An `/api/` route without `#[IsGranted]` on the method or the class
- `$request->get()`: removed in Symfony 8. Use `$request->query`, `$request->request`, `$request->attributes`, or the mapping attributes
- Controllers extending `AbstractController` directly and typing `getUser()` as `UserInterface`: write an `AbstractAppController` returning the typed `User` entity and extend it everywhere

**Domain / Service**
- A `Domain/` class that **injects (in its constructor)** a Repository, EntityManager, HttpClient, Logger, Filesystem, another Service, an `Api/` class or `UrlGeneratorInterface`: Domain receives its data as parameters. Framework attributes (`Assert`, `OA`, `Groups`) stay allowed.
- A Calculator reaching into a repository: extract the fetch into `Service/`, the Calculator stays pure
- A Service holding nothing but a pure business rule: move it to `Domain/`
- An interface over a service with a single implementation: no pre-emptive abstraction
- A ValueObject, Aggregate or other DDD ceremony with no concrete justification

**DTO / Payload**
- An allowlist DTO (`ObjectMapper`) with a `constructor`, `= null`, or `readonly` properties: it breaks partial mapping (fields absent from the JSON must stay **uninitialized**)
- A GET filter read through `$request->query->get()` instead of a DTO + `#[MapQueryString]`
- A file upload through `$request->files->get()`: use `UploadedFile` in the `#[MapRequestPayload]` DTO (SF 8.1, flat DTO) or `#[MapUploadedFile]` with the `Assert` constraints

**HttpClient / external API**
- `new RetryableHttpClient($client)` inside a service: retry is configured at the DI level (`scoped_clients` + `retry_failed`)
- A `while ($attempts < $max)` loop around a `$client->request()`: same, that is the DI layer's job
- A public endpoint (login, autocomplete, upload, password reset) without `#[RateLimit]` (a manual `RateLimiterFactory` only for complex custom keys)

**Command**
- `extends Command` + `protected function execute()`: the invokable pattern is mandatory, so no `extends`, `#[AsCommand]`, logic in `__invoke()`, typed parameters with `#[Argument]` / `#[Option]`
- `getDefaultName()` / `getDefaultDescription()`: removed in SF 8, use the named arguments of `#[AsCommand]`
- A cron declared in a shell script or a `cron.json`: use `#[AsCronTask]` on the command

**Entity / Doctrine**
- A `@var` / `@param` / `@return` PHPDoc duplicating a native PHP type already present: PHP-CS-Fixer removes it
- A `Collection` property without its generic (`Collection<int, Entity>`): PHPStan cannot infer the type in a loop
- Mutable `DateTime`: use `DateTimeImmutable`
- `#[ORM\Column(type: 'string')]` on a `string`-typed property: redundant since Doctrine ORM 3 (TypedFieldMapper). An explicit type only when PHP isn't enough (`text` vs `string`)

**Logging / Sentry**
- `logger->error()` for a non-blocking anomaly (flaky upstream, successful retry): use `warning` (Sentry only picks up from `ERROR`)
- An `ignore_exceptions` that is too broad (all of `HttpException`, say): ignore the precise class
- Catching and rethrowing a `RuntimeException` that only wraps the original without adding information

**Messenger**
- A message carrying a Doctrine entity, an `UploadedFile` or a callable: fragile serialization, keep to scalars (an ID plus a reload in the handler)
- A handler assuming the entity still exists: reload through the repository and `if (!$entity) return`
- Dispatching **before** the `flush()` of the entity concerned: the handler will read before the DB is up to date

**Tests**
- A test pointing at a production or staging DB: `tests/bootstrap.php` must refuse it by pattern-matching `DATABASE_URL`
- A test hitting a real external API: mock at the DI level with `MockHttpClient`
- A refactor over 500 lines with no test on the existing behaviour: write the safety net **before** touching anything
- A snapshot test on a full render: it breaks on any class change and carries no useful signal
