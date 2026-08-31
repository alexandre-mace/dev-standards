---
name: attack-surface
description: What can someone reach, and what can they do with it? Maps every entry point of the project, follows untrusted input to where it lands, and proves each finding by reproducing it. Triggers - revue de sécurité, "audit sécu", "est-ce que c'est exploitable ?", before opening a project to real users.
---

**`/gap-code` asks whether the code matches the rules. This asks what someone could do that
nobody thought of.** The two miss different things, which is why they are two skills. A project
can be perfectly conformant and still hand its database to whoever changes an id in a URL.

**Applies to**: all three stacks. **Only on the user's own projects**, never on a third party's
system. Read and reproduce, never exploit further than the proof needs.

## 1. Map the entry points

Everything that accepts something from outside, listed before anything is judged. A surface you
did not list is a surface you did not review, and this is the step that gets rushed.

| Stack | Where they are |
|---|---|
| All | HTTP routes, forms, file uploads, webhooks, crons taking arguments, anything reading a query parameter |
| Symfony | `bin/console debug:router`, the EasyAdmin CRUDs, the Messenger handlers |
| Next | server actions, route handlers, `proxy.ts`, `generateStaticParams` on a dynamic route |
| TanStack Start | server functions, API routes, and every Convex `query`/`mutation`/`action` that is not `internal` |

Two that hide: a **webhook** is authenticated by a signature or by nothing at all, and a **Convex
function that is not `internalQuery`/`internalMutation` is callable by any browser**, whatever the
UI does with it.

## 2. For each one, three questions

- **Who can reach it?** Not who the UI lets reach it. Anonymous, any logged-in user, or the right
  user. A guard on the page that renders the form does not guard the endpoint behind it.
- **On which object?** An identifier in a payload is an identifier the caller chose. Reaching the
  endpoint legitimately and passing someone else's id is the single most common real hole.
- **What does the answer contain?** A response built from a whole record publishes the whole
  record, hash and internal note included, even when the interface displays three fields.

## 3. Follow untrusted input to where it lands

Input is anything a user influences: a payload, a parameter, a header, a filename, a CMS field, a
webhook body, an upstream API's response. The finding is at the landing point, not at the entrance.

| It lands in | What to look for |
|---|---|
| A query | String concatenation into DQL or SQL, an ORDER BY or a table name built from input |
| HTML | `dangerouslySetInnerHTML`, `\|raw` in Twig, an `href` built from data (`javascript:` executes) |
| A shell or a filesystem | An interpolated command, a path built from a name (`../`), an unchecked archive extraction |
| An HTTP call | A URL that depends on input, which reaches the internal network (SSRF) |
| A redirect | A destination taken from a parameter, which sends the user anywhere |
| A log or Sentry | A secret in a query string, personal data in a breadcrumb |
| A file store | An upload whose type is trusted from its extension or its declared MIME, a public bucket |

## 4. Secrets and what crosses to the client

- Read the environment variables and sort them: what may reach the bundle (`NEXT_PUBLIC_`,
  `VITE_`) and what may not. Then check the second list never appears in a file that also runs on
  the client, following the `"use client"` boundary transitively.
- Grep the history for a committed secret, not only the working tree.
- A key in a query string is logged by the client library at INFO. See the stack's guidelines.

## 5. Prove it

This is what separates this pass from a checklist, and the step to spend the time on. A hole that
is only reasoned about is a hypothesis, and half of them are wrong for a reason the code does not
show.

- **Call the endpoint yourself**, with no session, then with a session that should not have access,
  then with another user's object identifier. `curl` on the local server, or the browser.
- **On a local or preproduction environment**, never production, and never a destructive operation
  even there. Reading someone else's record is proof enough; there is no need to delete it.
- **Then verify the refusal is genuine.** A 403 that comes from a missing button rather than from
  the server is not a refusal.
- Note what could not be proved and say so, rather than reporting it at the same level as the rest.

## 6. Report

Grouped by severity, each finding naming its file and line, who can trigger it, and what it gives
them.

```
Attack surface : <project> @ <date>
Entry points :   N mapped (M routes, K server functions, J webhooks)
Critique :   reachable by anyone, gives data or execution
Haute :      reachable by a logged-in user on someone else's object
Moyenne :    needs an unlikely condition, or leaks without giving access
Basse :      hardening, defence in depth
Unproven :   what was suspected and could not be reproduced, and why
```

For each: the reproduction, in one command or three clicks. Without it, the finding will be
argued about rather than fixed.

## Rules

- **Change nothing.** This skill diagnoses. Fixes come after, then re-run it.
- **Prove or downgrade.** Anything unreproduced goes in `Unproven`, never in `Critique`.
- Severity is what the hole gives, not how clever it is. Reading every user's email address beats
  a theoretical race condition.
- **A missing rule is a finding too.** No Content Security Policy, no rate limit on a public POST,
  no redaction of secrets in the logs: the absence is silent, and it is exactly what nobody
  notices.
- Something that deviates from the guidelines without being exploitable belongs to `/gap-code`.
- Say plainly what was not covered. A pass that stopped at the API and never opened the crons is a
  partial pass, and reporting it as complete is worse than not running it.
