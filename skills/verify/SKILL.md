---
name: verify
description: Exercise the feature in a real browser before it goes to review - golden path, one edge case, console and network clean, proof captured. Triggers - verify, teste la feature, "ça marche vraiment ?", "montre-moi", before /review-diff.
---

# Verify in the browser

The step that closes the gap between "the checks are green" and "the feature works".
Static analysis proves the code compiles. Tests prove what they cover. Only running
the thing proves the user gets what the ticket asked for.

Runs after the implementation, **before** `/quality` and `/review-diff`. A feature
that has never been rendered is not finished, whatever the test suite says.

**Applies to**: all three stacks, anything with a UI. Skip it for a change with no
observable surface (a console command, a migration, a CI tweak) and say so.

## 1. Get the app running

Use the Browser pane, never a background `pnpm dev` in a shell: the pane is what
lets you read the DOM, the console and the network.

Start it from `.claude/launch.json`. If the file does not exist, write it first:

```json
{
  "version": "0.0.1",
  "configurations": [
    { "name": "app", "runtimeExecutable": "pnpm", "runtimeArgs": ["dev"], "port": 3000 }
  ]
}
```

Per stack: a Next site and a TanStack Start app are one `pnpm dev`. A Symfony app
needs **two** processes, the PHP server and Vite: declare the PHP one as the
configuration to preview, and start Vite alongside it, otherwise the React islands
render nothing and you will chase a phantom bug.

If the server will not start, that is the finding. Report it and stop: do not
work around it.

## 2. Walk the golden path

The path the ticket describes, end to end, as a user does it. Not a smoke test of
the page that was touched: the **journey** the feature belongs to.

- Navigate, click, fill and submit with the real controls, not by calling functions
  in the console.
- After each step that changes the screen, read the page to confirm what actually
  rendered. A screenshot proves pixels, `read_page` proves content and structure.
- On a form, submit it for real and confirm the effect: the toast, the redirect,
  the row that appears, the value that persists after a reload.

## 3. Walk one edge case

Pick the one that would actually happen, not the exotic one:

- the required field left empty, and the validation message that comes back
- the 422 from the server rendered per field, not swallowed into a mute toast
- the empty list, the missing image, the too-large file
- the same page at mobile width if the feature has any layout to it

The point is to see the failure handled, not to enumerate failures.

## 4. Read the instruments

A screen that looks right and logs errors is not right.

- **Console**: any error, and any warning the feature introduced. React key warnings,
  hydration mismatches and act() warnings are findings, not noise.
- **Network**: the calls the feature makes. Check the status codes, and check there
  is no duplicate or runaway request. A `useQuery` firing on every render shows up
  here and nowhere else.
- **Server logs**: on Symfony, a 500 swallowed by a catch is invisible in the browser
  and obvious in the log.

## 5. Report with proof

```
Verify : <feature>
------------------------------------
Golden path :     OK | broken at <step>
Edge case :       <which one> : OK | broken
Console :         clean | N errors, M new warnings
Network :         clean | <what is wrong>
Proof :           <screenshot / the page content that matters>
```

Attach the screenshot when the change is visual. For a data or API change, the
network trace is the better proof.

## Rules

- **Never ask the user to check for you.** You have the browser; use it. Handing
  back "you can now test it at localhost:3000" is the failure mode this skill exists
  to remove.
- **A finding here outranks a green suite.** If the golden path breaks, stop and fix;
  do not carry on to `/quality` with a broken feature and clean linters.
- **Fix in the source, never in the page.** The JavaScript console is for reading
  state, not for patching it.
- **What you could not verify, say plainly.** A flow needing a real payment, a signed
  document or a third-party callback is not verifiable locally: name it and hand that
  specific part to the UAT, rather than implying the whole feature was exercised.
