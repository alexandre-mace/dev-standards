# Zone brief

Handed as is to every zone and theme agent, placeholders filled in. The orchestrator adds
nothing but the `<…>` values: a rule missing here is added to this file, not to one brief.

---

Repository: `<repo>`, on `<branch>` at `<commit>`. The previous pass audited `<previous commit>`.

**You edit no file, commit nothing, run neither CI nor a server.** You read, and return your
result as text. A targeted test or a read-only script to prove a point is allowed.

## Read first

- The project's `AGENTS.md`.
- The stack's guidelines: `<guidelines>`, and for writing
  `<dev-standards>/agent/writing.md`. Read the sections your files touch, and always the
  forbidden anti-patterns.
- `<skill>/not-a-finding.md`: what never counts. It overrides anything below.
- The `## Écarts assumés` section of `docs/gap-analysis.md`.
<project additions>

## What a finding is

The target is zero findings, Basse included, so each one must be worth a commit: code that
contradicts a guideline, logic that is wrong or fragile, a security smell, dead code, a
comment or docblock the code contradicts, twins (a PHP and a TS rule, a copied list) that
diverge, a test that cannot fail, a hardcoded URL that belongs in the environment.
**Read the code before calling it a deviation**, and once `git log -L` on the lines.

## Your files

<the file list, or the theme and what it reads for>

## What you return

1. **Coverage**: one line per file of your list, in order, `path : rien` or
   `path : N écart(s)`. Every file appears. A theme agent lists the files it read.
2. **Findings**, one entry each:
   - `path:line` (or method) and the defect, in one or two precise sentences;
   - priority: **Haute** (security, architecture, a wrong result in silence), **Moyenne**
     (convention, naming, a missing pattern), **Basse** (style, dead code, cosmetics);
   - confidence: `sûr`, `probable` (say what is left to check) or `à vérifier`;
   - origin: `ancien` if `git blame` shows the lines at `<previous commit>`, otherwise the
     hash of the commit that wrote them; `règle` if the rule entered the guidelines since;
   - the expected fix, in one line;
   - when the defect reduces to a searchable pattern, say so: the family must be closed by
     a guard.
3. **Questions for the communication team**, if any (a fact that matters and misleads).

In the project's language (`docs/gap-analysis.md` shows it), concise, without em dashes or
middle dots.
