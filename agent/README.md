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

`install.sh` symlinks the rules here into `~/.claude/rules/`, loaded at the start of
every session, and `output-styles/` into `~/.claude/output-styles/`. A new rule or style
is a new file plus a re-run.

Rules are context: Claude reads them and follows them, without a guarantee. An output
style changes the system prompt itself, so tone and format belong there rather than in
a rule.

`redaction.md` stays in French on purpose: it governs French prose, and its
examples have to be French to mean anything.
