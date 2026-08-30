---
name: ticket
description: Takes a pasted ticket, feature or bug, and runs it through the whole chain in order, stopping at the gates that need a human. Triggers - a pasted ticket, "prends ce ticket", "déroule la procédure".
---

# Ticket, end to end

`$ARGUMENTS` holds the ticket. Run the chain on it. The order is not a suggestion: each
step produces what the next one checks.

Copy this checklist into your reply and tick as you go.

```
- [ ] 1. Plan            /plan  (bug instead of a feature: /diagnosing-bugs)
- [ ] 2. GATE            plan announced, no blocking question left
- [ ] 3. Branch          feat/<scope>, cut from an up-to-date main
- [ ] 4. Implement       playbook followed, tests named in step 1 written
- [ ] 5. Clean           /quality
- [ ] 6. Works           /live-test
- [ ] 7. Saved           /commit
- [ ] 8. Right           /review-diff
- [ ] 9. Handover        the debrief, below
- [ ] 10. UAT            /preprod, or push the branch for a Vercel preview
- [ ] 11. GATE           a human tests it
```

After the UAT: fix, `/quality`, `/live-test`, `/commit`, `/review-diff` on the delta, then
**the user** runs `/deploy`.

## The two gates

- **Step 2, the plan.** Announce the assumed decisions and the tests owed, a few lines,
  before writing any code. Stop and wait only if `/plan` raised something blocking;
  otherwise carry on without waiting for an answer. The point is that the user can
  object cheaply: twenty lines of plan cost nothing to read, a five hundred line diff
  built on a wrong premise costs the whole implementation. This is the only place a
  wrong direction is cheap to catch.
- **Step 11, the UAT.** It belongs to a human. Hand over the URL and stop.

## Step 9, the debrief

Ten lines, no more, written for a developer who did not type this code but owns it. The
diff is in git and the plan is in `.claude/plan.md`; neither tells you what it was like
to build.

```
Debrief : <feature>
- Shape:     what moved, structurally. Not a file list, git has that.
- Decisions: the two or three that were not obvious, each with its why in one line.
- Fragile:   where I would look first if this breaks in three months.
- Dropped:   a reasonable alternative I considered and did not take, and why.
- You own:   a new dependency, a new pattern, a config that will need attention later.
```

Rules for it:

- **Name what you are unsure about.** A place where you guessed, or where the tests are
  thinner than you would like, is the single most useful line in the whole debrief.
- "Nothing non-obvious happened" is a valid debrief. Manufacturing interest is worse than
  admitting the ticket was mechanical.
- No restating the ticket, no listing files, no commentary on the quality of the work.
- Say it in the user's language, not in the language of the codebase.

## The three questions, in order

They are not interchangeable, which is why they are three steps:

| Step | Question | Answered by |
|---|---|---|
| `/quality` | Is it clean? | The machine, in seconds |
| `/live-test` | Does it work? | Running it, in minutes |
| `/review-diff` | Is it what was asked? | Judgement, against `.claude/plan.md` |

Cheapest first: never drive a browser against code that does not compile. And never let
one step absorb another, because green checks on a feature nobody ran and a working
feature with red checks are two different failures.

## Step 3, cutting the branch

Once the plan is clear, not before.

```bash
git checkout main && git pull && git checkout -b feat/<scope>
```

`<scope>` in kebab-case, two to four words, French or English following the repo. Stop
and ask when changes are uncommitted, rather than stashing them.

## Rules

- **Never skip a step.** A skipped step is a check nobody ran.
- A red step stops the chain. Fix it, re-run that step, then move on.
- Three attempts on the same red step, then stop and report: what is ruled out, what
  remains possible, what is missing to decide. An unbounded retry loop is how an hour
  disappears into a flapping test.
- Step 4 is where the work is. The chain does not make implementing easier, it makes the
  result checkable. Do not rush it because the surrounding steps are mechanical.
- Announce each step in one line, so the user can interrupt and know where things stand.
- Never run `/deploy` yourself. The merge is irreversible, and it is the user's call.
