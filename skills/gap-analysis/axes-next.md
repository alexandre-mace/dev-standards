# Scan axes: Next

Read this file only when auditing a Next project.

### Next (`app/`, `components/`, `lib/`)

- **Server by default**: `"use client"` on a component that needs no interactivity, or
  placed too high in the tree so a whole page ships to the browser.
- **Baked data**: a page fetching at request time what a `scripts/build-*.mjs` should
  have written into `lib/`; a dynamic route with no `generateStaticParams`.
- **UI base**: a project still on `new-york-v4` (Radix) or React Aria rather than Base
  UI in Nova style; `asChild`, which belongs to neither base.
- **The kit**: a `components/ui/` edited locally in a consumer instead of fixing the
  kit; a component copy-pasted between projects rather than installed from the
  registry.
- **Config**: `reactCompiler` off; `images.remotePatterns` with `hostname: "**"`;
  `--turbopack` still passed, redundant since Next 16; a hand-edited auto-managed
  block in `AGENTS.md`.
- **Icons**: a brand icon imported from `lucide-react` 1.x, which no longer ships any.
- **SEO**: a page with no exported `metadata`.
