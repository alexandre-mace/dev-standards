# agent/: how the agent should work

Behaviour, true on every project whatever the stack. `redaction.md` governs what
gets written anywhere, files included. `output-styles/` governs the shape of a
reply, and modifies the system prompt rather than being read as context.

`install.sh` links both into `~/.claude/`. A new rule or style is a new file plus
a re-run.

| Kind of rule | Where it lives |
|---|---|
| Behaviour, or how something is written | **here** |
| Technical, tied to a stack | `symfony-react/`, `next/` or `tanstack-start/` |
| True of one repository only | that repo's `AGENTS.md` |
| A procedure run on demand | `skills/` |
| Where a project currently stands | nowhere: `/gap-code` measures it |
