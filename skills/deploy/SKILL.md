---
name: deploy
description: Deploy the current feature branch to production (main). Commits, pushes, merges into main, and cleans up.
disable-model-invocation: true
allowed-tools: Bash(git *)
---

# Deploy to production

Merge the current feature branch into `main` and push.

## Pre-checks

1. Run `git status` — if there are uncommitted changes, run `/commit` first (ask the user).
2. Verify you are NOT on `main` or `preprod`. If you are, abort with a message.
3. Run `git branch --show-current` to get the branch name.

## Steps

```bash
# 1. Push current branch
git push -u origin $(git branch --show-current)

# 2. Switch to main and pull latest
git checkout main && git pull

# 3. Merge the feature branch
git merge <branch-name>

# 4. Push main
git push

# 5. Delete the feature branch (local + remote)
git branch -d <branch-name>
git push origin --delete <branch-name>
```

## After deploy

Show:
```
✅ Deployed <branch-name> to main
   Branch <branch-name> deleted (local + remote)
```

## Safety

- NEVER run on `main` or `preprod` branch
- NEVER force push
- If merge conflicts occur, stop and ask the user
