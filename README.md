# Dev Standards

Coding guidelines for Symfony + React (TypeScript) projects.

## Files

- **`symfony-guidelines.md`** — Backend architecture: Domain/Service/Controller, enums, repositories, commands, DTOs, Doctrine patterns
- **`reactony.md`** — Symfony + React integration: forms, mutations, type pipeline, error handling, SDK usage

## Usage

Clone this repo and symlink the files into your project's `docs/` directory:

```bash
ln -s ~/dev/dev-standards/symfony-guidelines.md docs/symfony-guidelines.md
ln -s ~/dev/dev-standards/reactony.md docs/reactony.md
```

Then reference them in your project's `CLAUDE.md`:

```markdown
## Architecture & Guidelines

Detailed conventions are in `docs/`:
- **`docs/symfony-guidelines.md`** — Backend architecture
- **`docs/reactony.md`** — Symfony + React integration

These docs are the source of truth for code conventions.
```

## Claude Code Skills

Custom skills live in `skills/` and are symlinked from `~/.claude/skills/` so they're discoverable by Claude Code globally.

**Installed skills:**

| Skill | Purpose |
|---|---|
| `commit` | Conventional Commits message generator for staged changes |
| `feature-start` | Start a new feature branch from an up-to-date main |
| `preprod` | Push current feature branch to the `preprod` branch for testing |
| `deploy` | Merge current feature branch into `main` and push |
| `quality` | Run all quality checks (PHPStan, PHP-CS-Fixer, tsc, ESLint, Prettier) |
| `ticket` | Deep-analyze a product ticket before coding (forces questions, prevents premature implementation) |
| `gap-analysis` | Audit a codebase against the guidelines, produce `docs/gap-analysis.md` |
| `update-guidelines` | Web-first review of the guidelines themselves against latest ecosystem best practices |
| `check-implementation` | Verify recent code against latest official docs of the techs used |
| `check-logs` | Prod health audit: CleverCloud logs + Messenger DB + Sentry, prioritized report |

### Setup on a new machine

```bash
git clone git@github.com:alexandre-mace/dev-standards.git ~/dev/dev-standards
~/dev/dev-standards/skills/install.sh
```

The `install.sh` script is idempotent — safe to re-run when skills are added or renamed. It creates (or refreshes) symlinks from `~/.claude/skills/<name>/` to `~/dev/dev-standards/skills/<name>/`.

### Adding a new skill

1. Create `skills/<skill-name>/SKILL.md`
2. Run `./skills/install.sh` to register it
3. Commit + push

All machines that `git pull` and rerun `install.sh` will get the new skill.
