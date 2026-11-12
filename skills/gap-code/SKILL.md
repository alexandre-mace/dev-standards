---
name: gap-code
description: Audits a whole codebase against its stack's guidelines and writes every deviation into docs/gap-analysis.md, aiming at zero, Basse included. `delta` reviews only what changed since the previous pass, to prepare it. Works on the three stacks - Symfony+React, Next, TanStack Start.
---

**The guidelines are right, the code gets corrected.** The mirror skill is `/gap-sota`,
where the ecosystem is right and the guidelines get corrected.

## 0. The target, and how much to read

**The target is zero findings, every priority included.** Between two passes each finding
is fixed or recorded as an accepted deviation with its reason; nothing stays open. A
Basse finding is objective, however small: a rule broken, a docblock that now says the
wrong thing, dead code, a misplaced comment, a twin that will drift. A matter of taste is
not one, and listing it only keeps the count from reaching zero. What never counts is in
`not-a-finding.md`, the one list every agent reads.

**Every agent gets its brief from a file of this skill, verbatim**: `zone-brief.md`,
`refute-brief.md`, `lot-brief.md`, placeholders filled in. A rule the orchestrator would
add by hand goes into the file instead. Seen: a lot brief written from memory that dropped
the rule on visible texts, and lots that reworded them.

**Every file, every pass.** A pass reads the whole repository, unchanged files included:
the code has not moved, but the reader has. A newer model, or a guideline sharpened since,
sees in old code what the previous passes missed.

**A delta, to prepare a pass, never to replace one** (`/gap-code delta`). It reads in
full every file changed since the commit the previous pass audited, named in the header
of `docs/gap-analysis.md`, and still searches the whole repository for the mechanical
rules, the config and the gate (sections 2 to 4) and every guideline rule added since that
commit's date. It catches what a week of features introduced, cheaply, so the fix lot
lands before the full pass and that pass is spent on what only a fresh full reading
finds. It writes no `docs/gap-analysis.md`: its Haute and Moyenne findings still go through
the refutation of section 5, then to a fix lot. Skipping it is how a deliberate choice
reaches a fix lot as a defect.

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
- Read the existing `docs/gap-analysis.md` for two things only: the deviations that were
  deliberately accepted, and, for a delta, the commit the previous pass audited. A fresh
  scan finds everything else on its own, and git holds the history.
- **An accepted deviation is judged again at every pass**, against today's guideline. One
  below a version floor or a mandated configuration stands only on a named blocker (a
  missing extension, an incompatible dependency) and the date to re-check it; otherwise it
  is a finding, Haute for a security floor. Seen: PHP 8.4 kept for a month as « patched »,
  under a guideline that sets 8.5.
- Stop on an archived repository, rather than auditing a corpse: a last commit saying
  "archive", a redirect to a successor, an archived flag on the remote.

## 2. Scan the code

The guidelines are the checklist. Their forbidden anti-patterns section lists, rule by
rule, exactly what a scan looks for, and the rest of the file gives the patterns those
rules protect. A pass that leaks shows up at the next one as "new" findings that were
there all along; four measures keep the first pass close to complete.

**Mechanical rules first, run by the orchestrator, not by the agents.** Every rule that
reduces to a pattern (a raw palette colour, `target="_blank"` without `rel`, an API route
without `#[IsGranted]`, an em dash in visible text) is one
search over the whole repository, listed exhaustively before any agent starts. An agent
reading for it finds most of the hits; the search finds all of them. A rule that reduces to a pattern and is not yet refused by the
gate is a finding of its own, Moyenne, against the gate: name the rule that will close it
(`bin/check-mechanical`, an ESLint rule, a PHPStan rule) and where it runs (the hook and
`bin/ci-local`). A family that comes back from a previous pass is that finding by
definition.

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

- secrets (keys, tokens, passwords) in logs, error monitoring and URLs, and real personal
  data copied into fixtures;
- twins that must stay aligned (PHP and TS rules, copied lists, constants);
- the API contract end to end (DTO, controller, OpenAPI, generated client, form);
- calls to action and links that break: a route written by hand instead of generated, a
  page that refuses the profile it is shown to, a dead anchor. Where a working link leads
  is a product choice;
- form accessibility (names, errors, required state on the control).

**Judgement where no search reaches.** A rule that cannot be turned into a search still
gets read for: architecture boundaries, business logic in the wrong layer, a pattern that
diverges between two files that should match. Beyond the guidelines, flag only what
breaks something even when no rule covers it: a wrong result, a security smell, dead code,
a hardcoded URL that belongs in the environment, a comment or docblock the code has since
contradicted.

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
  (Biome plus oxlint for the React Compiler rules on Next and TanStack, ESLint plus
  `eslint-plugin-react-hooks` >= 7 on Symfony: `react/react-guidelines.md` §2).
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
- Weight: every session loads the whole file, so each line must serve most of them. A
  subject only some tasks need (a runbook, one integration's pitfalls) moves to `docs/`,
  with a one-line pointer left behind. Name the sections to move.

## 5. Verify before writing

Every Haute and Moyenne finding goes to a second agent, briefed by `refute-brief.md`, whose
only job is to refute it: read the code, the callers, the tests, the data, and say whether
the defect is real. A refuted
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
> Audit de main à <commit complet>, le <date>. Un delta part de ce commit.

---

## 0. Nom de la catégorie (Haute/Moyenne/Basse priorité)

**Idéal** : ce que disent les guidelines
**Actuel** : ce que fait le code

### Sous-catégorie

- [ ] `chemin/vers/Fichier.php` : description de l'écart (sûr | probable | à vérifier ; ancien | lot | feature | règle)
  - Détail, ce qu'il faut extraire, déplacer ou renommer

## Écarts assumés

- `chemin/vers/Fichier.php` : l'écart, et la raison de ne pas le corriger
```

Priorities:

- **Haute**: security (a missing floor, an unprotected route, an unvalidated payload),
  architectural violations, anything that can produce a wrong result silently.
- **Moyenne**: convention violations, naming, missing patterns.
- **Basse**: style, dead code, cosmetics.

Origin, settled by `git blame` on the cited lines against the commit the previous pass
audited:

- **règle**: the rule it breaks entered or changed in the guidelines since that pass,
  whatever the age of the lines;
- **lot**: the lines were written since, by a fix lot of that pass (its branch, or the
  integration branch it merged through);
- **feature**: the lines were written since, by any other work;
- **ancien**: the lines were already there, and that pass missed them.

## 7. Present the summary

The stack's own gaps first (runtime and dependency versions, config, the gate), then the
findings per priority, then per origin beside the previous pass's counts, then the most
critical items, then the questions for the communication team, then one line on what to
do first. The origin says where the count comes
from. `ancien` falling is the reading converging. `lot` and `feature` are defects written
since the last pass that the gate and `/review-diff` let through: a change of method answers
them, not another pass.

## 8. What the fixing has to honour

This skill fixes nothing, but the fixes decide whether the next pass finds anything. Each
lot gets `lot-brief.md`, which carries the rules: re-check on the current main, refute
before fixing, fix the family and close it in the gate, data before constraint, prove the
hook ran, `/review-diff` before merging, a report that names only what someone could have
decided otherwise. One rule stays with the orchestrator:

- **Close the fix session with a delta.** Once every lot is merged, run `/gap-code delta`
  from the commit the pass audited, fix what it finds, and only then start the next full
  pass. Ambitious lots touch hundreds of files, and one reviewer per lot cannot read them
  all; the delta splits them into zones like a pass does. Seen: between the ninth and the
  tenth pass, the fix lots changed 1 992 files, and 72 % of the tenth pass's findings sat in
  those files; the other 28 % were in files nobody had touched.

## 9. Which model, at which effort

Set `model` and `effort` on every agent this skill or its fix lots launch: this section is
the instruction that allows it. The measure is the cost of a whole task, not the price of a
token, since a cheaper model that reasons longer can cost more per task.

| Role | Model | Effort | Why |
|---|---|---|---|
| Zone and theme agents, config scan | Opus | `high` | The findings worth a pass are judgement (a twin that drifted, a test that cannot fail, a docblock the code contradicts); a weaker reader misses them and they come back at the next pass |
| Refutation of Haute and Moyenne | Opus | `high` | It reads one finding against the code, the callers and the tests |
| Fix lots on logic, data, contracts, security | Opus | `high` | It must refute before fixing and break nothing next door |
| Fix lots that are mechanical (renames, typography, texts, test names) | Opus | `low` | Fewest defects introduced, fewest tokens |

One reading of a zone misses a third to a half of what is there: the delicate zones are
worth two readings at different efforts rather than one at a higher effort. The figures
behind these choices, and the measures still open, are in `benchmarks.md`: re-read them
when a new model ships.

## Rules

- **Fix nothing.** This skill diagnoses, it does not touch source code.
- Exhaustive, not sampled. Every file, with `Glob` and `Grep`.
- Every finding names a file and a line or a method. "Some controllers are too big" is
  worthless, "`AdvertController.php:245` maps icons inline" is actionable.
- Something that looks wrong in the guidelines themselves goes to `/gap-sota`.
- **Settle what you can, then report it with its confidence**: `sûr` (the code plainly
  contradicts a rule), `probable` (it looks wrong, one thing left to check), `à vérifier`
  (a doubt nothing in the repository could settle). A doubt the code, the tests or the
  commit that introduced the line can settle is settled by the scan, not passed on. A
  confidence is not a severity, which the priority already carries.
- **A product question reaches the user only when nothing answers it**: not the code, not
  the commit that introduced it, not `not-a-finding.md`.
- **A question left open comes with the default answer the fix lot applies**, asked once in
  the summary: without a reply the default holds, so nothing waits in the file.
- Group by theme, not by file, so the result is a work plan.
- French for the prose of `gap-analysis.md`, matching the existing file.
