---
name: feature
description: Run a ticket end to end through the whole chain, in order, stopping at the gates that need a human. Triggers - feature, "prends ce ticket", "déroule la procédure", a pasted ticket with no other instruction.
---

# Feature, end to end

Drives the full chain on one ticket. The order is not a suggestion: each step produces
what the next one checks.

**Stops at two gates, always**: a blocking question from `/ticket`, and the UAT. Never
merges to `main` on its own.

## The chain

| # | Step | Done when |
|---|---|---|
| 1 | `/ticket` | `docs/ticket.md` written: criteria, blast radius, assumed decisions, tests owed |
| 2 | **Gate** | Zero blocking question, or the user has answered it |
| 3 | `/feature-start` | Branch cut, plan clear |
| 4 | Implement | The stack's playbook followed, and the tests named at step 1 actually written |
| 5 | `/verify` | Golden path and one edge case exercised in a browser, console and network clean |
| 6 | `/quality` | Every check PASS |
| 7 | `/commit` | Work saved |
| 8 | `/review-diff` | Verdict "ready", against `docs/ticket.md` and the guidelines |
| 9 | `/preprod` | Symfony stack only. Elsewhere, push the branch: Vercel builds the preview |
| 10 | **Gate** | UAT by a human. Stop here and hand over the URL |

After UAT, the short leg: fix, `/verify` the fix, `/quality`, `/commit`, `/review-diff`
on the delta, `/deploy`.

A bug instead of a ticket: `/diagnosing-bugs` replaces step 1, the rest is identical.

## Rules

- **Never skip a step to save time.** A skipped step is a check nobody ran; that is the
  whole point of the chain.
- **A red step stops the chain.** Fix, re-run that step, then move on. Do not carry a
  FAIL forward hoping a later step catches it.
- **Steps 5 and 6 are not interchangeable.** Green checks on a feature nobody ran, and
  a working feature with red checks, are two different failures.
- **Step 4 is where the work is.** The chain does not make implementation easier, it
  makes its result checkable. Do not rush it because the surrounding steps are
  mechanical.
- **Announce the step you are entering**, one line. The user must be able to interrupt
  at any point and know where things stand.
- **Never merge to `main` autonomously.** The commit is a save point, the merge is
  irreversible.
