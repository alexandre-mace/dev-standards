---
name: review-diff
description: Is it what was asked? Reviews the branch diff against .claude/plan.md, the guidelines and the Definition of Done, then runs /quality. Two passes - before /preprod (full) and before /deploy (delta). Triggers - review, relis le diff, "ready for UAT?", "ready to deploy?".
context: fork
background: false
---

# Review the diff

The PR review of a flow with no PR. Runs twice:

- **Pass 1, before `/preprod`**: the full branch diff. Never send to UAT what would be
  sent back.
- **Pass 2, before `/deploy`**: the delta since pass 1, plus a fresh `/quality`. Nothing
  moved? Say so, verdict in three lines. No pass 1 happened? This one does the full
  review.

A commit is a save point, the merge is irreversible. Nothing lands on `main` unreviewed,
UAT fixes included.

> **Arm's length, enforced.** This skill runs in a forked subagent with **no access to
> the conversation**, which is the point: reviewing a diff with the context that wrote it
> is the definition of a blind spot. Anthropic's own `/code-review` forks for the same
> reason. The consequence is that `.claude/plan.md` is the only intent you get, so a
> feature with no written plan cannot be reviewed against its ticket, only against the
> guidelines.

## 1. Gather both terms

- **The diff**: `git diff main...HEAD`, plus `git log main..HEAD --oneline`. All of it,
  not a sample.
- **The intent**: `.claude/plan.md`, the only one available in a forked context.
  **Check its `Branch:` line matches the current branch**: one file holds one feature, so
  a mismatch means it belongs to other work and must not be used. Missing or mismatched:
  the review is reduced to the guidelines, and says so in the verdict.

## 2. Diff against the ticket

- **Everything asked is there?** Each acceptance criterion, and where the diff answers
  it. A criterion with no answer is a gap.
- **Nothing more is there?** Every change traces to the ticket or to an assumed decision
  in `.claude/plan.md`. An orphan change is scope creep: flag it, offer to extract it.
- **The assumed decisions hold?** Re-read them against the final code.

## 3. Diff against the guidelines

The diff only, not the project: that is `/gap-analysis`. Flag only what the diff
introduces or worsens.

- Symfony and React: DTO patterns, `format: 'json'` and `#[IsGranted]` on API routes, a
  pure Domain/, the SDK rather than a hand-written fetch, RHF + Zod, `@/` imports.
- Next and TanStack Start: Base UI, the kit rather than a copy, typed routes, baked data.

## 4. The Definition of Done, line by line

It is a checklist, so check it rather than trusting it was followed:

- `/quality` green, **run now**, not remembered
- no drift in the generated types (covered by `/quality`)
- **the tests owed exist and pass**: take the "Tests owed" list from `.claude/plan.md`,
  confirm each one is in the diff, and confirm `/verify` ran them green. A test planned
  and not written, or written and never run, is caught here or nowhere.
- `/verify` ran, the golden path passed, and the E2E suite is green
- `#[IsGranted]` and `format: 'json'` on new `/api/` routes
- no anti-pattern from the guidelines' forbidden list

## 5. Delegate the mechanical checks

- Run **`/quality`**, unless it already passed green **on this exact commit** and nothing
  has moved since: then say which run you are relying on. In pass 2, after UAT fixes, it
  always re-runs. A FAIL blocks the verdict either way.
- **Backstop on unfamiliar APIs**: `/plan` should have read the docs for anything the
  guidelines do not cover. If the diff uses such an API and the plan shows no sign of it,
  check it now against the official docs for the version in the lockfile. An invented
  signature that compiles is exactly what a green build does not catch.
- Do **not** run `/verify` from here. It belongs before the review. If it never ran,
  that is a finding: send it back rather than absorbing the step.

## 6. Verdict

```
Review : <branch> vs <ticket>
------------------------------------------
Ticket coverage :        complete | gaps listed
Scope :                  clean | N changes outside the ticket
Guidelines :             conform | deviations listed
Tests owed :             written | N missing
/quality :               PASS | FAIL
/verify :                passed | never run
Verdict :                ready for /deploy | send back (list)
```

Every gap points at a file and a line. "Send back" lists actions, not impressions.

## Rules

- **Changes nothing.** It diagnoses. Fixes come after, then re-run it.
- **Do not re-litigate the decisions settled in `/ticket`.** The review checks they were
  honoured, not that they were right.
- **A perfect diff on a misunderstood ticket is a failure.** Ticket coverage outranks
  elegance.
