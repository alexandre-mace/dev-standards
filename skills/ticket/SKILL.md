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
- [ ] 2. GATE            no blocking question left
- [ ] 3. Branch          feat/<scope>, cut from an up-to-date main
- [ ] 4. Implement       playbook followed, tests named in step 1 written
- [ ] 5. Works           /verify
- [ ] 6. Clean           /quality
- [ ] 7. Saved           /commit
- [ ] 8. Right           /review-diff
- [ ] 9. UAT             /preprod, or push the branch for a Vercel preview
- [ ] 10. GATE           a human tests it
```

After the UAT: fix, `/verify`, `/quality`, `/commit`, `/review-diff` on the delta, then
**the user** runs `/deploy`.

## The two gates

- **Step 2**: `/plan` raised something blocking. Stop, ask, wait.
- **Step 10**: the UAT belongs to a human. Hand over the URL and stop.

## The three questions, in order

They are not interchangeable, which is why they are three steps:

| Step | Question | Answered by |
|---|---|---|
| `/verify` | Does it work? | Running it |
| `/quality` | Is it clean? | The machine |
| `/review-diff` | Is it what was asked? | Judgement, against `docs/plan.md` |

Green checks on a feature nobody ran, and a working feature with red checks, are two
different failures.

## Step 3, cutting the branch

Once the plan is clear, not before.

```bash
git checkout main && git pull && git checkout -b feat/<scope>
```

`<scope>` in kebab-case, two to four words, French or English following the repo.
Uncommitted changes: stop and ask, never stash silently.

## Rules

- **Never skip a step.** A skipped step is a check nobody ran.
- **A red step stops the chain.** Fix it, re-run that step, then move on. Never carry a
  FAIL forward hoping a later step catches it.
- **Step 4 is where the work is.** The chain does not make implementing easier, it makes
  the result checkable. Do not rush it because the surrounding steps are mechanical.
- **Announce each step in one line**, so the user can interrupt and know where things
  stand.
- **Never run `/deploy` yourself.** The commit is a save point, the merge is
  irreversible, and it is the user's call.
