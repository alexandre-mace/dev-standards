---
name: commit
description: Commits the current changes with a Conventional Commits message matching the repository's own style.
allowed-tools: Bash(git *)
---

# Commit

## What goes in the commit

Compare the modified files against the work just done. Stop and name anything unrelated:
a leftover from earlier work, a file nobody meant to touch.

Otherwise commit everything modified, since nothing is usually staged, and list the
files in the output.

## The message

`type(scope): short description`, Conventional Commits, with the house rules:

- **Scope from the repository's own vocabulary**: `git log --oneline -20` shows the
  scopes already in use. Consistency matters more than the choice itself.
- Lower case, no full stop. French or English, following the recent commits.
- Use the main domain when the change spans several, or a scope that covers them.
- Never a `Co-Authored-By` line.
- Never amend a commit.
- Add a body only when the change needs explaining.

Pass the message through a quoted heredoc, so backticks and `$` in the body are not
expanded by the shell:

```bash
git commit -m "$(cat <<'EOF'
type(scope): description
EOF
)"
```
