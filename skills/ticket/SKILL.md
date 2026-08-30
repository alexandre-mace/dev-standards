---
name: ticket
description: Investigate a product ticket in depth before any implementation, settle everything the code can settle, and write the plan down. Raises only what would genuinely change the work to do.
---

The user has pasted a ticket (typically written by their PM) into `$ARGUMENTS`. Your
job is to **investigate** first, not to rush into implementation. But investigating
serves to **decide**, not to collect questions.

## Ticket

$ARGUMENTS

## Process

### 1. Understand the ticket

- Read it closely. Identify the *what* (what is being asked), the *why* (business
  value, when stated), and the acceptance criteria, explicit or implicit.
- Note the grey areas, the potential contradictions, the assumptions you would make.

### 2. Investigate the code in depth

- Explore the codebase for the files, modules, components, routes, entities and
  services involved.
- Use the search tools (Glob, Grep, Read); run several searches in parallel when it
  helps.
- Actually read the impacted code, not just the file names: architecture, local
  conventions, dependencies, tests, possible side effects.
- Check whether a similar pattern already exists in the project, to reuse rather than
  reinvent.
- **Map the blast radius of what you are about to change**: list the callers of every
  symbol touched (Grep), find the tests covering the area and plan to run them
  (`bin/phpunit --filter`, `pnpm test`), and say explicitly when the code is shared
  across several pages, components or product lines. A small change in shared code is
  not a small change.
- Read the project's guidelines (`docs/symfony-guidelines.md`, `docs/reactony.md`,
  `docs/next-guidelines.md`, `docs/tanstack-start-guidelines.md`, `AGENTS.md`).

### 3. Settle everything that can be settled

Take each grey area from step 1 and try to resolve it **yourself** before considering
raising it. Most often the answer is already in the code:

- Does the model force the answer? (a NOT NULL column, a constraint, an existing
  validator that would already block it)
- Does a single reading preserve the meaning of what exists? Then that is the one.
- Does the ticket answer it in the negative space? ("it must be possible to enter" is
  not "entering is mandatory"; a column "offered by default" describes an overridable
  default)
- Is there a conservative default that cannot produce a wrong result? (show nothing
  rather than a possibly wrong value, degrade cleanly rather than block)

What gets settled this way is not a question: it is an **assumed decision**, which you
document in the code and announce in one line.

### 4. Name the tests the work owes

Before writing a line, say which tests this ticket requires. The Definition of Done in
the guidelines is the reference, and it is not negotiable per ticket:

- a new non-trivial API route owes a functional test (HTTP contract plus DB state)
- new money or tier arithmetic owes a property-based test
- a new user journey owes a Playwright spec
- a form component with validation owes a Vitest spec covering the 422s
- a fragile component about to be refactored owes its safety net **first**

If the ticket owes no test, say that too, and why. What is never named is never
written.

### 5. Write the plan down

Write `docs/ticket.md`, overwriting it: it always holds the feature in progress. It is
what survives a compacted context, and what `/review-diff` reads to check the work
against the intent rather than against its own memory.

```markdown
# <ticket title>

## What is asked
<two or three lines, and the acceptance criteria as a list>

## Blast radius
<the files and symbols touched, and who calls them>

## Assumed decisions
- <the decision> : <the reason, one line>

## Tests owed
- [ ] <the test, and where it goes>

## Raised, not blocking
- <the finding>

## Blocking
- <the question, ideally none>
```

### 6. Check in with the user

By default you **implement** with your assumed decisions, stating them clearly.

Only raise what meets both conditions at once:

- it genuinely changes the work to do (not just the wording of a label);
- **and** no reasonable assumption lets you move forward without risking shipping the
  wrong thing, or the code simply does not hold the information (a field that exists
  nowhere, a business fact only the product owns).

Keep the three categories separate:

- **What you settle**: the answer and its reasoning, one or two lines.
- **What you raise**: a real finding that needs no answer to move on (a reference
  table too thin, an earlier decision this ticket reverses, a scope that is growing).
  Say it, carry on.
- **What genuinely blocks**: ideally zero, often one. More than two or three means
  going back to step 3, because you have not looked hard enough.

When a point blocks only one batch, ship the others and ask in parallel rather than
stopping everything.

Either way, summarise your understanding and your plan before coding, then implement
following the project's conventions. Take the time to do it properly, no shortcuts.

## Rules

- **Investigation first, code second.** Never jump to implementation without having
  read the relevant code.
- **An inconsistency is proven, not suspected.** If you think you see one, go and check
  in the code before raising it: half of them dissolve ("it doesn't exist" becomes "it
  already does", "it's ambiguous" becomes "only one reading holds"). Raise only what
  you have observed.
- **Guessing and reasoning are not the same thing.** Never bluff about a fact: go and
  check it. But when the code and the ticket let you conclude, concluding is not
  bluffing, it is the job.
- **Doubt is paid in risk, not in questions.** Before asking, estimate what being wrong
  would cost. If the mistake is visible and fixable in one line, decide and flag it.
  Save the question for what would be expensive or silent.
- **Respect local conventions.** Reuse the existing patterns and components rather than
  inventing new ones.
