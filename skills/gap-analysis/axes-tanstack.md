# Scan axes: TanStack Start

Read this file only when auditing a TanStack Start project.

### TanStack Start (`src/routes/`, `src/components/`, `convex/`)

- **The router is the framework**: `URLSearchParams` parsed by hand; a `useState` for
  state a user would want to share as a link, instead of extending the route's
  `validateSearch` schema.
- **Loaders**: fetching inside a component instead of a loader feeding the Query
  cache; a server-rendered read not using `useSuspenseQuery`.
- **Server state**: Zustand or any client store used as a cache for server data.
- **Server functions**: an API route written for something with no external consumer;
  a `createServerFn` with no `.validator()`.
- **Convex**: a database write going through anything other than a Convex mutation; a
  hand-rolled `take(n)` migration loop instead of `@convex-dev/migrations`.
- **Auth**: routes guarded leaf by leaf instead of in a layout route; two auth
  providers coexisting.
- **UI base**: same axis as Next.
- **React Compiler**: hand-written memoization; `react({ babel: {...} })`, silently
  ignored since plugin-react v6.
