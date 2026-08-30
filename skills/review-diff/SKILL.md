---
name: review-diff
description: Review the branch diff against the ticket, the guidelines and the Definition of Done, then run /quality. Two passes - before /preprod (full) and before /deploy (delta). Triggers - review, relis le diff, "ready for UAT?", "ready to deploy?".
disable-model-invocation: true
---

# Review the diff

The PR review of a flow that has no PR. It runs **twice**:

- **Pass 1, before `/preprod`.** Full review of the branch diff. Its job: never send
  something to UAT that would be sent back, because the PM's time is worth more than
  a round trip.
- **Pass 2, before `/deploy`.** Review of the **delta** since pass 1 (`git diff` from
  the commit reviewed then, typically the UAT fixes), plus a fresh `/quality`. If
  nothing moved since pass 1, say so and give the verdict in three lines: this pass
  should be quick, not ceremonial. If there was no pass 1 (a small feature), this pass
  does the full review.

The principle holding both: a commit is a save point, the merge is the irreversible
act. Nothing lands on `main` unreviewed, UAT fixes included.

> **Review at arm's length.** You are reviewing a diff you probably wrote, with the
> context that produced it, which is the definition of a blind spot. Two habits fight
> it: read the intent **from `docs/ticket.md`**, not from your memory of the
> conversation, and prefer running this review in a subagent with fresh context when
> the feature is substantial.

## 1. Gather both sides of the comparison

- **The full diff**: `git diff main...HEAD` (and `git log main..HEAD --oneline` for the
  shape of the commits). Not a sample: the whole diff.
- **The intent**: `docs/ticket.md`, written by `/ticket`. Falling back to the ticket
  pasted in the conversation is second best; with no written intent at all, the review
  is reduced to the guidelines and must say so explicitly.

## 2. The diff against the ticket: the heart of the skill

Three questions, in this order:

- **Is everything asked for there?** Take each acceptance criterion and point at where
  the diff answers it. A criterion with no answer is a gap to list.
- **Is nothing more there?** Every change must trace back to the ticket or to an
  assumed decision recorded in `docs/ticket.md`. An orphan change is scope creep: flag
  it, offer to pull it into its own commit or branch. Don't let the review itself widen
  the scope either.
- **Do the assumed decisions hold?** Re-read the ones written down against the final
  code.

## 3. The diff against the guidelines

Re-read the diff (not the whole project: that is `/gap-analysis`) with the stack's
guidelines in mind. Symfony and React: DTO patterns, `format: 'json'` and
`#[IsGranted]` on API routes, a pure Domain/, the SDK rather than a hand-written
fetch, RHF + Zod forms, `@/` imports. Next and TanStack Start: Base UI, the kit rather
than a copy, typed routes, baked data. Flag only what the diff introduces or worsens.

## 4. The Definition of Done, line by line

The guidelines carry a Definition of Done. It is a checklist, so check it, rather than
trusting that it was followed:

- `/quality` green, **run now**, not remembered from earlier
- no drift in the generated types (covered by `/quality`)
- **the tests the ticket owed exist**: open `docs/ticket.md`, take the "Tests owed"
  list, and confirm each one is in the diff. A test that was planned and not written
  is a gap, and this is the only place it gets caught.
- `/verify` was run and the golden path passed
- `#[IsGranted]` and `format: 'json'` on any new `/api/` route
- no anti-pattern from the guidelines' forbidden list

## 5. Delegate the mechanical checks

- Run **`/quality`** (mandatory). A FAIL blocks the verdict.
- Run **`/check-implementation`** only if the diff uses an API or a pattern unusual for
  this project (a new library, a Symfony component not yet used, a recent React
  feature). On a diff made of patterns already proven in the repo it is noise: don't
  run it, and say so.
- Do **not** run `/verify` from here. It belongs before the review, and if it never ran,
  that is itself a finding: send it back rather than absorbing the step.

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

Every gap or deviation points at a file and a line. A "send back" verdict lists
actions, not impressions.

## Rules

- This skill **changes nothing**: it diagnoses. Fixes come after, then it is re-run.
- Do not re-litigate the decisions settled during `/ticket`: they were announced, the
  review checks they were honoured, not that they were the right call.
- A perfect diff on a misunderstood ticket is a failure: ticket coverage outranks the
  elegance of the code.
