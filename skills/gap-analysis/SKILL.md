---
name: gap-analysis
description: Audit a whole codebase against its stack's guidelines and write every deviation into docs/gap-analysis.md. Works on the three stacks - Symfony+React, Next, TanStack Start.
---

Audit a full codebase against its stack's guidelines.

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
- Then read the existing `docs/gap-analysis.md`: what was already found, what is ticked.
- **Archived repository: stop.** A last commit saying "archive", a redirect to a
  successor, an archived flag on the remote. Report it rather than auditing a corpse.

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

A scan of `src/` never opens a config file, so the code can be pristine while the config
silently lags. Real case: PHPStan stuck at `level: 8` against a guideline mandating
`max`, invisible to a code-only scan, build green throughout.

- **PHPStan level** in `phpstan.dist.neon` against what the guideline mandates. A
  lower level is a real gap even with a green build. Check a baseline is used to climb.
- **Quality gate completeness**: does the pre-commit hook (`.husky/pre-commit`) and the
  CI (`.github/workflows/*.yml`) each run every mandated check? Name any that is
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

They steer every future agent session, and they rot silently.

Run the checker shipped with this skill: it confronts the verifiable claims of
`AGENTS.md` / `CLAUDE.md` against the repository (package manager vs lockfile, scripts
that do not exist, paths that do not exist).

```bash
python3 ~/.claude/skills/gap-analysis/check-agent-files.py .
```

Then read them for what a script cannot see:

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

- **Fix nothing.** This skill diagnoses, it does not touch source code.
- Exhaustive, not sampled. Every file, with `Glob` and `Grep`.
- Preserve ticked items: anything `[x]` in the previous file was fixed, keep it so
  progress stays visible.
- Every finding names a file and a line or a method. "Some controllers are too big" is
  worthless, "`AdvertController.php:245` maps icons inline" is actionable.
- Something that looks wrong in the guidelines themselves goes to `/sota-gap`.
- Do not invent problems. Flag what genuinely deviates, or what is plainly a bug or a
  security issue.
- Group by theme, not by file, so the result is a work plan.
- French for the prose of `gap-analysis.md`, matching the existing file.
