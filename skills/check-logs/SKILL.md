---
name: check-logs
description: Prod health audit correlating CleverCloud access and application logs, Messenger DB state and Sentry issues, into a triaged report. Use monthly or quarterly to separate noise from real bugs and keep Sentry clean.
---

# Check prod logs

**Applies to**: apps on CleverCloud with Sentry wired up, which today means the
Symfony + React stack. Needs the `clever` CLI authenticated and the Sentry MCP connected.

Ask for the period (default `7d`), the CleverCloud alias and the Sentry project if not
given. Pull the three sources in parallel, correlate them against the current code, then
triage.

## The two commands that do not behave as expected

```bash
# streaming by default: --before bounds the range and gives a finite tail
clever accesslogs --alias <ALIAS> --since <PERIOD> --before 30s
clever logs       --alias <ALIAS> --since <PERIOD> --before 30s
```

`clever accesslogs` is alpha and its columns are positional: pull the HTTP status with
`awk` rather than assuming a field index.

Messenger state, when the app uses the Doctrine transport, from the prod `DATABASE_URL`
in `clever env`:

```sql
SELECT message_type, failure_type, COUNT(*) AS n, MAX(received_at) AS last
FROM processed_messages
WHERE failure_type IS NOT NULL AND received_at > NOW() - INTERVAL '<PERIOD>'
GROUP BY 1,2 ORDER BY n DESC;
```

Sentry comes from its MCP, which documents its own tools: search the unresolved issues
for the period and read them.

## Correlate before judging

A raw list of issues is not an audit. Each signal gets crossed with the code as it is
**now**:

- a Sentry culprit file → `git log -- <file>`: a fix may already have landed since the
  last event
- a 500 endpoint in the access logs → find the controller, form a hypothesis
- a Messenger failure → the same error should appear in Sentry; if it does not, the
  logging is the finding

## Triage

The judgement this skill exists for. Volume is not severity.

| Pattern | Decision |
|---|---|
| 404 on stale JS bundles after a deploy | **Ignore** in `sentry.yaml`: users on cached HTML, not a bug |
| 404 on entities that no longer exist | **Ignore**, same family: a legitimate 404 belongs in access logs, not Sentry |
| 422 with a message | **Keep**: validation doing its job, but an *empty* message is a real bug |
| Upstream 5xx with retry already configured | **Resolve**: Sentry reopens on regression, which is the safety net |
| Real bug, few events, code path clearly wrong | **Fix**, commit referencing the issue id so Sentry auto-resolves on deploy |
| Nothing seen for more than 14 days | **Bulk resolve**, same safety net |

**Never auto-resolve without asking**, even though the OAuth scope allows it. And when
fixing: `/quality`, push, wait for the deploy, *then* resolve. Resolving first loses the
issue if the fix does not hold.

## Gotchas worth knowing

- **An empty-message exception in Sentry** usually means a resolver's default branch.
  Read the event's Local Variables at the failing frame to see the input that caused it.
  The oversize-upload case is documented in `reactony.md` §3.
- **A Messenger worker exiting in a loop every few seconds**: `--keepalive` against the
  Doctrine connection's `idle_connection_ttl`. Drop `--keepalive`.

## Report

Group by return on investment, not by volume: mass pollution first, then real bugs,
then what can be resolved (upstream flake, already fixed, stale). Every line carries its
event count and a decision, never just a description.

**Close the loop.** For each real bug, one question in the report: *which step of the
chain should have caught this, and why did it not?* A 500 on an unvalidated payload
points at `/plan`; a regression on an untouched page points at a missing spec, so at
`/verify`; a pattern repeated across files points at `/gap-analysis`. A workflow that
never learns from what escapes it stays at the level of the day it was written.
