---
name: gap-analysis
description: Audit a whole codebase against its stack's guidelines and write every deviation into docs/gap-analysis.md. Works on the three stacks - Symfony+React, Next, TanStack Start.
---

**The guidelines are right, the code gets corrected.** The mirror skill is `/sota-gap`,
where the ecosystem is right and the guidelines get corrected.

## 1. Identify the stack, read its guidelines in full

| Stack | Detected by | Guidelines |
|---|---|---|
| Symfony + React | `symfony/framework-bundle` in `composer.json` | `docs/symfony-guidelines.md` + `docs/reactony.md` |
| Next | a `next.config.{js,ts,mjs}` | `docs/next-guidelines.md` |
| TanStack Start | `@tanstack/react-start` in `package.json` | `docs/tanstack-start-guidelines.md` |

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
rules protect. Turn each one into a search and run it across every file.

- Agents in parallel, one per area of the codebase. Every file, not a sample.
- `Glob` and `Grep` systematically: most rules become one pattern each.
- A rule that cannot be turned into a search still gets read for: architecture
  boundaries, business logic in the wrong layer, a pattern that diverges between two
  files that should match.
- Beyond the guidelines, flag what is incoherent, fragile, surprising or plainly broken
  even when no rule covers it: suspicious logic, security smells, dead code, hardcoded
  URLs that belong in the environment.

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

## 5. Write the gap analysis

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

- [ ] `chemin/vers/Fichier.php` : description de l'écart
  - Détail, ce qu'il faut extraire, déplacer ou renommer

## Écarts assumés

- `chemin/vers/Fichier.php` : l'écart, et la raison de ne pas le corriger
```

Priorities:

- **Haute**: security (a missing floor, an unprotected route, an unvalidated payload),
  architectural violations, anything that can produce a wrong result silently.
- **Moyenne**: convention violations, naming, missing patterns.
- **Basse**: style, dead code, cosmetics.

## 6. Present the summary

Findings per priority, then the most critical items, then one line on what to do first.

## Rules

- **Fix nothing.** This skill diagnoses, it does not touch source code.
- Exhaustive, not sampled. Every file, with `Glob` and `Grep`.
- Every finding names a file and a line or a method. "Some controllers are too big" is
  worthless, "`AdvertController.php:245` maps icons inline" is actionable.
- Something that looks wrong in the guidelines themselves goes to `/sota-gap`.
- Do not invent problems. Flag what genuinely deviates, or what is plainly a bug or a
  security issue.
- Group by theme, not by file, so the result is a work plan.
- French for the prose of `gap-analysis.md`, matching the existing file.
