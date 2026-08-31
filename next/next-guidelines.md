# Next Guidelines: static-first personal sites

> **Last watch: 24 August 2026** (`/gap-sota`), start from this date on the next run.
>
> Radar: Instant Navigations (`cacheComponents` + `partialPrefetching`, opt-in, announced as a future default) and `transitionTypes` on `<Link>`. Worth watching, not yet standards.

| Tool | Version | Notes |
|---|---|---|
| Next.js | 16.3 | App Router, Active LTS. Security releases are pre-announced: patch within a week (e.g. 16.3.3, critical on 26/08/2026). `catchError`/`retry()` stable for error boundaries; `middleware.ts` deprecated in favour of `proxy.ts` |
| React | 19.2 | |
| `next/image` | 16 | Defaults: `qualities` down to `[75]`, cache TTL to 4 h, and a local src with a query string requires `images.localPatterns` |
| TypeScript | 5.9 | Native TS 7 is GA. Plan the 5.9 to 6.0 to 7.0 migration; `next build` type-checking supports TS 7 since 16.3 |
| Tailwind | 4 | PostCSS, no `tailwind.config` |
| shadcn | Base UI base, Nova style | The ecosystem default since July 2026 |
| React Compiler | 1.0 | Enabled with `reactCompiler: true`, out of `experimental` since Next 16 |
| Biome | 2.5 | Lint and format. The oxc line (oxlint + oxfmt) is the other way out of ESLint+Prettier, but oxfmt is in beta: revisit when it goes stable |
| Kit | `@alexandremace` | ui.alexandremace.fr |
| lucide-react | 1.x | No more brand icons |
| Geist | npm package | |
| Hosting | Vercel Hobby | |

## What this file covers

Personal Next sites. If server rendering saves no JavaScript because everything is interactive, the project is an application and belongs in `tanstack-start/`. Having a backend changes nothing here: what decides is the rendering model.

Two options combine freely, each described where it belongs: the site takes the `@alexandremace` kit or carries its own identity (§2), and it adds a backend or does not (§6).

**React itself is documented once**, in `react/react-guidelines.md`: React 19, the
compiler, shadcn and Base UI, the test doctrine. This file carries what is specific to
the stack.

## Playbook

What gets added most often, and where it goes.

- **A page**: a folder under `app/`, a `page.tsx` Server Component, its exported `metadata`. A dynamic route pre-fills its values with `generateStaticParams`, otherwise it is not static.
- **An external dataset**: a `scripts/build-*.mjs` writing typed TypeScript into `lib/`, a `data` script in `package.json`, and control logs at the end of the run. The page imports the constant, it does not fetch.
- **An interactive component** (simulator, filter, picker): a client component, as low in the tree as possible. Its page stays a Server Component and passes the baked data down as props.
- **A chart**: the chart component is client, the data arrives as props from the server. Colours come from the kit's `--chart-*` tokens, never hardcoded values.
- **User data**: see §6, the only case that justifies a backend.

## Current patterns

**Suspense for what is slow, not for what is missing.** A `loading.tsx` covers the whole segment; a local `<Suspense>` isolates the one slow part. On a static site most pages need neither.

**`<Activity>` rather than unmounting** when a hidden panel has to come back in the same state, which is every tabbed simulator. Stable since React 19.2: state, DOM and scroll position survive, Effects are cleaned up.

```tsx
<Activity mode={activeTab === "transport" ? "visible" : "hidden"}>
  <TransportPanel />
</Activity>
```

**`useEffectEvent`** to pull out of an Effect the logic that reads props or state without listing them as dependencies. Stable since 19.2, never called outside the Effect that owns it.

**React Compiler enabled**, so no hand-written `useMemo`, `useCallback` or `React.memo`. Stable since 1.0, and Next only applies the Babel plugin to the relevant files through an SWC pass, so the build cost stays marginal.

```ts
// next.config.ts, the key is no longer under experimental
const nextConfig: NextConfig = { reactCompiler: true };
```

It needs `babel-plugin-react-compiler` as a devDependency.

## 1. Scaffolding

```bash
pnpm create next-app@latest <project> --ts --app --tailwind --biome --no-src-dir --import-alias "@/*" --use-pnpm
```

Do not add `--turbopack`: it is the default bundler since Next 16.

**Pin `packageManager`** in `package.json`, and commit the lockfile. A `package-lock.json` showing up means someone installed with the wrong tool.

**Commit the auto-managed block in `AGENTS.md`** without editing it: `next dev` maintains it and it points at the docs for the installed version.

**List the real hosts in `images.remotePatterns`**, never `hostname: "**"`: a wildcard lets anyone route their images through the site's optimizer.

## 2. Components

**Base UI, never React Aria or Radix**, whatever the project, in Nova style. Composition goes through the `render` prop and standard DOM handlers. `asChild` exists in neither base, it is a Radix idiom. Migrate a project left on another base in full, never two bases in one project.

A **project with its own identity** stops there: official shadcn CLI, no registry.

A **site in the ecosystem** adds the `@alexandremace` kit, declared in `components.json`:

```json
"registries": { "@alexandremace": "https://ui.alexandremace.fr/r/{name}.json" }
```

Everything from the kit is installed through the registry, ecosystem components included. Never copy-paste between projects.

**The kit is the source.** Never modify `components/ui/` in a consumer: a local need is either a real gap to fix in the kit and propagate with `/propagate-kit`, or a use case to style through `className`. Project-specific components live at the root of `components/`.

**Icons**: `lucide-react` 1.x has no brand icons left, local SVG in `components/icons.tsx`.

**Variants**: CVA, as in the kit.

## 3. Styling

**Light single theme.** Add this line to `app/globals.css`, after the `@import`s:

```css
@custom-variant dark (&:is(.dark *));
```

It neutralises the stock shadcn `dark:` classes, and the `.dark` class is never set. A dark theme is a project decision, not a default.

**Tailwind 4 is configured in CSS**, no `tailwind.config.js`. Tokens live in OkLCh under `:root`, remapped to `--color-*` in `@theme inline`. Trap: `shadcn add theme` does not rewrite an existing `globals.css`, so add every new token by hand in both places.

The default palette comes from the kit. A project can take its own by redeclaring the tokens, without touching the components.

## 4. Layout and SEO
- **Server Components by default.** Keep `"use client"` for components that hold state or interaction.
- **The language is a project decision**, taken at the start and held everywhere: content, comments, and declared both in `<html lang>` and in `openGraph.locale`. Domain identifiers in that language are fine in scripts and models (`Pays`, `ANNEE`, `donnees`).
- **Geist through the `geist` package**, never through `next/font/google`: `import { GeistSans } from "geist/font/sans"`, variables on `<html>`, `font-sans` on the `<body>`.
- **Every site lives on its canonical domain** (`<project>.alexandremace.fr` or `<project>.climatelab.fr`), declared in `metadataBase`. Never a `*.vercel.app`: the canonical domain rules in metadata, OG and redirects. Factor the description into a const, it is used three times.
- **Generated OG image**: `app/opengraph-image.tsx` with `ImageResponse`, 1200×630, never a static image that goes stale.
- **Icons**: `app/icon.svg`, which Next serves with `sizes="any"` at every size, plus `app/apple-icon.png` at 180×180 for the iOS home screen. Next emits the `<link>` tags on its own. An `icon.tsx` rendering an `ImageResponse` when the icon derives from an emoji or an initial. No `favicon.ico`, unless a very old browser has to be supported.

## 5. Baked data

Content lives in `lib/` as typed TypeScript. Everything the page displays exists at build time: no route handlers, no server actions, no DB, unless the site has a backend (§6).

**Small dataset** (project cards, a model): written by hand in `lib/data.ts`, typed, sources documented in a header comment.

**External source** (World Bank, OWID): a `pnpm data` pipeline.

- `scripts/build-*.mjs`.
- Output generated into `lib/**/*.ts` with a mandatory header: `// Generated by scripts/…, do not edit by hand.`, the source, the extraction date, and the licence if it requires one (CC BY for OWID). A generated file edited by hand loses that edit on the next run.
- **Control logs at the end of the run**: totals cross-checked against a reference aggregate, row counts. It is the only safety net on a site with no test suite.
- Re-run `pnpm data` before a deployment that depends on freshness.

**Runtime fetch: only for deliberate exploration**, for instance a country picker on a chart. Small targeted calls, in-memory cache, clean degradation if the upstream goes down. Never `useEffect + fetch` for content that could have been baked at build time.

## 6. Backend

A site that needs accounts or stored data adds **Convex** and its official `@convex-dev/*` components (rate limiting, Resend emails, Stripe payments), with **Clerk** for auth.

The seam itself, schema, query, mutation and the bridge to TanStack Query, is written once in `tanstack-start-guidelines.md` §3 and does not change here.

Tests then become mandatory: Vitest and `convex-test` for the backend functions, Playwright for critical journeys. The control logs of the data scripts are no longer enough once a user can write.

## 6 bis. Security

A statically generated site with no backend has almost no attack surface. The moment §6 applies,
accounts and stored data, it has all of it, and the framework hides where the boundary sits.

**What crosses to the browser.** A `NEXT_PUBLIC_` variable is in the bundle, readable by anyone, and
nothing warns you: the prefix is the whole security model. Only what would be fine on a billboard
takes it, a publishable key, a public URL. Everything else is read server-side only. A secret read in
a component that turns out to be a client component is a secret published, so check the `"use client"`
boundary of any file that touches one, transitively.

**A server action is a public endpoint.** It compiles to a POST route anyone can call with any
payload, whatever the form around it looked like. It therefore starts by checking the session, then
the authorization for the specific object, then validates its input with the schema, in that order.
Being called from a form the user could only reach when logged in proves nothing.

**Never `dangerouslySetInnerHTML` on anything a user can influence**, directly or through the CMS.
The escape hatch is for markup produced by the project, and sanitised at that.

**Headers.** A Content Security Policy, `X-Content-Type-Options`, `Referrer-Policy` and
`Strict-Transport-Security` are declared once, in `next.config.ts` or `proxy.ts`. Their absence is
silent, so it is a `/gap-code` finding on its own.

**Rate limits at the edge of writes.** With Convex, the official `@convex-dev/rate-limiter`
component. Sign-in, any public POST, anything expensive to call in a loop.

**Personal data.** It does not travel into a URL, a log, or a Sentry breadcrumb. Sentry captures the
request by default: restrict it before the first real user, not after.

## 7. Quality and deployment

**`pnpm build` is the check**: types and static generation. Run it before pushing.

**Pushing is deploying** (Vercel webhook on main):

- Group your pushes. The Hobby plan caps at 100 deployments per rolling 24 h, and every push of every project takes a slot.
- Hobby retention is 30 days: never rely on an old deployment URL as an archive.

A host being retired becomes a redirect to the canonical domain, never a live duplicate.

### Definition of Done

A page or a feature is only done when every one of these is green:

- [ ] `/quality` passes, which here means `pnpm build` plus the linter
- [ ] `/live-test` run: the golden path exercised in a browser, console and network clean
- [ ] A new page exports its `metadata`; a new dynamic route has its `generateStaticParams`
- [ ] Anything reading baked data: `pnpm data` re-run, and its control logs actually read
- [ ] Nothing new under `components/ui/`: a gap in the kit is fixed in the kit, then propagated
- [ ] With a backend (§6): Vitest and `convex-test` on the new functions, a Playwright spec on a new journey
