---
name: verify
description: Exercise the feature in a real browser - golden path, one edge case, console and network clean, proof captured. Triggers - verify, teste la feature, "ça marche vraiment ?", "montre-moi", before /review-diff.
---

# Verify in the browser

Static analysis proves it compiles. Tests prove what they cover. Only running it proves
the user gets what the ticket asked for.

Runs after the implementation, before `/quality` and `/review-diff`.

**Applies to**: all three stacks. Skip it when the change has no observable surface (a
console command, a migration, a CI tweak) and say so.

**Target**: localhost by default. Pass a URL to verify a preview or production instead.

## 1. Get the app running

Use the Browser pane, never a backgrounded `pnpm dev`: the pane is what gives you the
DOM, the console and the network.

- Start it from `.claude/launch.json`; write the file if it does not exist.
- Next, TanStack Start: one `pnpm dev`.
- Symfony: **two processes**, the PHP server and Vite. Preview the PHP one, start Vite
  alongside. Without Vite the React islands render nothing and you will chase a phantom.
- Server will not start: that is the finding. Report it, stop, do not work around it.

## 2. Walk the golden path

The journey the ticket describes, end to end, as a user does it. Not a smoke test of
the page that was touched.

- Navigate, click, fill and submit with the real controls, never by calling functions in
  the console.
- Read the page after each step that changes the screen. A screenshot proves pixels,
  `read_page` proves content and structure.
- Submit forms for real, and confirm the effect: the toast, the redirect, the row that
  appears, the value that survives a reload.

## 3. Walk one edge case

The one that would actually happen, not the exotic one:

- a required field left empty, and the message that comes back
- a server 422 rendered per field rather than swallowed into a mute toast
- the empty list, the missing image, the file that is too large
- mobile width, if the feature has any layout to it

## 4. Read the instruments

A screen that looks right and logs errors is not right.

- **Console**: every error, and every warning the feature introduced. Key warnings,
  hydration mismatches and `act()` warnings are findings.
- **Network**: status codes, and no duplicate or runaway request. A `useQuery` firing on
  every render shows up here and nowhere else.
- **Server logs**: on Symfony, a 500 swallowed by a catch is invisible in the browser.

## 5. Report

```
Verify : <feature> @ <target>
------------------------------------
Golden path :  OK | broken at <step>
Edge case :    <which one> : OK | broken
Console :      clean | N errors, M new warnings
Network :      clean | <what is wrong>
Proof :        <screenshot, or the trace that matters>
```

Screenshot for a visual change, network trace for a data or API change.

## Rules

- **Never hand the check back to the user.** "You can test it at localhost:3000" is the
  failure mode this skill exists to remove.
- **A finding here outranks a green suite.** Broken golden path: stop and fix.
- **Fix in the source, never in the page.** The console reads state, it does not patch it.
- **Name what you could not verify.** A real payment, a signed document, a third-party
  callback: hand that part to the UAT explicitly rather than implying full coverage.
