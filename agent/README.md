# agent/: how the agent should work

Behaviour, true on every project whatever the stack. `writing.md` governs how
anything gets written, files included. `writing-things-down.md` governs where it
goes. `long-running-checks.md` governs a slow check run in a loop (a full CI, an E2E
suite). `output-styles/` governs the shape of a reply, and modifies the system prompt
rather than being read as context.

`install.sh` links both into `~/.claude/`. A new rule or style is a new file plus
a re-run.

| Kind of rule | Where it lives |
|---|---|
| Behaviour, or how something is written | **here** |
| Technical, tied to a stack | `symfony-react/`, `next/` or `tanstack-start/` |
| True of one repository only | that repo's `AGENTS.md` |
| A procedure run on demand | `skills/` |
| Where a project currently stands | nowhere: `/gap-code` measures it |
