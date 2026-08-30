---
name: plan
description: Step 1 of the chain. Investigates a ticket in depth, settles everything the code can settle, names the tests owed, and writes .claude/plan.md. Raises only what would genuinely change the work to do.
---

Investigate first. But investigating serves to decide, not to collect questions.

Invoked by `/ticket` as step 1, or on its own.

## Ticket

$ARGUMENTS

## 1. Read the ticket

- The *what*, the *why*, and the acceptance criteria, explicit or implicit.
- The grey areas, the contradictions, the assumptions you would make.

## 2. Investigate the code

- Locate the files, routes, entities, services and components involved. Search in
  parallel.
- Read the impacted code, not the file names: architecture, local conventions,
  dependencies, tests, side effects.
- Look for an existing pattern to reuse rather than reinvent.
- **Map the blast radius**: list the callers of every symbol touched (Grep), find the
  tests covering the area and plan to run them. Say it explicitly when the code is
  shared across pages, components or product lines. A small change in shared code is
  not a small change.
- Read the stack's guidelines and `AGENTS.md`.
- Read the docs of anything the guidelines do not cover: a library new to the repo, an
  API never used here, a framework feature absent from this codebase. Training memory
  has a cutoff and invents plausible signatures.
- Check the version in the lockfile, not the latest release.
- Follow the source hierarchy: the repo first (changelog, release notes, advisories),
  then the official docs, then the published package. A blog never establishes a fact.

## 3. Settle what can be settled

Take each grey area and resolve it yourself before considering raising it. The
answer is usually already in the code:

- Does the model force it? A NOT NULL column, a constraint, a validator that already
  blocks it.
- Does a single reading preserve the meaning of what exists? Then that is the one.
- Does the ticket answer in the negative space? "It must be possible to enter" is not
  "entering is mandatory".
- Is there a conservative default that cannot produce a wrong result? Show nothing
  rather than a maybe-wrong value; degrade rather than block.

What gets settled this way is not a question. It is an **assumed decision**: documented
in the code, announced in one line.

## 4. Name the tests the work owes

Before writing code. The reference is the Definition of Done in the stack's own
guidelines, and it does not bend per ticket. On the Symfony stack that means a functional
test for a new API route, a property-based test for money arithmetic, a Playwright spec
for a new journey, a Vitest spec for a form's 422s. A fragile component about to be
refactored owes its safety net first.

If the ticket owes none, say so and why. What is never named is never written.

## 5. Write `.claude/plan.md`

**One file, always overwritten**: it holds the feature in progress and nothing else. It
survives a compacted context, and `/review-diff` reads it instead of trusting its own
memory. `/deploy` deletes it when the feature ships.

Add `.claude/plan.md` to `.gitignore` if it is not there: it is working state, not
documentation, and committing it would put a conflict on every branch.

```markdown
# <ticket title>

Branch: feat/<scope>

## What is asked
<two or three lines, then the acceptance criteria as a list>

## Blast radius
<files and symbols touched, and who calls them>

## Assumed decisions
- <the decision> : <the reason, one line>

## Tests owed
- [ ] <the test, and where it goes>

## Raised, not blocking
- <the finding>

## Blocking
- <the question, ideally none>
```

## 6. Check in

Default: implement with your assumed decisions, stated clearly.

Raise only what meets both conditions:

- it genuinely changes the work to do, not the wording of a label;
- and no reasonable assumption lets you proceed without risking shipping the wrong
  thing, or the code simply does not hold the information.

Keep three categories separate:

- **Settled**: the answer and its reasoning, one or two lines.
- **Raised**: a real finding that needs no answer to move on (a reference table too
  thin, an earlier decision this ticket reverses, a scope that is growing). Say it,
  carry on.
- **Blocking**: ideally zero, often one. More than two or three means going back to
  step 3.

When a point blocks only one batch, ship the others and ask in parallel.

## Rules

- **Investigation first, code second.**
- An inconsistency is proven, not suspected. Half dissolve on checking: "it doesn't
  exist" becomes "it already does", "it's ambiguous" becomes "one reading holds".
- Never bluff a fact. But concluding from the code and the ticket is not bluffing, it is
  the job.
- Doubt is paid in risk, not in questions. Visible and fixable in one line: decide and
  flag. Save the question for what would be expensive or silent.
- Reuse the local patterns rather than inventing new ones.
