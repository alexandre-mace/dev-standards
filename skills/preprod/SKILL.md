---
name: preprod
description: Merges the current feature branch into preprod for UAT, then returns to it. Never touches main.
disable-model-invocation: true
allowed-tools: Bash(git *)
---

# Push to preprod

Push the branch, merge it into `preprod`, push that, come back to the branch.

**Applies to**: the Symfony + React stack, which has a long-lived `preprod` branch that
CleverCloud tracks. On Next and TanStack Start there is nothing to merge: Vercel builds a
preview per branch, so pushing the branch *is* the UAT deploy. Skip this skill there and
hand over the preview URL.

## Rules

- **Abort if the current branch is `main` or `preprod`.** This skill merges *from* a
  feature branch, never onto itself.
- **Uncommitted changes: stop and ask.** Offer `/commit`, never stash or commit silently.
- **Never touch `main`.**
- **Never delete the feature branch.** It stays for the UAT fixes and the eventual
  `/deploy`. That is the whole difference with `/deploy`.
- **A merge conflict stops everything.** Resolving it is the user's call, not a guess.
- End on the feature branch, and say so: landing the user on `preprod` without telling
  them is how the next commit goes to the wrong place.
