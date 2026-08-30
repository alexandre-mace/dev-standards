---
name: diagnosing-bugs
description: Disciplined bug-fix loop - reproduce, one hypothesis, instrument, root cause, regression test. Triggers - bug, regression, "it stopped working", production error, Sentry issue, 500.
---

# Diagnosing bugs

Takes the place of `/plan` as step 1 when the task is "fix this behaviour" rather than
"build this thing". Typical inputs: a bug ticket from the PM, a Sentry issue, a finding
from `/check-logs`, something broken in UAT. The rest of the chain is unchanged:
`/quality` → `/verify` → `/commit` → `/review-diff` → `/preprod` → `/deploy`.

Six steps, in this order.

## 1. Reproduce

Write the failing reproduction before thinking about the fix:

- a PHPUnit or Vitest test if the bug is reachable from a test, which is the best case
  because it becomes step 6;
- otherwise a script, a `curl` against the route, a Playwright scenario, or the browser
  itself through `/verify` when the bug only shows in the interface;
- for a Sentry bug: start from the real event (payload, stack, breadcrumbs through the
  Sentry MCP), never from an imagined reconstruction. The `sentry-fix-issues` skill knows
  that plumbing: lean on it here and to resolve the issue at step 6.

**No reproduction, no fix.** If reproducing is genuinely impossible, a bug depending on
inaccessible production state, say so and compensate at step 3 with instrumentation in
production.

## 2. Locate, and state one hypothesis

- Read the error in full: the real message, the real line.
- `git log -- <area>`: recent changes first. Half of all bugs are in the last commit
  that touched the area.
- State the hypothesis in one sentence, out loud in the conversation. An unstated
  hypothesis cannot be refuted.

## 3. Test the hypothesis with an instrument, never with the fix

A targeted log, a `dump()`, a unit test of the suspect function, a SQL query by hand.
The hypothesis is confirmed or it falls. If it falls: back to step 2, next hypothesis.
A speculative fix destroys the value of the reproduction.

## 4. Write `.claude/plan.md`, and announce it

The root cause is known, the fix is not written yet: this is the moment the user can
object cheaply. `/review-diff` runs in a forked context, so this file is also the only
intent it will get.

- What broke, and the root cause in one line.
- What the fix will change, and its blast radius.
- The regression test, under "Tests owed", ticked at step 6.

## 5. Fix the root cause, not the symptom

- If the symptom is in A but the cause is in B, fix B. Padding A with a null check, a
  try/catch or a default value leaves the bug alive for the next caller.
- Blast radius on B: list the callers (Grep), run the tests covering the area
  (`bin/phpunit --filter`, `pnpm test`). A root-cause fix touches shared code far more
  often than a patch does.

## 6. The reproduction becomes the regression test

- The test from step 1, or a clean version of it, joins the suite. It must have failed
  before the fix and pass after. Show both outputs.
- Close the original loop: a Sentry issue gets resolved; a `/check-logs` finding gets
  noted; a PM ticket gets a one-sentence answer on what was fixed and why it happened.

## Rules

- Never announce "fixed" on the disappearance of the symptom alone: the regression test
  is what settles it.
- Flag in one line a fragile pattern the bug reveals elsewhere, as a candidate for
  `/gap-analysis`. Do not go and fix it everywhere: the scope of the fix stays the bug.
- If two hypotheses fall in a row, stop and present the state: what is ruled out, what
  remains possible, what is missing to decide.
