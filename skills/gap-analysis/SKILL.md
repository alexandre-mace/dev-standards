---
name: gap-analysis
description: Scan the entire codebase (backend + frontend) and list every deviation from docs/symfony-guidelines.md and docs/reactony.md into docs/gap-analysis.md
---

Audit the full codebase against the project's coding guidelines and produce an exhaustive gap analysis.

## Steps

1. **Read the guidelines**
   - Read `docs/symfony-guidelines.md` and `docs/reactony.md` in full — these are the source of truth
   - Read the existing `docs/gap-analysis.md` to know what's already been identified and what's been checked off

2. **Scan the backend (`src/`)**

   Use agents in parallel to cover all these axes. Be exhaustive — check every file, not a sample.

   - **Domain/ vs Service/ separation** — any class in `Domain/` that injects a repository, EntityManager, API client, logger, or any infra dependency. Domain/ must be pure (Entity + other Domain only).
   - **Enum patterns** — abstract classes with `public const` that should be PHP `enum`. Check naming (singular). Check Doctrine columns that should use `enumType:`.
   - **Controller patterns** :
     - Routes `/api/` missing `format: 'json'`
     - Routes `/api/` missing `#[IsGranted]`
     - Usage of `$request->get()` (removed in Symfony 8.0)
     - Controllers with inline business logic that should be in Domain/ or Service/. Note: controller length alone is NOT a problem — a well-organized controller with many thin actions can be long. Only flag controllers where actual business logic is inline and should be extracted.
     - `getRepository()` calls — should inject typed Repository instead
   - **DTO patterns** — MapRequestPayload on entities that should use a DTO (large entities), DTOs with constructors or `= null` defaults when used with ObjectMapper
   - **Repository patterns** — queries living outside Repository/ (in Domain/, Service/, Twig/, Controllers via `getRepository()`)
   - **Entity patterns** :
     - Missing `Timestampable` trait
     - Explicit `type: 'datetime_immutable'` that can be removed (TypedFieldMapper)
     - Complex logic in entities that belongs in Domain/
     - `DateTime` mutable instead of `DateTimeImmutable`
   - **Command patterns** :
     - Commands extending `Command` with `execute()` instead of invokable pattern
     - Missing `#[AsCommand]`
     - Business logic inline (>100 lines) that should be in Service/
   - **Service patterns** — constructor without property promotion, missing `readonly`
   - **Twig patterns** — extensions using `AbstractExtension` + `getFunctions()` instead of `#[AsTwigFunction]` attributes
   - **PHP 8.4** — implicit nullable (`Type $param = null` without `?`), opportunities for asymmetric visibility or property hooks
   - **General** — dead code (commented-out blocks, unused classes/methods), hardcoded URLs that belong in `.env`, inconsistent naming (plural enums, typos), code duplication
   - **Anything else weird** — the axes above are a starting point, not a limit. Flag anything that looks incoherent, fragile, surprising, or just wrong — even if no specific guideline covers it. Use your judgement. Examples: suspicious logic, security smells, inconsistent patterns between similar files, things that will confuse the next developer.

3. **Scan the frontend (`assets/`)**

   Use agents in parallel. Check every `.tsx`, `.ts` file.

   - **Data fetching** — `useEffect` + `fetch` / `useState` instead of `useQuery` + SDK
   - **SDK usage** — manual `fetch()` calls instead of generated SDK functions. Manual `new FormData()` instead of SDK multipart.
   - **Error handling** — missing `handleSdkError` on SDK calls, inconsistent error patterns
   - **Forms** — forms not using the RHF + Zod + shadcn Form pattern. Missing Zod validation. Manual Zod schemas that should be generated.
   - **Imports** — relative imports (`../../`) instead of `@/` alias. Barrel files that shouldn't exist.
   - **Typing** — `any` casts, untyped props, untyped components
   - **CSS** — ternary className strings instead of `cn()`, raw `<button>` instead of shadcn `<Button>`
   - **File naming** — files not in PascalCase, folders not in snake_case
   - **QueryClient** — components creating `new QueryClient()` instead of using the shared one. Missing `<QueryClientProvider>` wrapper.
   - **React patterns** — `forwardRef` usage (React 19 doesn't need it), missing `useMemo`/`useCallback` that React Compiler handles
   - **Anything else weird** — same as backend: flag anything incoherent, fragile, or surprising beyond the listed axes. Inconsistent patterns between similar components, dead props, logic that doesn't make sense, etc.

4. **Write the gap analysis**

   Overwrite `docs/gap-analysis.md` with the full findings. Use this structure:

   ```markdown
   # Gap Analysis — Theorie vs Pratique

   > Ecarts entre `docs/symfony-guidelines.md` / `docs/reactony.md` et le code actuel.
   > Organisé par priorité. Cocher au fur et à mesure du nettoyage.

   ---

   ## 0. Category Name (Haute/Moyenne/Basse priorité)

   **Idéal** : what the guidelines say
   **Actuel** : what the code actually does

   ### Sub-category

   - [ ] `path/to/File.php` — description of the gap
     - Details, what to extract/move/rename
   - [x] Already-fixed items (preserve from previous gap-analysis)
   ```

   Priority levels:
   - **Haute** — architectural violations (Domain/Service confusion, missing security attributes, wrong patterns)
   - **Moyenne** — convention violations (naming, property promotion, missing traits)
   - **Basse** — style/cosmetic (naming typos, dead code, hardcoded URLs)

5. **Present summary** — After writing the file, show the user a short summary: number of findings per priority level, most critical items.

## Rules

- **Exhaustive, not sampled** — check every file, not just a few examples. Use `Glob` + `Grep` systematically.
- **Preserve checked items** — items marked `[x]` in the previous gap-analysis were already fixed. Keep them in the new file as-is so progress is visible.
- **Concrete, not vague** — every finding must reference a specific file path and line/method. "Some controllers are too big" is bad. "`AdvertController.php` (531 lines) has inline icon mapping at line 245" is good.
- **Don't invent problems** — only flag things that genuinely violate the guidelines or common sense. If the guidelines don't cover something, don't flag it unless it's clearly a bug or security issue.
- **Group by theme, not by file** — organize findings by the type of gap (Domain/Service, Enums, Controllers...), not by individual file. This makes it actionable.
- **French for prose** — same language as the guidelines and existing gap-analysis.
- **Don't fix anything** — this skill only diagnoses. It does not modify source code.
