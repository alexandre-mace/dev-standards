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

**Known upstream traps**: `PopoverClose` is gone from the v4 registry; the dialog prop
became `showCloseButton`; upstream `xs` moved to `h-6` and the `icon-*` sizes are not in
every style; components that are not shadcn's (`multi-select`, `visually-hidden`) have no
upstream diff at all.

**Cadence**: at every `/gap-sota`, check the current style on ui.shadcn.com and reconcile
whatever drifted most. Target a near-empty diff outside the documented brand variants.

## 4. Front-end tests

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
