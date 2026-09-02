# React Guidelines: what is true on every stack

> **Last watch: 30 August 2026** (`/gap-sota`), start from this date on the next run. Reference versions verified: React 19.2.8 · React Compiler 1.0 · shadcn CLI 4.19 (Base UI base, Nova style) · lucide-react 1.x · eslint-plugin-react-hooks 7.1 · Vitest 4.1 · MSW 2.15 · Playwright 1.62.

React runs on all three stacks, so what follows is written once here. Each stack's own
file carries what genuinely differs: how the compiler is enabled, which form library, how
the tests reach the backend.

Referenced by `symfony-react/reactony.md`, `next/next-guidelines.md` and
`tanstack-start/tanstack-start-guidelines.md`.

## 1. React 19

React 18 is in security support only. The features that change how code is written:

- **`ref` as a prop**: no `forwardRef` any more, and refs take cleanup functions.
- **`use()`**: read a promise or a context during render.
- **`useOptimistic`**: show a mutation landing before the server answers. For reversible,
  non-critical actions; not for a creation that can visibly fail.
- **`<Activity mode="visible|hidden">`**, stable since 19.2: keep a hidden panel's state,
  DOM and scroll position instead of unmounting it. Every tabbed interface wants this.
- **`useEffectEvent`**, stable since 19.2: pull out of an Effect the logic that reads
  props or state without listing them as dependencies. Never in the deps array, and
  declared in the component that owns the Effect.

```tsx
// ref straight as a prop
const Input = ({ ref, ...props }: { ref?: React.Ref<HTMLInputElement> }) => (
  <input ref={ref} {...props} />
);
```

**View Transitions** (`<ViewTransition>`) stay experimental, not in production.
Stabilization is announced for 19.3 alongside Fragment refs, but only through a secondary
source: wait for the official announcement.

## 2. React Compiler

**Enabled, on every stack.** How to turn it on differs and lives in each stack's file.
What follows is the part that does not.

- **Do not write `useMemo`, `useCallback` or `React.memo` by hand.** The compiler places
  them where they are needed.
- Keep one only when profiling shows a specific expensive re-render, or when the lint
  reports a bail on that component.
- Existing ones from before the compiler are no-ops, not bugs. Clean them up when you
  touch the file, not as a campaign.
- **Lint rules ship with `eslint-plugin-react-hooks` ≥ 7** (`configs.flat.recommended`).
  The standalone `eslint-plugin-react-compiler` package is frozen: do not install it.

**A bailed component** breaks the Rules of React somewhere: a side effect in render, a
write to `window.*`, a ref mutated from an outside callback, an `exhaustive-deps`
disable. It still works, it just gets no auto-memoization. Not blocking, fix when
profiling asks.

Write the simplest React you can, and let the compiler optimize.

### Never pass a library instance down as a prop

TanStack Table's `useReactTable` returns a **stable** object whose internals it mutates in
place. The compiler knows the hook is incompatible and skips the component that calls it
(`react-hooks/incompatible-library`, plugin ≥ 7), so a table rendered inline is safe. Pass
that same `table` instance to a child and the safety ends: the child never calls the hook,
so the compiler memoizes it on a prop reference that never changes, and it **stops
re-rendering when the filtered data changes**.

The tell is a counter bound to `filtered.length` updating while the rows stay put.

The fix is not `"use no memo"`, it is passing **derived data**: `getHeaderGroups()` and
`getRowModel().rows` produce a fresh reference whenever the data changes. That is also
what React and TanStack recommend. The same reasoning applies to any library object that
stays identical while mutating.

**No unit test can catch this**, because Vitest runs its own config without the compiler
plugin. Only an end-to-end test on a real build sees it, so a table with filtering owes a
spec: filter down to the empty state, clear, come back.

## 3. shadcn and Base UI

**Base UI, never React Aria or Radix**, whatever the project, in Nova style. Composition
goes through the `render` prop and standard DOM handlers. `asChild` exists in neither
base, it is a Radix idiom. A project still on `new-york-v4` is a gap for `/gap-code` to
raise: migrate it in full, never two bases in one project.

shadcn is **not an npm dependency**: the components are vendored source in
`components/ui/`. There is no `pnpm update`, only the CLI, component by component,
preserving local customizations.

### Picking the right element

- **Action** (submit, confirm, delete) → `<Button variant=…>`.
- **Link** → `<Button render={<a href=… />} variant=…>`, so a real `<a>` survives.
- **Selectable or toggle** (filter pills, multi-select) → `Toggle` / `ToggleGroup`.
- **Bespoke** (image tile, clickable card, absolutely positioned icon, dropzone) → a raw
  `<button>` is legitimate; `<Button>` would only add a variant to override.
- Escape hatch `buttonVariants({variant})` for an element you cannot compose through
  `<Button>`, a third-party `Link` for instance.

Use the compound components (`Dialog` + `DialogContent` + `DialogHeader`). Loading spinner
from lucide-react, notifications through sonner's `toast`, never `alert()`.

**A `Select` whose values differ from their labels needs `items` on the root.** Radix
resolved the trigger label from the selected `SelectItem`'s children; Base UI does not,
and `SelectValue` falls back to `String(value)`, so the trigger shows raw keys
(`__all__`, `price:asc`). Pass `items={{value: label}}` to `Select` (a partial map works,
unlisted values fall back to the value itself), or give `SelectValue` a render function.
A post-Radix-migration sweep of every `<SelectValue`: one report showed nine components
leaking sentinels into the UI.

**Declare the `data-horizontal` / `data-vertical` variants in your CSS.** The Nova
components style themselves by orientation through those Tailwind variants, and shadcn
ships no CSS to define them. Base UI exposes orientation as
`data-orientation="horizontal|vertical"`, so a bare `data-horizontal:` variant matches
nothing and every orientation rule silently drops. Seen on a `Tabs`: the root kept
`flex-row`, the tab list took a column down the left and the panel collapsed to zero
pixels wide, on every page using tabs. Add both lines once:

```css
@custom-variant data-horizontal (&[data-orientation='horizontal']);
@custom-variant data-vertical (&[data-orientation='vertical']);
```

`grep -rl 'data-horizontal\|data-vertical' components/ui/` names the components that
depend on them; it found eight, from `tabs` to `slider` and `separator`.

### Scoping a theme to one screen

Overriding `--primary` on a wrapper element does nothing: the components stay the
original colour. Tailwind's `@theme` declares the indirection on the root,
`--color-primary: hsl(var(--primary))`, and a custom property's `var()` references are
substituted on the element that carries the declaration. `--color-primary` is therefore
resolved once on `:root`, and descendants inherit an already-computed colour. Redefining
`--primary` further down never reaches it.

Override the `--color-*` tokens the utilities actually consume, with final values, on the
wrapper class: `--color-primary`, `--color-accent`, `--color-border`, `--color-input`,
`--color-ring`, `--color-destructive`, and their `-foreground` counterparts. Check with
`getComputedStyle(el).getPropertyValue('--color-primary')` rather than by eye. Portals
(`PopoverContent`, `DialogContent`) render outside the wrapper, so they need the theme
class passed through `className`.

### Updating a component

The CLI is the update tool. Never fetch the GitHub files by hand.

1. `npx shadcn@latest add <component> --diff`: the gap against upstream for the configured
   style. Add a filename to diff one file at a time.
2. No local change means a safe overwrite. A local change means reading the file and
   re-grafting our additions onto the upstream update.
3. **Never `--overwrite` blindly**: an `add` can pull a registry dependency and crush a
   customized component.
4. After every `add`, fix the icon imports and the `@/` aliases, then check the rendering
   in a browser: moving between styles changes shadows, focus rings and sizes.

**What is customized is recorded per project**, in its `DESIGN-SYSTEM.md`. That file is
authoritative; a shared document cannot hold a local inventory without lying to its
neighbours. Read it before updating, and update it in the same PR.

**A grouped bump can half-migrate a component.** v4 moved the vertical padding of `Card`
from its sub-parts to the root (`py-6` / `gap-6` on `Card`, `px-6` alone on `CardHeader`
and `CardFooter`). Pull the sub-parts without the root and every card has its title glued
to the top edge. Check the components that have sub-parts after any UI bump: Card, Dialog,
Sheet. Related: `p-0` on `Card` does **not** remove the `px-6` its sub-parts carry, so a
chart embedded in an already-padded wrapper needs `px-0` on the sub-parts.

**A card header is responsive to the card, not the viewport.** In a `md:grid-cols-2` grid
a card is half-width, so a `sm:` breakpoint flips it at the wrong moment. v4's `CardHeader`
already declares `@container/card-header`: use `@lg/card-header:flex-row` so the
title-plus-action row decides on the card's own width.

**Grepping generated Tailwind CSS**: the build escapes the class names, `md:p-6` is written
`.md\:p-6`, `/` becomes `\/` and `[` becomes `\[`. Grepping the unescaped form returns
nothing and reads as a missing class. Use `grep -F '.md\:p-6'`.

**Known upstream traps**: `PopoverClose` is gone from the v4 registry; the dialog prop
became `showCloseButton`; upstream `xs` moved to `h-6` and the `icon-*` sizes are not in
every style; components that are not shadcn's (`multi-select`, `visually-hidden`) have no
upstream diff at all.

**Cadence**: at every `/gap-sota`, check the current style on ui.shadcn.com and reconcile
whatever drifted most. Target a near-empty diff outside the documented brand variants.

## 4. Security, on the front

Three things are true on the three stacks, and each stack's file carries the rest.

**Everything in the bundle is public.** A prefixed environment variable (`NEXT_PUBLIC_`, `VITE_`) is
readable by anyone who opens the sources, and nothing warns you. Only what would be fine on a
billboard takes the prefix. A secret read in a file that also runs on the client is a secret
published, so follow the `"use client"` boundary transitively before concluding a file is
server-only.

**`dangerouslySetInnerHTML` is for markup the project produced**, never for anything a user or a CMS
can influence, and sanitised even then. The `href` of a link built from data deserves the same
suspicion: a `javascript:` URL is an execution.

**What the client sends, the client can forge.** A check done in the component is an ergonomic
affordance, not a guard. The rule that matters lives on the other side, and the front's job is to
render the server's verdict rather than re-derive it, which is also what stops the two from drifting.

## 5. Front-end tests

Vitest with `@testing-library/react` and `user-event`, MSW for the network, Playwright for
journeys. Same stack everywhere; what differs is how the tests reach the backend, and that
lives in each stack's file.

**By return on investment:**

1. **Pure functions**: business calculations, formatters, transforms. No mocks, no DOM.
   Bugs here shift the numbers the user reads.
2. **Forms with validation**: every invalid field, the happy path, and the server errors.
3. **Critical journeys**: one Playwright spec per journey, not per page.
4. **A fragile component before a refactor**: pin the current visible behaviour first.
5. **Everything else: skip.** A component passing three props to three children needs no
   test; TypeScript and the linter cover it.

**MSW over `vi.mock`**, always. Module mocking works, but MSW intercepts at the network
level and stays true when the same flow moves to E2E. A test that only mocks a module is
usually a pure-function test in disguise.

**Portal-based primitives are fragile in jsdom.** Select, Dialog and Popover misbehave
without a real layout engine. Either mock the primitive down to its contract, or run that
spec in Vitest Browser Mode, stable since Vitest 4. jsdom stays the default for light unit
tests.

**Not worth testing**: a component that only calls an API and displays the result, a
full-render snapshot that breaks on any class change, a passthrough of the UI kit.
