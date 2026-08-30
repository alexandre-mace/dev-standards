---
name: check-logs
description: Audits the health of a production app by correlating CleverCloud logs, Messenger state and Sentry issues. Use when checking on prod, triaging Sentry, or chasing down noise in the logs.
---

# Check prod logs

Pull the signals, correlate them against the current code, triage.

The window differs by source, because they do not keep their data for the same time.
CleverCloud retains logs for 7 days, so `--since 7d` is the ceiling and asking for more
returns 7 days plus the illusion of a wider audit. Sentry keeps its issues far longer:
take the widest window it will give you.

## Where the signals live

### CleverCloud, Sentry, Messenger

```bash
# both stream by default: --before bounds the range and gives a finite tail
clever accesslogs --alias <ALIAS> --since <PERIOD> --before 30s
clever logs       --alias <ALIAS> --since <PERIOD> --before 30s
```

Pull the HTTP status with `awk` rather than a field index: `clever accesslogs` is alpha
and its columns are positional.

Messenger state, from the prod `DATABASE_URL` in `clever env`:

```sql
SELECT message_type, failure_type, COUNT(*) AS n, MAX(received_at) AS last
FROM processed_messages
WHERE failure_type IS NOT NULL AND received_at > NOW() - INTERVAL '<PERIOD>'
GROUP BY 1,2 ORDER BY n DESC;
```

Sentry issues come from its MCP.

### Vercel and Convex

`npx convex logs` follows a deployment, `npx convex dashboard` opens it. Vercel keeps its
own runtime logs per deployment.

Nothing else here is documented yet, because nothing else has been run yet. The first
audit on this host records what worked.

### A static site with no backend

There is nothing to audit. Say so rather than producing a report out of Vercel's
request logs.

## Correlate before judging

Cross each signal with the code as it stands now:

- a Sentry culprit file → `git log -- <file>`: a fix may already have landed since the
  last event
- a 500 endpoint in the access logs → find the controller, form a hypothesis
- a Messenger failure → the same error should appear in Sentry; if it does not, the
  logging is the finding

## Triage

| Pattern | Decision |
|---|---|
| 404 on stale JS bundles after a deploy | **Ignore** in `sentry.yaml`: users on cached HTML, not a bug |
| 404 on entities that no longer exist | **Ignore**, same family: a legitimate 404 belongs in access logs, not Sentry |
| 422 with a message | **Keep**: validation doing its job, but an empty message is a real bug |
| Upstream 5xx with retry already configured | **Resolve**: Sentry reopens on regression, which is the safety net |
| Real bug, few events, code path clearly wrong | **Fix**, commit referencing the issue id so Sentry auto-resolves on deploy |
| Nothing seen for more than 14 days | **Bulk resolve**, same safety net |

**Never auto-resolve without asking**, even though the OAuth scope allows it. And when
fixing: `/quality`, push, wait for the deploy, then resolve. Resolving first loses the
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
event count and a decision.

**Close the loop.** For each real bug, one question in the report: *which step of the
chain should have caught this, and why did it not?* A 500 on an unvalidated payload
points at `/plan`; a regression on an untouched page points at a missing spec, so at
`/verify`; a pattern repeated across files points at `/gap-analysis`.
