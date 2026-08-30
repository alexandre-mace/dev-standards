---
name: commit
description: Commits the staged changes with a Conventional Commits message matching the repository's own style.
allowed-tools: Bash(git *)
---

# Commit

Read the staged diff, write the message, commit.

## What goes in the commit

**Never commit a file the user has not seen.**

- Something already staged: commit exactly that, nothing more. The user chose it.
- Nothing staged: show `git status --short`, and ask before running `git add -A`. That
  command sweeps in everything, and a scratch file, a `.env` or a forgotten `dump()`
  would ride along unseen.
- The user named what to commit, here or earlier: stage those paths and only those.

## The message

`type(scope): short description`, Conventional Commits, with the house rules:

- **Scope from the repository's own vocabulary**: `git log --oneline -20` shows the
  scopes already in use. Consistency matters more than the choice itself.
- Lower case, no full stop. French or English, following the recent commits.
- Several domains touched: the main one, or a scope that covers them.
- **Never a `Co-Authored-By` line.**
- **Never amend.** A commit is a save point; rewriting one destroys the point.

Pass the message through a quoted heredoc, so backticks and `$` in the body are not
expanded by the shell:

```bash
git commit -m "$(cat <<'EOF'
type(scope): description
EOF
)"
```
