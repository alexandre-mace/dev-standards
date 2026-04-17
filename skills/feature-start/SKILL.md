---
name: feature-start
description: Start a new feature branch from main with an auto-generated name
allowed-tools: Bash(git *)
---

Create a new feature branch from an up-to-date main.

## Steps

1. Run `git status` to check for uncommitted changes. If there are uncommitted changes, warn the user and stop.
2. Run `git log --oneline -5` and `git diff HEAD --stat` to understand the current context
3. Decide on a branch name based on either:
   - `$ARGUMENTS` if provided (use it as-is or adapt to kebab-case)
   - The conversation context — what the user has been working on or asked for
4. Run: `git checkout main && git pull && git checkout -b feat/<branch-name>`
5. Confirm the branch was created with `git branch --show-current`

## Branch naming rules

- Format: `feat/<scope>` where scope is in **kebab-case**
- Keep it short (2-4 words max), descriptive of the feature
- Use French or English depending on the context
- Examples:
  - `feat/indicateurs-pee` for adding PEE indicators
  - `feat/dashboard-filters` for adding dashboard filters
  - `feat/export-csv-leviers` for CSV export of leviers
  - `feat/fix-bingo-overflow` for fixing a layout bug

## Rules

- NEVER force push or delete branches
- If `$ARGUMENTS` is provided, use it to derive the branch name (convert to kebab-case if needed)
- If no arguments, infer the best name from conversation context
- Always start from an up-to-date main
- After creating the branch, display the branch name to the user
