---
name: check-logs
description: Prod health audit combining CleverCloud access logs, application logs, Messenger DB state, and Sentry issues. Use monthly/quarterly to triage prod noise, spot real bugs, and keep Sentry clean. Produces a prioritized report with suggested actions (fix / ignore / bulk resolve).
---

# Check prod logs

End-to-end hygiene pass on a running prod app. Pulls signals from three places, correlates them against the current codebase, and produces an actionable report.

Works on any app deployed to CleverCloud with Sentry wired up. Assumes the `clever` CLI is authenticated and the Sentry MCP is connected (via `claude mcp add --transport http sentry https://mcp.sentry.dev/mcp` + `/mcp` OAuth).

## Inputs

Ask the user if not provided:
- **Period** (default `7d`) — `1d`, `7d`, `14d`, `30d`, etc.
- **CleverCloud app alias** (default: ask `clever applications` and pick the prod one)
- **Sentry org / project** (default: ask `find_organizations` + `find_projects` and pick the matching one)

## Pipeline

Run steps in parallel where possible to stay fast.

### 1. Access logs — HTTP status distribution

```bash
clever accesslogs --alias <ALIAS> --since <PERIOD> --before 30s 2>&1 \
  | awk '{for(i=1;i<=NF;i++) if($i ~ /^[45][0-9][0-9]$/) {print $i; break}}' \
  | sort | uniq -c | sort -rn
```

Then drill into each non-trivial status:
- Top **404** endpoints → separate bot noise (WordPress probes, `.env`, etc.) from real missing resources
- Top **5xx** endpoints → these are always real app bugs worth investigating
- 422 → usually user-input validation working as intended

### 2. Application logs — deprecations + crashes

```bash
clever logs --alias <ALIAS> --since <PERIOD> --before 30s 2>&1 \
  | grep -iE "deprecat" | sort | uniq -c | sort -rn
```

Filter out npm install deprecations (repeat on every deploy) from PHP runtime deprecations (real). Also check for messenger worker crash patterns (SSL eof, unexpected exit codes).

### 3. Database — Messenger state (if Symfony + doctrine messenger)

If the app uses Symfony Messenger with Doctrine transport and zenstruck/messenger-monitor:

```sql
-- Recent failures
SELECT message_type, failure_type, COUNT(*) AS n, MAX(received_at) AS last
FROM processed_messages
WHERE failure_type IS NOT NULL AND received_at > NOW() - INTERVAL '<PERIOD>'
GROUP BY 1,2 ORDER BY n DESC;

-- Throughput sanity check
SELECT DATE(received_at) AS day, COUNT(*) total,
       COUNT(*) FILTER (WHERE failure_type IS NOT NULL) AS failed
FROM processed_messages
WHERE received_at > NOW() - INTERVAL '<PERIOD>'
GROUP BY 1 ORDER BY 1 DESC;
```

Get the prod `DATABASE_URL` via `clever env --alias <ALIAS>` and connect with `psql` (use `export PGPASSWORD=...` inline).

### 4. Sentry — unresolved issues

Use the Sentry MCP:

```
search_issues(
  organizationSlug=<ORG>, projectSlugOrId=<PROJECT>,
  naturalLanguageQuery="all unresolved issues from the last <PERIOD>",
  limit=30
)
```

For each issue, consider:
- **Event count + last seen** — active vs stale
- **Culprit file** — does it match what the code looks like NOW, or was it fixed in a recent commit?
- **Category** — 404 noise / upstream flake / real app bug / infra blip

### 5. Correlation pass

Cross-reference the three sources with the current codebase:

- Sentry issue's culprit file → `git log -- <file>` to see if a recent fix landed since the last event
- Access log 500 endpoints → locate the controller + repro hypothesis
- Messenger failures → correlate with Sentry (same error should be in both)

### 6. Report

Produce a markdown table organized by **ROI** (impact / effort):

| Category | Example |
|---|---|
| 🔴 **Pollution massive** | High-volume noise drowning real signals (e.g. stale `/build/assets/*.js` 404s after deploy). Fix via Sentry `ignore_exceptions` or CSS-level drop. |
| 🟠 **Real bugs to fix** | Actionable, low-volume, recent. Propose one-liner fix where possible. |
| 🟠 **Upstream flaky** | External API 5xx that already retries. Resolve in Sentry (auto-reopen on regression = our safety net). |
| 🟡 **Benign validation** | 422 / business-rule rejections working as intended. Resolve. |
| 🟢 **Already fixed** | Issues whose culprit code has been rewritten since lastSeen. Resolve with confidence. |
| 🗑️ **Stale** | lastSeen > 14d without recurrence. Bulk resolve — Sentry regression detection is the safety net. |

## Decisions — fix / ignore / resolve

| Pattern | Decision |
|---|---|
| 404 on stale JS bundles after deploy | **Ignore** in sentry.yaml (`NotFoundHttpException` or path pattern). Users on cached HTML, not a bug. |
| 404 on entities that no longer exist (deleted farm, removed page) | **Ignore** same family — legitimate 404s, belong in access logs not Sentry. |
| 422 UnprocessableEntityHttpException | **Keep** — but investigate if empty message (see "MapUploadedFile gotcha" below). |
| External API 5xx with retry already configured | **Resolve** — Sentry auto-reopens on regression. |
| Empty-string enum values at DTO level | **Coerce to null** in controller before denormalize (pattern from `ProfileController::save`). |
| Real app bug with < 10 events and code path clearly wrong | **Fix + commit referencing issue ID** (`Fixes LAGRANGE-XYZ`) — Sentry auto-resolves on deploy. |

## Triage safety rules

- **NEVER auto-resolve without confirmation** unless the user explicitly said "bulk resolve". Our OAuth scope includes Triage but we ask first.
- When fixing, follow up with: verify CI (`/quality`), push, wait deploy, then resolve the Sentry issue (not the other way around).
- For bulk resolve of stale issues: sanity-check with `lastSeen < NOW() - 14d` filter, then fire in parallel.

## Known gotchas on this stack

### CleverCloud

- `clever logs` is streaming by default — pair `--since` with `--before 30s` to bound the range and get a finite tail.
- `clever accesslogs` is alpha; the column format is positional, use `awk` to grab the HTTP status robustly.
- Messenger worker with `--keepalive` may interact badly with `idle_connection_ttl` on the Doctrine connection → 5s exit loop (observed 2026-04-17, fixed by removing `--keepalive`).

### Symfony 8 `MapUploadedFile`

- When PHP drops an oversize upload at the SAPI level (`upload_max_filesize`), the resolver sees null and throws `HttpException(422)` with **empty message** — not a structured violations response.
- Front-side size guards in the component prevent this cleanly; a kernel.exception listener that unwraps `ValidationFailedException` from `HttpException::getPrevious()` is the systemic fix if needed.

### Sentry empty-message events

- An empty message `UnprocessableEntityHttpException` or similar usually indicates the resolver's default branch — always look at the Sentry event's **Local Variables** (Sentry prints them at the failing frame) to see the actual input that triggered it.

## Output skeleton

```markdown
# Prod health audit — {date} ({period})

## HTTP distribution
- Total: X req, X% 2xx, X% 3xx, X% 4xx, X% 5xx

## 🔴 Pollution massive
- [ISSUE-123] X events — reason — suggested action

## 🟠 Real bugs
- [ISSUE-456] X events — reason — suggested fix (file:line)

## 🟠 Upstream flaky
- [ISSUE-789] — already retried, resolve?

## 🟢 Already fixed since lastSeen
- [ISSUE-…] — fixed by commit abc1234

## 🗑️ Stale (lastSeen > 14d)
- Bulk resolve: LAGRANGE-1, LAGRANGE-2, … (N issues)

## Recommendations
- P1: …
- P2: …
- P3: …

## Backlog côté toi (non-code)
- …
```
