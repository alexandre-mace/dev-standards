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

Companion skills (`commit`, `gap-analysis`, `update-guidelines`) are installed globally in `~/.claude/skills/` and work with any project that has these guideline files in `docs/`.
