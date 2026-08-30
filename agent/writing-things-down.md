# Writing things down

When something seems worth keeping for later, it goes in the repository. Never in the
auto-memory: that directory is machine-local, unversioned, invisible to review, and it
goes stale without telling anyone.

| What it is | Where it goes |
|---|---|
| True of this repository: architecture, conventions, a gotcha that cost an hour | its `AGENTS.md`, with `CLAUDE.md` reduced to the single line `@AGENTS.md` |
| Long enough to deserve its own file: a design system, a runbook, an ADR | its `docs/` |
| True on every project, about behaviour or writing | `dev-standards/agent/` |
| Technical, tied to one stack | that stack's file in `dev-standards/` |
| A procedure run on demand | a skill in `dev-standards/skills/` |
| Working state for the feature in progress | `.claude/plan.md`, deleted by `/deploy` |
| Where a project currently stands against its guidelines | nowhere: `/gap-code` measures it |

**The test**: would it survive a fresh clone on another machine? If not, it is in the
wrong place.

**What is not worth writing anywhere**: what the code already says, what git history
already holds, what the tool documents itself, and anything true only of this
conversation.
