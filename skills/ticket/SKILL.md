---
name: ticket
description: Takes a pasted ticket, feature or bug, and carries it to a branch ready for UAT, stopping only where a human decides. Triggers - a pasted ticket, "prends ce ticket", "déroule la procédure".
---

# Ticket, end to end

`$ARGUMENTS` holds the ticket. Carry it until it is done, in the sense below. How to get
there is yours to organise. What "done" means, and the three decisions that belong to the
user, are not.

## Done means

- **Understood.** `/investigate` wrote `.claude/plan.md` (`/diagnosing-bugs` for a bug), and its
  assumed decisions and tests owed were announced before any code.
- **On its own branch**, `feat/<scope>`, cut from an up-to-date `main`.
- **Tested.** Every test the plan owed exists, and was seen failing before the change and
  passing after. Code you modified whose behaviour no test pinned now has one, in the same
  branch: deferring it to a dedicated pass means never.
- **Clean.** `/quality` is green.
- **Seen working.** `/live-test` walked the golden path and one edge case in a browser, or
  named what it could not reach.
- **Saved.** `/commit`.
- **Right.** `/review-diff` found no gap against the plan.
- **Handed over.** The debrief below is written, and the branch is in UAT: `/preprod`, or
  the pushed branch for a Vercel preview.

A check with nothing to check (no browser surface, no test owed) is declared with its
reason. A check skipped in silence is a check nobody ran.

## Order, where it matters

- The plan before the branch, the branch before the code.
- Cheapest check first: never drive a browser against code that does not compile.
- `/live-test`, then `/commit`, then `/review-diff`. The review runs in a forked context
  that sees only the committed branch and `.claude/plan.md`, where `/live-test` writes its
  report.
- Keep the three checks apart. `/quality` asks whether it is clean, `/live-test` whether it
  works, `/review-diff` whether it is what was asked. Green checks on a feature nobody ran
  and a working feature with red checks are two different failures.

## What belongs to the user

- **A blocking question from `/investigate`.** Announce the plan in a few lines and carry on,
  unless it raised something blocking. Twenty lines of plan cost nothing to read, a five
  hundred line diff built on a wrong premise costs the whole implementation.
- **The UAT.** Hand over the URL and stop.
- **Shipping.** `/deploy` runs only on the user's explicit go, and always through the
  skill, never with hand-typed git commands: the skill is where the checks live.

After the UAT, the fixes meet the same definition of done, `/review-diff` runs on the
delta, then `/deploy` on the go.

## Minor changes

A wording fix, a colour, a label, a one-line correction with no logic behind it: the full
definition of done costs more than the change. `/quality`, look at the result, `/commit`,
then ship it by whatever the repository's own flow is, in its `AGENTS.md`. No plan, no
gate, and **no question before shipping**. Asking "shall I deploy?" on a two-line change
is friction, not safety, and it trains the habit of not reading the ones that matter.

It stops being minor the moment it touches money, permissions or personal data, changes a
schema, adds a dependency, alters a shared component, or leaves you unsure. Then the full
definition applies, gates included. Unsure counts as not minor.

## The debrief

Short, written for a developer who did not type this code but owns it. The diff is in
git and the plan is in `.claude/plan.md`; neither tells you what it was like to build.

```
Debrief : <feature>
- Shape:     what moved, structurally. Not a file list, git has that.
- Decisions: the two or three that were not obvious, each with its why in one line.
- Fragile:   where I would look first if this breaks in three months.
- Dropped:   a reasonable alternative I considered and did not take, and why.
- You own:   a new dependency, a new pattern, a config that will need attention later.
```

- **Name what you are unsure about.** A place where you guessed, or where the tests are
  thinner than you would like, is the single most useful line in the whole debrief.
- "Nothing non-obvious happened" is a valid debrief. Manufacturing interest is worse than
  admitting the ticket was mechanical.
- No restating the ticket, no listing files, no commentary on the quality of the work.
- Say it in the user's language, not in the language of the codebase.

## Rules

- Announce each check in one line as it starts ("Clean: /quality"), so the user knows
  where things stand and can interrupt.
- A red check stops the work. Fix it, re-run it, then move on.
- Three attempts on the same red check, then stop and report: what is ruled out, what
  remains possible, what is missing to decide. An unbounded retry loop is how an hour
  disappears into a flapping test.
- The implementation is where the work is. The checks do not make it easier, they make the
  result checkable. Do not rush it because the checks around it are mechanical.
- The branch: `git checkout main && git pull && git checkout -b feat/<scope>`, `<scope>` in
  kebab-case, two to four words, French or English following the repo. Stop and ask when
  changes are uncommitted, rather than stashing them.
