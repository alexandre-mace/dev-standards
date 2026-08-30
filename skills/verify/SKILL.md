---
name: verify
description: Does the feature actually work? Drives it in a real browser - golden path, one edge case, console and network - and reports proof. Triggers - verify, teste la feature, "ça marche vraiment ?", "montre-moi".
---

# Does it work?

Static analysis proves it compiles. Tests prove what they cover. Only running it proves
the user gets what the ticket asked for.

Runs after the implementation, before `/quality` and `/review-diff`.

**Applies to**: all three stacks. Skip it when the change has no observable surface (a
console command, a migration, a CI tweak) and say so.
**Target**: localhost by default. Pass a URL to check a preview or production instead.

Use `/run` or the Browser pane for the mechanics of launching and driving the app. What
follows is what `/run` does not know.

## Stack gotchas

- **Symfony needs two processes**, the PHP server and Vite. Without Vite the React
  islands render nothing and you will chase a phantom bug.
- Next and TanStack Start: one `pnpm dev`.
- Server will not start: that is the finding. Report it and stop, do not work around it.

## What to walk

**The golden path**: the journey the ticket describes, end to end, as a user does it.
Not a smoke test of the page that was touched.

- Real controls only, never by calling functions in the console.
- Read the page after each step that changes the screen. A screenshot proves pixels,
  reading the DOM proves content.
- Submit forms for real and confirm the effect: the toast, the redirect, the row that
  appears, the value that survives a reload.

**One edge case**, the one that would actually happen:

- a required field left empty, and the message that comes back
- a server 422 rendered per field rather than swallowed into a mute toast
- the empty list, the missing image, the file that is too large
- mobile width, if the feature has any layout to it

## Then run the specs

The hand walk covers the new path. The Playwright suite covers everything the change
could have broken without you noticing, and it is the only assertion that survives you.

```bash
pnpm test:e2e                       # whole suite
pnpm test:e2e <spec>                # while iterating
```

- **A spec the plan owed must have been seen to fail before the fix, and pass after.**
  A new spec that passes on the first run proves nothing: it may be asserting nothing.
- A failure here is a finding, whether it belongs to this feature or not: a spec broken
  by a neighbouring change is exactly what this step is for.
- Suite too slow to run whole on each pass: run the specs touching the feature while
  iterating, and the whole suite once before `/review-diff`. Say which one you ran.
- No suite in the project at all: say so. The guidelines mandate one, so its absence is
  a `/gap-analysis` finding, not a neutral fact.

## What to read

A screen that looks right and logs errors is not right.

- **Console**: every error, and every warning the feature introduced. Key warnings,
  hydration mismatches and `act()` warnings count.
- **Network**: status codes, and no duplicate or runaway request. A `useQuery` firing on
  every render shows up here and nowhere else.
- **Server logs**: on Symfony, a 500 swallowed by a catch is invisible in the browser.

## Report

```
Verify : <feature> @ <target>
Golden path :  OK | broken at <step>
Edge case :    <which one> : OK | broken
E2E suite :    N passed | N failed (which) | scope run
Console :      clean | N errors, M new warnings
Network :      clean | <what is wrong>
Proof :        <screenshot, or the trace that matters>
```

## Rules

- **Never hand the check back to the user.** "You can test it at localhost:3000" is the
  failure mode this skill exists to remove.
- **A finding here outranks a green suite.** Broken golden path: stop and fix.
- **Fix in the source, never in the page.** The console reads state, it does not patch it.
- **Name what you could not verify.** A real payment, a signed document, a third-party
  callback: hand that part to the UAT explicitly rather than implying full coverage.
