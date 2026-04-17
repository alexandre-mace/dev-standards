---
name: commit
description: Commit staged changes with a Conventional Commits message
disable-model-invocation: true
allowed-tools: Bash(git *)
---

Create a git commit following Conventional Commits convention.

## Steps

1. Run `git status` to see staged and unstaged changes
2. Run `git diff --cached` to analyze what's staged. If nothing is staged, stage all modified/untracked files with `git add -A`, then re-run `git diff --cached`.
3. Run `git log --oneline -5` to see recent commit style

## Commit message format

```
type(scope): description concise en minuscules
```

### Types

| Type | Usage |
|------|-------|
| `feat` | Nouvelle fonctionnalité ou ajout significatif |
| `fix` | Correction de bug |
| `refactor` | Restructuration sans changement de comportement |
| `docs` | Documentation uniquement |
| `test` | Ajout ou modification de tests |
| `chore` | Maintenance, dépendances, config |
| `perf` | Amélioration de performance |

### Scope

Le scope est le domaine métier impacté, en kebab-case. Exemples : `farm-model`, `parcours`, `adverts`, `skills-assessment`, `search-farm`, `foncier`, `dashboard`, `auth`, `api`, `css`, `typescript`.

Si les changements touchent plusieurs domaines, utiliser le scope principal ou un scope englobant.

### Description

- Minuscules, pas de point final
- En français ou en anglais (suivre le style des commits récents)
- Concis mais descriptif

## Rules

- If `$ARGUMENTS` is provided, use it as the commit message directly (but validate the format)
- If no arguments, auto-generate the message from the diff analysis
- NEVER add a Co-Authored-By line
- NEVER amend previous commits — always create a new one
- Use a HEREDOC to pass the commit message:

```bash
git commit -m "$(cat <<'EOF'
type(scope): description
EOF
)"
```

- After committing, run `git log --oneline -1` to confirm
