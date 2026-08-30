# TanStack Start Guidelines: apps where the interface is the product

> Dashboards, internal tools, simulators, products.
>
> **Last watch: 29 August 2026** (`/sota-gap`), start from this date on the next run.

| Tool | Version | Notes |
|---|---|---|
| TanStack Start | 1.168 | Stable on npm, around 17M weekly downloads. Parts of the official docs still say Release Candidate, and Convex repeats that caveat: pin exact versions and read the changelog before bumping |
| TanStack Router | 1.170 | The framework's real core |
| TanStack Query | 5.102 | |
| TanStack Form | 1.33 | |
| Convex | 1.45 | Optional. The backend when the app needs one, bridged to Query by `@convex-dev/react-query` |
| Clerk | `@clerk/tanstack-react-start` 1.5 | Optional. First-class support when the app has accounts |
| Zod | 4.5 | |
| Vite | 8.2 | |
| Tailwind | 4.3 | PostCSS, no `tailwind.config` |
| Biome | 2.5 | Lint and format |
| Vitest / Playwright | 4.1 / 1.62 | |
| Hosting | Vercel | Build output is portable, it can move elsewhere unchanged |

## What this file covers

Apps whose value is what the user does. The fork with `next/` is decided when the project starts, on one testable question: would rendering on the server ship less JavaScript to the browser? If yes the pages are content and belong in `next/`; if no, the server-first model is a tax paid for nothing.

Both frameworks have Server Components. What differs is the default: Next is server-first until you write `use client`, Start is client-first and you opt in with `renderServerComponent()`. Start's are still experimental, so if RSC is central to the project, that settles it in favour of `next/`.

**If the app needs no SSR, no server functions and no streaming at all**, the TanStack docs recommend dropping Start for **TanStack Router alone**, as a SPA.

**Mixed project**: a marketing landing plus an app is one project with two audiences. Serve the landing statically and keep the app here, rather than splitting the repo.

## Playbook

- **A screen**: a file under `src/routes/`, its state in the URL through `validateSearch`, its data through a `loader` that goes via the Query client.
- **A filter, a tab, a pagination**: extend the route's Zod search schema. Never a `useState` for something a user would want to share as a link.
- **Reading server data**: a Convex query through `@convex-dev/react-query`, consumed with `useSuspenseQuery` so fetching starts during SSR.
- **Writing server data**: a Convex mutation. Nothing else touches the database.
- **A one-off server-side operation** (a third-party call, a secret): a server function, not an API route.
- **A form**: TanStack Form with the same Zod schema that validates the server side.

## Current patterns

**Typed search params.** The schema lives on the route, so both reads and links are checked at compile time: rename a field and every link that used it fails to build.

```tsx
export const Route = createFileRoute('/shop/products')({
  validateSearch: z.object({
    page: z.number().default(1),
    sort: z.enum(['newest', 'price']).default('newest'),
  }),
})

const { page, sort } = Route.useSearch()
<Link from={Route.fullPath} search={(prev) => ({ page: prev.page + 1 })}>Next</Link>
```

**Server functions** are typed RPC, validated at the boundary and callable from a loader or a component.

```tsx
export const getUser = createServerFn({ method: 'GET' })
  .validator(z.object({ id: z.string() }))
  .handler(async ({ data }) => db.user(data.id))
```

Call them directly in a `loader`, or through `useServerFn()` in a component.

**Loaders feed the Query cache** rather than fetching in a component. A route then renders from cache and revalidates, instead of blocking on a waterfall.

**`useSuspenseQuery` for anything server-rendered.** The browser client resumes the live Convex subscription afterwards with no loading flash. Subscriptions stay alive 5 minutes after unmount (`gcTime`): lower it deliberately, not by accident.

## 1. Scaffolding

```bash
pnpm create @tanstack/start@latest
```

TypeScript strict, pnpm with `packageManager` pinned, Biome for lint and format. Commit the generated route tree.

## 2. Rules

- **The router is the framework.** Parsing `URLSearchParams` by hand means you left the rails.
- **Server state is not client state.** TanStack Query owns anything that comes from the server. Zustand is for genuinely global client state (a theme, a sidebar, a wizard in progress), never as a cache.
- **Server functions instead of endpoints.** Write an API route only for a real external consumer.
- **When the app needs a backend, it is Convex.** Schema, queries, mutations and actions live in TypeScript next to the app, which is what lets an agent work across the seam. Data migrations go through `@convex-dev/migrations`, never a hand-rolled `take(n)` loop. An app that stores nothing needs none of this.
- **Validate at the boundary, once**, with Zod. The inferred type flows from there.
- **If there are accounts, guard them in a layout route**, never leaf by leaf. Clerk by default, Better Auth when self-hosting is required, never both.
- **One library per job.** One form library, one validation library, one auth provider.

## 3. UI

**Base UI in Nova style**, whatever the project, with Tailwind 4 through PostCSS.

A product with its own identity stops there: official shadcn CLI, no registry. That is the common case for an app.

A project that carries the shared identity adds the `@alexandremace` kit instead. A component fixed once is then fixed everywhere, so never modify `components/ui/` in a consumer: the change belongs in the kit, then propagates with `/propagate-kit`.

## 4. Quality and deployment

`pnpm build`, `pnpm test` and `tsc --noEmit` are the gate. Vitest for logic, Playwright for user journeys, and a new user-facing flow ships with its Playwright spec. This is an app, not a brochure: tests are not optional.

Pushing is deploying, on the same Vercel account as everything else. Group your pushes: the Hobby plan caps at 100 deployments per rolling 24 h across all projects, and retention is 30 days, so never rely on an old deployment URL as an archive.
