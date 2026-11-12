# Fix lot brief

Handed as is to every fix lot, placeholders filled in. It carries the rules a fix has to
honour: the fixes decide whether the next pass finds anything.

---

Repository: `<repo>`. Read its `AGENTS.md` first: commands, branch strategy, the project's
own rules. The guidelines are `<guidelines>`, and for writing `<dev-standards>/agent/writing.md`.

## The findings

- The reading: `<findings file>`.
- The refutation, **which prevails over the reading** (verdict, priority, right fix):
  `<verdicts file>`.
- Your lot is defined below: handle its findings only.

**Do not fix** what the refutation refutes or calls an accepted deviation, the entries
marked set aside or outside the zone, or anything `<skill>/not-a-finding.md` lists, which
you read first. A visible text keeps the words its team wrote: you fix its form only.
<project additions>

## Where to work

- Your branch, in your worktree, cut from `origin/main`, set up as `AGENTS.md` says before
  the first commit. **Prove the hook ran** at the first commit (its output): in a fresh
  worktree the hook directory may be missing, and the commits then skip every check.
- Never on `main` nor `preprod`, no deploy, never `--no-verify` nor a force push.
- Commits in the style of `git log`, without attribution lines. Push your branch as you go.
- Do not touch `docs/gap-analysis.md` nor `dev-standards`. Other lots run in parallel on
  other folders: stay in yours, and when a fix needs a file of another lot, make the
  smallest change and say so.

## What a fix has to honour

- **Re-check each finding on today's `origin/main`.** One already fixed is reported as such,
  without a commit.
- **Refute before fixing**: the code, the callers, the tests, the commit that wrote the
  lines. A deliberate deviation is reported as accepted, with its reason.
- **Fix the family, not the line**: search the whole repository for the same defect. Half
  of what a pass finds is the sibling of something the previous lot repaired in one place.
- **Close the family in the gate** when it reduces to a pattern: a PHPStan rule, an ESLint
  rule, a guard test or `bin/check-mechanical`, run by the hook and the local CI, with a
  test that makes it fail on the refused variant; the project's list of guards updated.
  Every exception list is strict, one entry per file with its reason. A fix without the
  rule is undone by the next feature.
- **Data first, constraint second.** A unique index or a stricter enum on a table that holds
  offending rows fails the deploy: say what production must be cleaned of first.
- **A fix never cites the finding's identifier in the code**: the next audit rewrites them.
  It says the cause in plain words.
- The project's own rules hold: schema changes, money rounding, the clock, comments in the
  project's language and code in English, no em dash, no middle dot.

## Checks and budget

- Iterate on what you touch: one test file, one spec, the static analysis, the type check.
- Then one full pass of the local CI at the end, through `machine-lock` (the project's CI
  takes it): if another lot holds the machine, it waits. Green on your last commit.
- Then `/review-diff` (full pass) on your branch, and fix what it returns, rerunning only
  the groups touched.
- **Budget: `<budget>`**, one full CI pass at the end, two at most if the review returns code.
- A test red then green on the next pass is a result: stop and report it (which test, at
  which step, how many tries). Never rerun until green, never raise a timeout.
- If the budget runs out, stop cleanly: everything committed and pushed, CI green on what
  is done, and the list of what is left.

## Report (in the project's language, concise)

1. Branch, last commits, local CI (commit, duration, tests per suite), the `/review-diff` verdict.
2. The findings of your lot, by family: fixed (commit), already fixed on main, accepted
   deviation (reason) or left (why).
3. The guards added.
4. **Only what someone could have decided otherwise**: a step added to or removed from a
   user's path, how long data is kept or who receives it, an access opened or closed, a
   product rule. Apply the default and give it one line. A bug fix, an alignment on a
   choice already made and a fix of form go without a word.
5. What to check in production before the deploy.
