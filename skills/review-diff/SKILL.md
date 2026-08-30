---
name: review-diff
description: Is it what was asked? Reviews the branch diff against docs/plan.md, the guidelines and the Definition of Done, then runs /quality. Two passes - before /preprod (full) and before /deploy (delta). Triggers - review, relis le diff, "ready for UAT?", "ready to deploy?".
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

> **Arm's length.** You are reviewing a diff you probably wrote, with the context that
> produced it. Two habits fight that: read the intent from `docs/plan.md`, not from
> memory, and run this in a subagent with fresh context on a substantial feature.

## 1. Gather both terms

- **The diff**: `git diff main...HEAD`, plus `git log main..HEAD --oneline`. All of it,
  not a sample.
- **The intent**: `docs/plan.md`. The ticket pasted in the conversation is second
  best. No written intent at all: the review is reduced to the guidelines, and says so.

## 2. Diff against the ticket

- **Everything asked is there?** Each acceptance criterion, and where the diff answers
  it. A criterion with no answer is a gap.
- **Nothing more is there?** Every change traces to the ticket or to an assumed decision
  in `docs/plan.md`. An orphan change is scope creep: flag it, offer to extract it.
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
- **the tests owed exist**: take the "Tests owed" list from `docs/plan.md` and confirm
  each one is in the diff. A test planned and not written is caught here or nowhere.
- `/verify` ran and the golden path passed
- `#[IsGranted]` and `format: 'json'` on new `/api/` routes
- no anti-pattern from the guidelines' forbidden list

## 5. Delegate the mechanical checks

- Run **`/quality`**. Mandatory. A FAIL blocks the verdict.
- Run **`/check-implementation`** only when the diff uses something the guidelines do
  not cover: a new library, an API not yet used in the repo. On patterns already proven
  in the repo it is noise: skip it and say so.
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
/verify :                passed | never run
/quality :               PASS | FAIL
/check-implementation :  PASS | not relevant here
Verdict :                ready for /deploy | send back (list)
```

Every gap points at a file and a line. "Send back" lists actions, not impressions.

## Rules

- **Changes nothing.** It diagnoses. Fixes come after, then re-run it.
- **Do not re-litigate the decisions settled in `/ticket`.** The review checks they were
  honoured, not that they were right.
- **A perfect diff on a misunderstood ticket is a failure.** Ticket coverage outranks
  elegance.
