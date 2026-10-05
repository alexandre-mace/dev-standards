---
name: gap-code
description: Audits a whole codebase against its stack's guidelines and writes every deviation into docs/gap-analysis.md. Works on the three stacks - Symfony+React, Next, TanStack Start.
---

**The guidelines are right, the code gets corrected.** The mirror skill is `/gap-sota`,
where the ecosystem is right and the guidelines get corrected.

## 1. Identify the stack, read its guidelines in full

| Stack | Detected by | Guidelines |
|---|---|---|
| Symfony + React | `symfony/framework-bundle` in `composer.json` | `docs/symfony-guidelines.md` + `docs/reactony.md` |
| Next | a `next.config.{js,ts,mjs}` | `docs/next-guidelines.md` |
| TanStack Start | `@tanstack/react-start` in `package.json` | `docs/tanstack-start-guidelines.md` |

Every stack also reads `docs/react-guidelines.md`: React 19, the compiler, shadcn and the
test doctrine apply to all three.

- Report a missing guidelines file as the first gap and stop: without the symlink into
  `dev-standards` there is nothing to audit against.
- Read the existing `docs/gap-analysis.md` for one thing only: the deviations that were
  deliberately accepted. A fresh scan finds everything else on its own, and git holds the
  history.
- Stop on an archived repository, rather than auditing a corpse: a last commit saying
  "archive", a redirect to a successor, an archived flag on the remote.

## 2. Scan the code

The guidelines are the checklist. Their forbidden anti-patterns section lists, rule by
rule, exactly what a scan looks for, and the rest of the file gives the patterns those
rules protect. A pass that leaks shows up at the next one as "new" findings that were
there all along; four measures keep the first pass close to complete.

**Mechanical rules first, run by the orchestrator, not by the agents.** Every rule that
reduces to a pattern (a raw palette colour, `target="_blank"` without `rel`, an email in a
log context, an API route without `#[IsGranted]`, an em dash in visible text) is one
search over the whole repository, listed exhaustively before any agent starts. An agent
reading for it finds most of the hits; the search finds all of them. A rule that keeps
coming back pass after pass is also a gap in the quality gate: say so, and name the lint
rule or script that would close it in the hook and the CI.

**Coverage is proven, not declared.**

- Zones of at most 60 files, each handed its explicit file list. Past that, an agent's
  context fills up and the end of its zone gets skimmed.
- The agent returns one line per file of its list, findings or "rien", then its findings.
- The orchestrator checks every listed file is in the return, and relaunches the zone on
  what is missing.
- **Each agent returns its findings as text and edits no file.** Several agents writing
  `docs/gap-analysis.md` at once race each other. The orchestrator keeps each return and
  writes the file once, at the end.

**Themes cut across zones.** Some defects are scattered thin: each zone sees one instance
and none sees the pattern. Next to the zones, one agent per theme reads the whole
repository for it alone:

- personal data in logs, error monitoring, URLs and fixtures;
- twins that must stay aligned (PHP and TS rules, copied lists, constants);
- the API contract end to end (DTO, controller, OpenAPI, generated client, form);
- calls to action and links (where each one leads a visitor and a member);
- form accessibility (names, errors, required state on the control).

**Judgement where no search reaches.** A rule that cannot be turned into a search still
gets read for: architecture boundaries, business logic in the wrong layer, a pattern that
diverges between two files that should match. Beyond the guidelines, flag what is
incoherent, fragile, surprising or plainly broken even when no rule covers it: suspicious
logic, security smells, dead code, hardcoded URLs that belong in the environment.

**What only the data can tell is checked in the data.** A finding that depends on what
production holds (a duplicate, a value outside an enum, a dead branch) is confirmed by a
read-only query before it is written, and its priority follows the answer.

## 3. Scan config, tooling and the quality gate

A scan of the source never opens a config file, so the code can be pristine while the
config silently lags. Real case: PHPStan stuck at `level: 8` against a guideline mandating
`max`, invisible to a code-only scan, build green throughout.

- PHPStan level in `phpstan.dist.neon` against what the guideline mandates. A lower
  level is a real gap even with a green build. Check a baseline is used to climb.
- Quality gate completeness: does the pre-commit hook (`.husky/pre-commit`) and the CI
  (`.github/workflows/*.yml`) each run every mandated check? Name any missing from
  either. A mandated test suite that does not exist is a gap, not a skip.
- TS and lint config: `tsconfig.json` strict flags, and the linter the stack prescribes
  (Biome on Next and TanStack, ESLint plus `eslint-plugin-react-hooks` >= 7 on Symfony).
- Dependency versions against the guideline's reference list, in its "Last watch"
  header. **A security floor that is not met is Haute priority** whatever else is going on.
- Mandated config present: rate limiter, `http_client` retry, Sentry level, the asset
  pipeline, `packageManager` pinned, the lockfile matching the package manager the docs
  claim.

## 4. Scan the agent instruction files

They steer every future agent session, and they rot silently.

Run `check-agent-files.py`, in this skill's own directory, against the project root. It
confronts the verifiable claims of `AGENTS.md` / `CLAUDE.md` with the repository: package
manager against the lockfile, scripts that do not exist, paths that do not exist.

Then read them for what a script cannot see:

- **One instruction file, not two.** `CLAUDE.md` should be the single line `@AGENTS.md`
  and `AGENTS.md` should carry the content. Two files with independent content is a
  split brain: whichever the agent reads, it reads half the truth.
- Contradictions between the two, when both hold content.
- Stale claims: a framework version, an architecture, a file layout that no longer
  matches.
- Length: past roughly 200 lines the file stops being read carefully. Say so.

## 5. Verify before writing

Every Haute and Moyenne finding goes to a second agent whose only job is to refute it: read
the code, the callers, the tests, the data, and say whether the defect is real. A refuted
finding is dropped, a confirmed one keeps its confidence or gains one. A false positive
costs a fix lot an hour of work and sometimes breaks code that was right.

## 6. Write the gap analysis

Overwrite `docs/gap-analysis.md` with the current state. Nothing is carried forward
except the accepted deviations.

```markdown
# Gap Analysis : Theorie vs Pratique

> Ecarts entre les guidelines de la stack et le code actuel, à la date de l'audit.
> Organisé par priorité. Les cases servent le temps d'une session de nettoyage :
> le prochain audit réécrit le fichier.

---

## 0. Nom de la catégorie (Haute/Moyenne/Basse priorité)

**Idéal** : ce que disent les guidelines
**Actuel** : ce que fait le code

### Sous-catégorie

- [ ] `chemin/vers/Fichier.php` : description de l'écart (sûr | probable | à vérifier)
  - Détail, ce qu'il faut extraire, déplacer ou renommer

## Écarts assumés

- `chemin/vers/Fichier.php` : l'écart, et la raison de ne pas le corriger
```

Priorities:

- **Haute**: security (a missing floor, an unprotected route, an unvalidated payload),
  architectural violations, anything that can produce a wrong result silently.
- **Moyenne**: convention violations, naming, missing patterns.
- **Basse**: style, dead code, cosmetics.

## 7. Present the summary

Findings per priority, then the most critical items, then one line on what to do first.

## 8. What the fixing has to honour

This skill fixes nothing, but the fixes decide whether the next pass finds anything. Hand
the fix lots these rules with the findings:

- **Fix the family, not the line.** Before closing a finding, search for every place with
  the same defect and fix them all. Half of what a pass finds is the sibling of something
  the previous fix lot repaired in one place only.
- **Re-check on the current main.** Other lots merge while one works; a finding may
  already be fixed, or moved.
- **Review your own diff against the audit grid** before opening the pull request, so the
  fix does not hand the next pass new findings.
- **Data first, constraint second.** A unique index or a stricter enum on a table that
  holds offending rows fails the deploy; clean production first, then ship the constraint.
- **Prove the hook ran.** In a fresh worktree the hook directory may be missing, and the
  commits then skip every check without a word.

## Rules

- **Fix nothing.** This skill diagnoses, it does not touch source code.
- Exhaustive, not sampled. Every file, with `Glob` and `Grep`.
- Every finding names a file and a line or a method. "Some controllers are too big" is
  worthless, "`AdvertController.php:245` maps icons inline" is actionable.
- Something that looks wrong in the guidelines themselves goes to `/gap-sota`.
- **Report everything you saw, each finding with its confidence**: `sûr` (the code
  plainly contradicts a rule), `probable` (it looks wrong, one thing left to check),
  `à vérifier` (a doubt worth a look). Do not drop a doubtful finding: the user sorts, not
  the scan. A confidence is not a severity, which the priority already carries.
- **Read the code before calling it a deviation.** Code that does not follow the canonical
  pattern is sometimes right for its situation, and forcing the pattern makes it worse.
  Seen: mutation hooks flagged for not using `useMutation`, in islands mounted without a
  `QueryClientProvider`, where the hook crashes on render. A form flagged for not using
  react-hook-form, whose fields are derived asynchronously from two selects, which RHF
  does not simplify. Hand-written Zod schemas flagged as not generated, validating the
  state of the form rather than the payload. Say honestly that the pattern does not fit,
  rather than opening a gap that a later session will close by breaking working code.
- Group by theme, not by file, so the result is a work plan.
- French for the prose of `gap-analysis.md`, matching the existing file.
