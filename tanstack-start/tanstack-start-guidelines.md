# TanStack Start Guidelines: apps where the interface is the product

> Dashboards, internal tools, simulators, products.
>
> **Last watch: 29 August 2026** (`/sota-gap`), start from this date on the next run.
> Reference versions verified on npm that day: TanStack Start 1.168, TanStack Router 1.170,
> TanStack Query 5.102, TanStack Form 1.33, Convex 1.45, @clerk/tanstack-react-start 1.5,
> Zod 4.5, Zustand 5.0, Vite 8.2, Tailwind 4.3, Biome 2.5, Vitest 4.1, Playwright 1.62.

## Scope: when this stack instead of `next/`

The fork is decided **when the project starts**, by one question you can actually test:

> **Would rendering on the server ship less JavaScript to the browser?**

- **Yes**, because the pages are largely content. Server Components earn their
  complexity, and the framework built around them is `next/`.
- **No**, because it is all interactive anyway. Then the server-first model is a tax you
  pay for nothing, and this stack gives you typed URLs instead.

The honest version of the difference: **both frameworks have Server Components. What
differs is the default.** Next is server-first, every component runs on the server until
you write `use client`. TanStack Start is client-first: you opt into server rendering
explicitly, call by call, and the result flows back as data through a loader. So the
question is not whether you *can* render on the server, it is whether you want that to
be the default posture of the whole app.

What is genuinely *not* comparable today: Next's Server Components are production-grade,
TanStack Start's are **experimental** and its own docs say so. If RSC is central to what
you are building, that settles it: go to `next/`.

Nor is SEO a differentiator, whatever the blogs say: this stack does full-document SSR,
so a page here is indexed and previewed like any other.

A login is a common sign of the second case, never its definition: an internal tool on
an open URL belongs here, and so does a public simulator whose every screen is generated
by what the user typed.

Apply the test rather than the vibe. A tool whose page is a fifteen-word shell mounting
thirty-five client components ships exactly as much JavaScript either way, so Next's
default buys it nothing. What it would gain here is the thing it lacks: state in the
URL, typed, so a result can be shared as a link.

The other side of the test is just as real. Many pages of prose, a blog, a catalogue,
documentation: the server renders them once, the browser downloads almost no JavaScript,
and that is Next doing what it was built for.

**Mixed projects**: a marketing landing plus an app is one project with two audiences.
Serve the landing statically and keep the app here, rather than splitting the repo.

Do not migrate a working project just to follow this fork. It applies to new work.

**If the app needs no SSR, no server functions and no streaming at all**, the
TanStack docs themselves recommend dropping Start and using **TanStack Router alone**
as a SPA. Reach for Start when you actually want server functions or SSR, not by default.

## Principles

1. **The router is the framework.** Routes, params and search params are typed end
   to end. If you find yourself parsing `URLSearchParams` by hand, you left the rails.
2. **Server state is not client state.** TanStack Query owns anything that comes from
   the server. Zustand exists only for genuinely global client state (a theme, a
   sidebar, a wizard in progress), never as a cache.
3. **Server functions instead of endpoints.** A typed RPC beats a hand-written REST
   route and its client. Write an API route only for a real external consumer.
4. **The backend is Convex.** Schema, queries, mutations and actions live in TypeScript
   next to the app, which is what makes an agent able to work across the seam.
5. **Validate at the boundary, once.** Zod on anything entering the system, and the
   inferred type flows from there.
6. **Tests are not optional here.** This is an app, not a brochure: Vitest for logic,
   Playwright for the paths a user actually takes.

## 1. Scaffolding

```bash
pnpm create @tanstack/start@latest
```

Non negotiable from the first commit: TypeScript strict, pnpm with `packageManager`
pinned, Biome for lint and format, Vite as the build tool.

## 2. Routing

- **File-based routes** under `src/routes/`, with the generated route tree committed.
- **Typed search params** through `validateSearch`, with a Zod schema. This is the
  main thing this stack buys over Next: filters, pagination and tabs live in the URL,
  typed, shareable, and the back button works for free.
- **Loaders** fetch through the Query client so a route can render from cache and
  revalidate, rather than blocking on a waterfall.
- Guard authenticated areas in a **layout route**, not in each leaf.

## 3. Data: Convex and TanStack Query

The combination Start plus TanStack Query plus Convex is the documented sweet spot,
and Convex supports it officially.

- `@convex-dev/react-query` bridges the two: Convex subscriptions become Query entries.
- Use `useSuspenseQuery()` when a route server-renders, so fetching starts on the server.
  The browser client resumes the live subscription afterwards with no loading flash.
- Subscriptions stay alive 5 minutes after unmount (`gcTime`), which is usually what you
  want when a user navigates back and forth. Lower it deliberately, not by accident.
- Data migrations go through `@convex-dev/migrations`, never a hand-rolled `take(n)` loop.

## 4. Auth

**Clerk** through `@clerk/tanstack-react-start`, which has first-class support.
**Better Auth** is the alternative when self-hosting is required. Pick one at the
start of the project, never both, and put the session check in the layout route.

## 5. UI

Same kit as the rest of the personal ecosystem, so a component fixed once is fixed
everywhere: the `@alexandremace` registry, shadcn on the **Base UI** base, style Nova,
Tailwind 4 through PostCSS with no `tailwind.config`. Forms use TanStack Form with a
Zod schema, or React Hook Form if the project already leans that way, but only one of
the two per project.

## 6. Quality and deployment

- `pnpm build` and `pnpm test` are the gate. `tsc --noEmit` is part of it.
- Vitest for units and logic, Playwright for user paths. A new user-facing flow ships
  with a Playwright spec.
- Deployment on Vercel like the rest, knowing the build output is portable and can move
  elsewhere without rewriting the app.

## 7. Anti-patterns

- Reaching for this stack for a site whose content is the product. That is `next/`.
- Zustand, or a `useState`, holding data that came from the server.
- Hand-parsed query strings next to a router that types them.
- A REST route written for the app's own frontend, where a server function would do.
- Two form libraries, two validation libraries, or two auth providers in one project.
- Copying a Next.js idiom by reflex. Server Components exist here but are opt-in and
  experimental: you call `renderServerComponent()` deliberately, you do not sprinkle
  `use client` to escape a server-first default that does not exist.

## Watch

The framework ships stable on npm (`latest` is a plain `1.x`, past 800 releases, with
the beta line long closed), and it sits around 17M weekly downloads against 55M for
Next. But **parts of the official documentation still describe it as a Release Candidate**,
and Convex repeats that caveat. Treat breaking changes in a minor as possible, pin
exact versions, and read the changelog before bumping.
