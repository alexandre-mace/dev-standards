---
name: commit
description: Commit staged changes with a Conventional Commits message
disable-model-invocation: true
allowed-tools: Bash(git *)
---

Create a git commit following Conventional Commits convention.

## Steps

1. Run `git status` to see staged and unstaged changes
2. Run `git diff --cached` to analyze what's staged. If nothing is staged, show the user the `git status --short` list and **ask before staging** with `git add -A`. Never auto-stage silently: a stray scratch file, `.env`, or debug artifact would ride into the commit unseen. If the user already named what to commit in `$ARGUMENTS` or the conversation, stage exactly that instead.
3. Run `git log --oneline -5` to see recent commit style

## Commit message format

```
type(scope): short description in lower case
```

### Types

| Type | Use |
|------|-------|
| `feat` | A new feature or a significant addition |
| `fix` | A bug fix |
| `refactor` | Restructuring with no behaviour change |
| `docs` | Documentation only |
| `test` | Adding or changing tests |
| `chore` | Maintenance, dependencies, config |
| `perf` | A performance improvement |

### Scope

The business domain touched, in kebab-case. Take the vocabulary from the project's own
recent commits rather than inventing one: `git log --oneline -20` shows the scopes
already in use, and consistency matters more than the choice itself.

When the changes span several domains, use the main one, or a scope that covers them.

### Description

- Lower case, no full stop
- French or English, following the style of the recent commits
- Short but descriptive

## Rules

- If `$ARGUMENTS` is provided, use it as the commit message directly (but validate the format)
- If no arguments, auto-generate the message from the diff analysis
- NEVER add a Co-Authored-By line
- NEVER amend previous commits : always create a new one
- Use a HEREDOC to pass the commit message:

```bash
git commit -m "$(cat <<'EOF'
type(scope): description
EOF
)"
```

- After committing, run `git log --oneline -1` to confirm
