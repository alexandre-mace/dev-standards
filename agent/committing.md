# Committing and pushing

A change the user asked for is committed and pushed once it is done, without asking
again: the request is the go. "Shall I commit?" after the work is friction, and it trains
the habit of not reading the questions that matter.

- **Where.** In `dev-standards`, on `main`. In a project, on the work's own branch, never on
  `main`: there a commit is a deploy waiting for the next push.
- **What.** The files of the requested change only. Someone else's uncommitted change stays
  out, and the report says it is there.
- **Through the gate.** The pre-commit hook and `/commit`'s message rules. A failing hook is
  fixed, never bypassed with `--no-verify`.
- **Not covered.** Deploying (`/deploy` waits for the user's go or the word `ship`), merging
  into a project's `main`, force-pushing, pushing a branch that is not this work's, or
  rewriting a commit already pushed.

The report names what was committed and pushed, and where.
