---
name: deploy
description: Merges the current feature branch into main and cleans up. The irreversible act, invoked by a human only.
disable-model-invocation: true
allowed-tools: Bash(git *)
---

# Deploy to production

Push the branch, merge it into `main`, push that, delete the branch local and remote.

**Applies to**: all three stacks. On Vercel as on CleverCloud, `main` is what production
tracks, so this merge **is** the deploy. It is the one irreversible step of the chain.

## Rules

- **Nothing reaches `main` without `/review-diff`**, UAT fixes included. If the last
  review predates the last commit, stop and review first.
- **Abort if the current branch is `main` or `preprod`.**
- **Uncommitted changes: stop and ask.** Offer `/commit`, never commit silently.
- **Never force push.** A merge conflict stops everything and goes back to the user.
- **Delete `.claude/plan.md`** after the merge: the feature has shipped, and a stale plan
  would be read as the current one by the next `/review-diff`.
- Report what was merged and that the branch is gone, both sides.
