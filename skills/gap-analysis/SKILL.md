---
name: gap-analysis
description: Audit a whole codebase against its stack's guidelines and write every deviation into docs/gap-analysis.md. Works on the three stacks - Symfony+React, Next, TanStack Start.
---

Audit the full codebase against the guidelines of the stack it belongs to, and produce
an exhaustive gap analysis.

The authority runs one way here: **the guidelines are right, the code is what gets
corrected**. The mirror skill is `/sota-gap`, where the ecosystem is right and the
guidelines get corrected. Do not fix guidelines from this skill.

## 1. Identify the stack and read its guidelines

Detect it, then read the matching files **in full**: they are the source of truth.

| Stack | Detected by | Guidelines |
|---|---|---|
| Symfony + React | `symfony/framework-bundle` in `composer.json` | `docs/symfony-guidelines.md` + `docs/reactony.md` |
| Next | a `next.config.{js,ts,mjs}` | `docs/next-guidelines.md` |
| TanStack Start | `@tanstack/react-start` in `package.json` | `docs/tanstack-start-guidelines.md` |

If the files are missing, the project has no symlink into `dev-standards`: that is the
first gap to report, and the audit stops there.

Then read the existing `docs/gap-analysis.md`, to know what has already been found and
what has been ticked off.

**Skip an archived repository.** A last commit saying "archive", a redirect to a
successor, an archived flag on the remote: report it and stop rather than auditing a
corpse.

## 2. Scan the code

Use agents in parallel across the axes. Be exhaustive: every file, not a sample.

### Symfony backend (`src/`)

- **Domain/ vs Service/**: any class in `Domain/` injecting a repository,
  EntityManager, API client, logger or any infrastructure dependency. Domain/ must be
  pure (Entity plus other Domain). Framework attributes (`Assert`, `OA`, `Groups`)
  are allowed and are not a finding.
- **Enums**: abstract classes with `public const` that should be a PHP `enum`. Naming
  (singular). Doctrine columns that should use `enumType:`.
- **Controllers**: `/api/` routes missing `format: 'json'`; `/api/` routes missing
  `#[IsGranted]`; `$request->get()` (removed in Symfony 8.0); `getRepository()` calls
  instead of an injected typed repository; controllers extending `AbstractController`
  and typing `getUser()` as `UserInterface`; inline business logic that belongs in
  Domain/ or Service/. Length alone is not a finding: a long controller of thin
  actions is fine.
- **DTOs**: `MapRequestPayload` on a large entity where an allowlist DTO is due;
  ObjectMapper DTOs carrying a constructor, `= null` or `readonly`, which breaks
  partial mapping.
- **Repositories**: queries living outside `Repository/`.
- **Entities**: missing `Timestampable`; explicit column types the TypedFieldMapper
  infers; mutable `DateTime`; logic that belongs in Domain/.
- **Commands**: `extends Command` with `execute()` instead of the invokable pattern;
  missing `#[AsCommand]`; more than ~100 lines of business logic inline.
- **Services**: constructors without property promotion, missing `readonly`.
- **Twig**: `AbstractExtension` + `getFunctions()` instead of the `#[AsTwigFunction]`
  attributes.
- **HttpClient**: `new RetryableHttpClient()` or a hand-rolled retry loop in a service;
  a public endpoint with no `#[RateLimit]`.
- **Messenger**: a message carrying an entity or an `UploadedFile`; a handler assuming
  the entity still exists; a dispatch before the `flush()`.
- **PHP 8.4**: implicit nullables, opportunities for asymmetric visibility or property
  hooks.

### React frontend (`assets/`), Symfony stack

- **Data fetching**: `useEffect` + `fetch` + `useState` instead of `useQuery` and the
  generated SDK. A bare `fetch()`. A hand-built `FormData` instead of SDK multipart.
- **Errors**: SDK calls with no `handleSdkError`.
- **Forms**: not on RHF + Zod + the shadcn `Field` family; hand-written Zod schemas
  that should come from `zod.gen`; the legacy `<Form>/<FormField>` wrapper in new code.
- **Uploads**: no `file.size` guard on the frontend.
- **Imports**: relative `../../` instead of `@/`.
- **Typing**: `any` outside the documented `form.setError` exception; untyped props.
- **QueryClient**: a `new QueryClient()` inside a component; a Twig-mounted component
  with no `<QueryClientProvider>`.
- **React 19**: `forwardRef`; `useMemo`/`useCallback` added by hand under the compiler;
  `watch()` or a render-time read of the `formState` proxy.
- **Stimulus and Turbo**: a new custom Stimulus controller carrying state or fetching;
  Turbo Drive re-enabled.

### Next (`app/`, `components/`, `lib/`)

- **Server by default**: `"use client"` on a component that needs no interactivity, or
  placed too high in the tree so a whole page ships to the browser.
- **Baked data**: a page fetching at request time what a `scripts/build-*.mjs` should
  have written into `lib/`; a dynamic route with no `generateStaticParams`.
- **UI base**: a project still on `new-york-v4` (Radix) or React Aria rather than Base
  UI in Nova style; `asChild`, which belongs to neither base.
- **The kit**: a `components/ui/` edited locally in a consumer instead of fixing the
  kit; a component copy-pasted between projects rather than installed from the
  registry.
- **Config**: `reactCompiler` off; `images.remotePatterns` with `hostname: "**"`;
  `--turbopack` still passed, redundant since Next 16; a hand-edited auto-managed
  block in `AGENTS.md`.
- **Icons**: a brand icon imported from `lucide-react` 1.x, which no longer ships any.
- **SEO**: a page with no exported `metadata`.

### TanStack Start (`src/routes/`, `src/components/`, `convex/`)

- **The router is the framework**: `URLSearchParams` parsed by hand; a `useState` for
  state a user would want to share as a link, instead of extending the route's
  `validateSearch` schema.
- **Loaders**: fetching inside a component instead of a loader feeding the Query
  cache; a server-rendered read not using `useSuspenseQuery`.
- **Server state**: Zustand or any client store used as a cache for server data.
- **Server functions**: an API route written for something with no external consumer;
  a `createServerFn` with no `.validator()`.
- **Convex**: a database write going through anything other than a Convex mutation; a
  hand-rolled `take(n)` migration loop instead of `@convex-dev/migrations`.
- **Auth**: routes guarded leaf by leaf instead of in a layout route; two auth
  providers coexisting.
- **UI base**: same axis as Next.
- **React Compiler**: hand-written memoization; `react({ babel: {...} })`, silently
  ignored since plugin-react v6.

### Every stack

- **Anything else that is wrong.** The axes are a starting point, not a limit. Flag
  what looks incoherent, fragile, surprising or plainly broken even when no guideline
  covers it: suspicious logic, security smells, patterns that diverge between two
  files that should match, dead code, hardcoded URLs that belong in the environment.

## 3. Scan config, tooling and the quality gate

The axis agents reading `src/` never open a config file, so **the code can be pristine
while the config silently lags the guideline**. A real case: PHPStan stuck at
`level: 8` while the guideline mandated `max`, invisible to a code-only scan, build
green throughout.

- **PHPStan level** in `phpstan.dist.neon` against what the guideline mandates. A
  lower level is a real gap even with a green build. Check a baseline is used to climb.
- **Quality gate completeness**: does the pre-commit hook (`.husky/pre-commit`) **and**
  the CI (`.github/workflows/*.yml`) each run every mandated check? Name any that is
  missing from either. A mandated test suite that does not exist is a gap, not a skip.
- **TS and lint config**: `tsconfig.json` strict flags, and the linter the stack
  prescribes (Biome on Next and TanStack, ESLint plus `eslint-plugin-react-hooks` >= 7
  on the Symfony stack).
- **Dependency versions**: compare the lockfile against the guideline's reference
  versions, listed exactly in its "Last watch" header. Flag notable drift, and flag a
  security floor that is not met as **Haute** priority whatever else is going on.
- **Mandated config present**: rate limiter, `http_client` retry, Sentry level, the
  Reprise or asset pipeline configuration, `packageManager` pinned, the lockfile
  matching the package manager the docs claim.

## 4. Scan the agent instruction files

The files that steer every future agent session are code too, and they rot silently.

Run the checker shipped with this skill, which confronts the verifiable claims of
`AGENTS.md` / `CLAUDE.md` against the real repository (package manager against the
lockfile, scripts that do not exist, paths that do not exist):

```bash
python3 ~/.claude/skills/gap-analysis/check-agent-files.py .
```

Then read the files yourself for what a script cannot see:

- **One instruction file, not two.** `CLAUDE.md` should be the single line `@AGENTS.md`
  and `AGENTS.md` should carry the content. Two files with independent content is a
  split brain: whichever the agent reads, it reads half the truth.
- **Contradictions between the two**, when both hold content.
- **Stale claims**: a framework version, an architecture, a file layout that no longer
  matches. Every verifiable claim is checkable, so check it.
- **Length**: past roughly 200 lines the file stops being read carefully. Say so.

## 5. Write the gap analysis

Overwrite `docs/gap-analysis.md`:

```markdown
# Gap Analysis : Theorie vs Pratique

> Ecarts entre les guidelines de la stack et le code actuel.
> Organisé par priorité. Cocher au fur et à mesure du nettoyage.

---

## 0. Nom de la catégorie (Haute/Moyenne/Basse priorité)

**Idéal** : ce que disent les guidelines
**Actuel** : ce que fait le code

### Sous-catégorie

- [ ] `chemin/vers/Fichier.php` : description de l'écart
  - Détail, ce qu'il faut extraire, déplacer ou renommer
- [x] Éléments déjà corrigés (repris de la version précédente)
```

Priorities:

- **Haute**: security (a missing floor, an unprotected route, an unvalidated payload),
  architectural violations, anything that can produce a wrong result silently.
- **Moyenne**: convention violations, naming, missing patterns.
- **Basse**: style, dead code, cosmetics.

## 6. Present the summary

Findings per priority, then the most critical items, then one line on what to do first.

## Rules

- **Exhaustive, not sampled.** Every file. `Glob` and `Grep` systematically.
- **Preserve ticked items.** Anything `[x]` in the previous file was fixed: keep it so
  progress stays visible.
- **Concrete, not vague.** Every finding names a file and a line or a method. "Some
  controllers are too big" is worthless; "`AdvertController.php:245` maps icons inline"
  is actionable.
- **A project not applying a rule does not make the rule wrong.** This skill corrects
  the code, never the guidelines. Something that looks wrong in the guidelines goes to
  `/sota-gap`.
- **Do not invent problems.** Flag what genuinely deviates, or what is plainly a bug or
  a security issue. Silence on everything else.
- **Group by theme, not by file**, so the result is a work plan.
- **French for the prose** of `gap-analysis.md`, matching the existing file.
- **Fix nothing.** This skill diagnoses. It does not touch source code.
