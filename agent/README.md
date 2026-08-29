# agent/: how the agent should work

Behavioural rules, true on every project whatever the stack and whatever the
language. Nothing about a framework belongs here.

| Kind of rule | Where it lives |
|---|---|
| Behaviour, writing, register, research | **here**, `agent/` |
| Technical, tied to a stack | `symfony-react/`, `next/` or `tanstack-start/` |
| True of one repository only | that repo's `AGENTS.md` |
| A procedure run on demand | `skills/` |
| Where a project currently stands | nowhere: that is not documentation. `/gap-analysis` lists deviations from the guidelines, not progress |

`install.sh` symlinks every file here into `~/.claude/rules/`, which Claude Code
loads at the start of every session. A new rule is a new file plus a re-run.

`redaction.md` stays in French on purpose: it governs French prose, and its
examples have to be French to mean anything.
