---
name: review-diff
description: Is it what was asked? Reviews the branch diff against .claude/plan.md, the guidelines and the Definition of Done, then runs /quality. Two passes - before UAT (full) and before /deploy (delta). Triggers - review, relis le diff, "ready for UAT?", "ready to deploy?".
context: fork
background: false
---

# Review the diff

The PR review of a flow with no PR. Runs twice:

- **Pass 1, before UAT**: the full branch diff. Never send to UAT what would be sent
  back.
- **Pass 2, before `/deploy`**: the delta since pass 1, plus a fresh `/quality`. When
  nothing moved, say so and give the verdict in three lines. When there was no pass 1,
  this one does the full review.

> **Arm's length, enforced.** This skill runs in a forked subagent with no access to the
> conversation, which is the point: reviewing a diff with the context that wrote it is
> the definition of a blind spot. The consequence is that `.claude/plan.md` is the only
> record you get, for the intent and for what `/live-test` found.

## 1. Gather both terms

- **The diff**: `git diff main...HEAD`, plus `git log main..HEAD --oneline`. All of it,
  not a sample.
- **The intent**: `.claude/plan.md`, the only record available in a forked context.
  Check its `Branch:` line matches the current branch, since one file holds one feature.
  A missing or mismatched plan reduces the review to the guidelines, and the verdict
  says so.

## 2. Diff against the ticket

- **Everything asked is there?** Each acceptance criterion, and where the diff answers
  it. A criterion with no answer is a gap.
- **Nothing more is there?** Every change traces to the plan. An orphan change is scope
  creep: flag it, offer to extract it.
- **Do the assumed decisions hold?** Re-read them against the final code.

## 3. Touched files against the audit grid

Whatever this review lets through, the next `/gap-code` finds: its delta pass reads every
file changed since the previous one, in full, against the same grid. The aim is that this
branch hands it nothing.

- **Read every touched file in full, not only its hunks.** A docblock, a comment, a test
  name or an OpenAPI description that the change made false counts as introduced by the
  diff. So does a sibling left behind: the same constant or rule in a TS twin, a second
  caller that still does it the old way.
- **The forbidden anti-patterns of the stack's guidelines, rule by rule**, against those
  files. Symfony and React: DTO placement, `format: 'json'` and `#[IsGranted]` on API
  routes, `requirements` on route parameters, a pure Domain/, the SDK rather than a
  hand-written fetch, RHF + Zod, `@/` imports, a fixed log message with the exception
  under `exception`. Next and TanStack Start: Base UI, the kit rather than a copy, typed
  routes, baked data.
- **The cross-cutting themes of `/gap-code`** on the touched files: personal data in logs
  and fixtures, twins that must stay aligned, the API contract end to end, where each link
  and call to action leads, form accessibility.
- **A family, not a line.** When the diff fixes or introduces a pattern, search the
  repository for its siblings. A defect that reduces to a pattern and that the gate does
  not refuse yet is a finding: name the rule that would refuse it.

Flag what the diff introduces or worsens, and what sits in a touched file. A defect in
a file the branch never opened is `/gap-code`'s business.

## 4. The Definition of Done, line by line

It is a checklist, so check it rather than trusting it was followed:

- `/quality` green, per step 5.
- No drift in the generated types, covered by `/quality`.
- **The tests owed exist and pass**: take the "Tests owed" list from `.claude/plan.md`,
  confirm each one is in the diff, and confirm the `## Live test` block reports them green.
  A test planned and not written, or written and never run, is caught here or nowhere.
- The `## Live test` block exists, the golden path passed, the E2E suite is green.
- `#[IsGranted]` and `format: 'json'` on new `/api/` routes.
- No anti-pattern from the guidelines' forbidden list.

Then the part no checklist of green ticks covers, **completeness**:

- **No dead end.** Follow the whole path, interface to backend to persisted data and
  back to the interface. A button that calls a mutation nothing ever reads is a feature
  that compiles and does not exist.
- **Whole lifecycle.** Anything persisted is created, read, updated **and** cleaned up.
  No orphan row or file left behind on delete.
- **Nothing unused.** No dependency, schema field or prop added that nothing consumes.

## 5. Delegate the mechanical checks

- Run **`/quality`**, unless it already passed green on this exact commit with nothing
  moved since, in which case name the run you are relying on. Pass 2 always re-runs it,
  after the UAT fixes. A FAIL blocks the verdict either way.
- Backstop on unfamiliar APIs: `/investigate` should have read the docs for anything the
  guidelines do not cover. If the diff uses such an API and the plan shows no sign of it,
  check it against the official docs for the version in the lockfile. An invented
  signature that compiles is what a green build does not catch.
- Never run `/live-test` from here. It belongs before the review, and a missing `## Live test`
  block is a finding: send it back rather than absorbing the step.

## 6. Verdict

```
Review : <branch> vs <ticket>
------------------------------------------
Ticket coverage :        complete | gaps listed
Scope :                  clean | N changes outside the ticket
Audit grid :             conform | deviations listed
Tests owed :             written | N missing
/quality :               PASS | FAIL
/live-test :                passed | never run
Verdict :                ready for /deploy | send back (list)
```

Every gap points at a file and a line. "Send back" lists actions, not impressions.

## Rules

- **Changes nothing.** It diagnoses. Fixes come after, then re-run it.
- Do not re-litigate the decisions settled in `/investigate`. The review checks they were
  honoured, not that they were right.
- A perfect diff on a misunderstood ticket is a failure. Ticket coverage outranks
  elegance.
