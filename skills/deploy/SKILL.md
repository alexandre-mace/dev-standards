---
name: deploy
description: Merges the current feature branch into main and cleans up. The irreversible act, invoked by a human only.
disable-model-invocation: true
allowed-tools: Bash(git *), Bash(gh *)
---

# Deploy to production

Push the branch, merge it into `main`, push that, delete the branch local and remote.

**Applies to**: all three stacks. On Vercel as on CleverCloud, `main` is what production
tracks, so this merge is the deploy.

## Rules

- **Nothing reaches `main` without `/review-diff`**, UAT fixes included. If the last
  review predates the last commit, stop and review first.
- Abort if the current branch is `main` or `preprod`.
- Stop and ask when changes are uncommitted. Offer `/commit`, never commit silently.
- Never force push.
- A merge conflict stops everything and goes back to the user.
- Delete `.claude/plan.md` after the merge: a stale plan would be read as the current
  one by the next `/review-diff`.
- Confirm the deployment succeeded after the push. A failed build leaves production on
  the previous version with nobody told.
- **A green local gate is not a green CI.** After the push, `gh run list --branch <branch>
  --limit 1`, then wait for the verdict before announcing anything, and read
  `gh run view <id> --log-failed` on a failure. CI runs in another environment, with other
  fixtures and other users: a spec that has never run there hides its environment traps.
- Report what was merged, and that the branch is gone local and remote.
