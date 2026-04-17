---
name: preprod
description: Push the current feature branch to preprod for testing. Does NOT touch main.
disable-model-invocation: true
allowed-tools: Bash(git *)
---

# Push to preprod

Merge the current feature branch into `preprod` for testing, then return to the feature branch.

## Pre-checks

1. Run `git status` — if there are uncommitted changes, run `/commit` first (ask the user).
2. Verify you are NOT on `main` or `preprod`. If you are, abort with a message.
3. Run `git branch --show-current` to get the branch name.

## Steps

```bash
# 1. Push current branch
git push -u origin $(git branch --show-current)

# 2. Save current branch name
BRANCH=$(git branch --show-current)

# 3. Switch to preprod and pull
git checkout preprod && git pull

# 4. Merge the feature branch into preprod
git merge $BRANCH

# 5. Push preprod
git push

# 6. Return to the feature branch
git checkout $BRANCH
```

## After

Show:
```
✅ Merged <branch-name> into preprod
   You are back on <branch-name>
```

## Safety

- NEVER touch `main` — this skill only merges into `preprod`
- NEVER delete the feature branch — it stays for further work or future deploy
- If merge conflicts occur, stop and ask the user
