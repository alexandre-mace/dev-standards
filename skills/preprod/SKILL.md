---
name: preprod
description: Merges the current feature branch into preprod for UAT, then returns to it. Never touches main. Triggers - "pousse en preprod", "mets en preprod", "envoie en recette", once the user has given the go.
allowed-tools: Bash(git *)
---

# Push to preprod

Push the branch, merge it into `preprod`, push that, come back to the branch.

**Applies to**: the Symfony + React stack, which has a long-lived `preprod` branch that
CleverCloud tracks. On Next and TanStack Start there is nothing to merge: Vercel builds a
preview per branch, so pushing the branch is the UAT deploy. Skip this skill there and
hand over the preview URL.

## Rules

- **Only on the user's explicit go, in this conversation.** Preprod is shared: a merge
  there replaces what someone else may be testing.
- **Never delete the feature branch.** It stays for the UAT fixes and the eventual
  `/deploy`.
- Abort if the current branch is already `main` or `preprod`.
- Stop and ask when changes are uncommitted. Offer `/commit`, never stash or commit
  silently.
- A merge conflict stops everything. Resolving it is the user's call, not a guess.
- Confirm the deployment succeeded before handing over. A failed build sends the user to
  test the previous version.
- End on the feature branch, and say so: landing the user on `preprod` without telling
  them is how the next commit goes to the wrong place.
