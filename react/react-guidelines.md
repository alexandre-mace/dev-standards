# React Guidelines: what is true on every stack

> **Last watch: 29 September 2026** (`/gap-sota`), start from this date on the next run. Reference versions verified: React 19.3.0 · React Compiler 1.0 · shadcn CLI 4.21 (Base UI 1.8 base, Vega style, `cn` package) · lucide-react 1.x · eslint-plugin-react-hooks 7.1 · Vitest 5.0 · MSW 3.0 · Playwright 1.63.

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

**Stable since 19.3**, usable in production:

- **`<ViewTransition>`** with `addTransitionType`: animate between two UI states with the
  browser's View Transitions API, no animation library.
- **Fragment refs**: a ref on `<Fragment>` reaches its children without a wrapper `div`.

19.3 also renders independent transitions separately instead of batching them into one
render. Code that relied on two `startTransition` calls committing together can now see an
intermediate state.

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

A library object that stays identical while it mutates in place defeats the compiler: a
child that receives it is memoized on a reference that never changes, and **stops
re-rendering when the data does**. The tell is a counter bound to `filtered.length`
updating while the rows stay put.

TanStack Table v9 (`useTable`) is built for the compiler: with the default selector it
returns a fresh table reference on every state change, so a table rendered inline, or a
child handed `table`, re-renders as it should. The hazard is narrower. A nested component
that receives only a stable `row`, `cell`, `column` or `header` and reads state through
one of its methods (`row.getIsSelected()`) hides that read from the compiler. Keep a
`Subscribe` inside that component, or pass it the selected value as a changing prop
(TanStack's own guide, `docs/framework/react/guide/react-compiler.md`, shipped with the
package as the `table-state` skill). Do not wrap every cell in `Subscribe` preemptively.

v8 (`useReactTable`) predates this: the compiler skips the component that calls the hook
(`react-hooks/incompatible-library`), and the fix for a child is to pass derived data,
`getHeaderGroups()` and `getRowModel().rows`, never the instance. Migrate rather than work
around it.

**No unit test can catch this**, because Vitest runs its own config without the compiler
plugin. Only an end-to-end test on a real build sees it, so a table with filtering owes a
spec: filter down to the empty state, clear, come back.

## 3. shadcn and Base UI

**Base UI, never React Aria or Radix**, whatever the project, in **Vega** style (`"style": "base-vega"` in `components.json`).
Vega is the classic shadcn geometry: a 36 px default button, `rounded-md`. Nova, the other Base UI
style, is visibly tighter, a 32 px button on `rounded-lg`, and a project that lands on it without
choosing it ends up with controls smaller than the rest of its interface. Composition
goes through the `render` prop and standard DOM handlers. `asChild` exists in neither
base, it is a Radix idiom. A project still on `new-york-v4` is a gap for `/gap-code` to
raise: migrate it in full, never two bases in one project.

shadcn is **not an npm dependency**: the components are vendored source in
`components/ui/`. There is no `pnpm update`, only the CLI, component by component,
preserving local customizations.

**`cn` comes from the `cn` npm package** since CLI 4.21: `lib/utils.ts` reduces to
`export { cn } from "cn"`, and registry components import it from there. A project still on
`clsx` + `tailwind-merge` migrates with `npx shadcn@latest migrate cn`, then drops both
dependencies.

### Picking the right element

- **Action** (submit, confirm, delete) → `<Button variant=…>`.
- **Link** → `<ButtonLink href=… variant=…>`, a plain `<a>` carrying `buttonVariants`,
  defined next to `Button` in `components/ui/button.tsx`. Never `<Button render={<a />}>`:
  Base UI reserves `Button` for buttons. It warns about the missing `<button>`, and
  `nativeButton={false}`, which silences the warning, puts `role="button"` on the link, so a
  screen reader announces a button (cf. base-ui.com/react/components/button). A link has
  no `disabled`: pass `aria-disabled`, and `ButtonLink` drops the `href` itself.
- **Selectable or toggle** (filter pills, multi-select) → `Toggle` / `ToggleGroup`.
- **Bespoke** (image tile, clickable card, absolutely positioned icon, dropzone) → a raw
  `<button>` is legitimate; `<Button>` would only add a variant to override.
- Escape hatch `buttonVariants({variant})` for an element you cannot compose through
  `<Button>`, a third-party `Link` for instance.

Use the compound components (`Dialog` + `DialogContent` + `DialogHeader`). A loading
indicator is shadcn's `Spinner` (lucide's `Loader2` with `role="status"`), notifications go
through sonner's `toast`, never `alert()`.

**A `Select` whose values differ from their labels needs `items` on the root.** Radix
resolved the trigger label from the selected `SelectItem`'s children; Base UI does not,
and `SelectValue` falls back to `String(value)`, so the trigger shows raw keys
(`__all__`, `price:asc`). Pass `items={{value: label}}` to `Select` (a partial map works,
unlisted values fall back to the value itself), or give `SelectValue` a render function.
A post-Radix-migration sweep of every `<SelectValue`: one report showed nine components
leaking sentinels into the UI.

**Bring in shadcn's `tailwind.css`.** The registry components assume the project imports
`shadcn/tailwind.css`, shipped in the `shadcn` npm package (`dist/tailwind.css`, 4.21.1): it
declares the `data-open`, `data-closed`, `data-checked`, `data-unchecked`, `data-selected`,
`data-disabled`, `data-active`, `data-horizontal` and `data-vertical` variants, plus the
accordion keyframes. The CLI does not add the import to an existing stylesheet. Without it,
Tailwind's native `data-*` variants apply, and they read differently:
- Base UI exposes orientation as `data-orientation="horizontal|vertical"`, so a bare
  `data-horizontal:` matches nothing and every orientation rule silently drops. Seen on a
  `Tabs`: the root kept `flex-row`, the tab list took a column down the left and the panel
  collapsed to zero pixels wide, on every page using tabs.
- cmdk writes `data-selected="false"` on the rows it does not highlight, and the native
  `data-selected:` matches `[data-selected]` whatever its value: every row of a `Command`
  list looks highlighted.

Either `@import "shadcn/tailwind.css";` with `shadcn` as a dev dependency, or vendor the file
next to `app.css` as the components are vendored, which avoids pulling the whole CLI into
`node_modules` and into `pnpm audit`.

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

**A global is replaced with `vi.stubGlobal` or `vi.spyOn`**, never with
`Object.defineProperty` or an assignment. The configuration undoes the first two after
every test (`unstubGlobals` and `restoreMocks`, or `vi.unstubAllGlobals()` and
`vi.restoreAllMocks()` in an `afterEach` of the setup file), even when an assertion
stopped the test halfway; nothing undoes the others. A `navigator.clipboard` defined by
hand stays for every later file sharing the same jsdom. A getter-only property takes
`vi.spyOn(navigator, 'clipboard', 'get')`.

**Time goes through the project's one clock helper, never through a real wait.** The
helper turns on the fake timers and hands back a `userEvent` that advances them
(`vi.useFakeTimers({ shouldAdvanceTime: true })` and
`userEvent.setup({ advanceTimers: vi.advanceTimersByTime })`); the setup file puts the
real timers back after each test. Recopied inline, the pair drifts from one file to the
next. A `setTimeout(50)` awaited in a test bets on how long the code takes, and the bet
gets lost on a slower runner.

**Not worth testing**: a component that only calls an API and displays the result, a
full-render snapshot that breaks on any class change, a passthrough of the UI kit.

## 6. Accessibility

Forms have their own rules in each stack's file: focus on the error, error linked to the
control, required state. What follows holds for any component.

- **A confirmation that replaces the button just clicked takes the focus, or is announced
  from a `role="status"` region.** Unmounting the focused button drops the focus on
  `<body>`, and a screen reader says nothing: an alert created, a suggestion sent, a
  thank-you screen, all silent. Move the focus to the confirmation's heading
  (`tabIndex={-1}`), or put the message in a `role="status"` region.
- **An image inside a link whose text already names it takes `alt=""`.** A card whose
  title is the link text and whose photo has that title as `alt` is read twice (WCAG
  1.1.1).
- **A decorative emoji is `aria-hidden`**: `<span aria-hidden="true">📍</span> Région`.
  Left in the label, its name is read before the word.
- **A touch target is at least 24 × 24 CSS pixels** (WCAG 2.2, criterion 2.5.8, level
  AA). A chip's « Retirer » button at `h-4 w-4` is 16 pixels. axe's `target-size` rule
  measures it but is off by default (axe-core 4.13): turn it on in the E2E check,
  `new AxeBuilder({page}).options({rules: {'target-size': {enabled: true}}})`.
- **No `outline-hidden` without a visible focus indicator in its place.** Tailwind's
  `outline-hidden` removes the outline outside forced-colours mode. A field that takes it
  needs its own `focus-visible:` ring, or its wrapper a `focus-within:` one, or a keyboard
  user cannot tell where they are (WCAG 2.4.7): a site's main search box had neither.
