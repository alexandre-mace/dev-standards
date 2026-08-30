---
name: sota-gap
description: Measure how far the dev-standards guidelines sit from the state of the art, judging them against the ecosystem's own sources. Triggers - sota gap, veille, update guidelines, "are the guidelines still current?", state of the art.
---

# sota-gap

The distance between the guidelines and the ecosystem. The mirror of `/gap-analysis`,
which measures the distance between the code and the guidelines: here the authority is
reversed, the ecosystem is right and the **guidelines** are what get corrected.

It reads the guidelines in `~/dev/dev-standards`, confronts their recommendations with
**authoritative web sources**, and proposes edits wherever the advice has fallen behind
the state of the art. Run from inside a project, the same files are reachable through
its `docs/` symlinks. By default the watch covers all three stacks; the user can target
one.

| Stack | Files |
|---|---|
| Symfony + React | `symfony-react/symfony-guidelines.md`, `symfony-react/reactony.md` |
| Next | `next/next-guidelines.md` |
| TanStack Start | `tanstack-start/tanstack-start-guidelines.md` |

This is a **tech watch**: the guidelines are the subject, the web is the judge, and a
project repository is **not a source**, because it can be full of legacy.

## 1. Read the current state

- Read the target guidelines in full, starting with the "Last watch" header: it dates
  the previous run and lists the reference versions verified then. Start from that date.
- Note every version claim and every prescription. Those are what you are about to test.

## 2. Research, web first, authoritative sources only

The guidelines are **prescriptive**: they say what the maintainers and the ecosystem
recommend now, not what any project happens to do. The work is a web tech watch, not
an introspection of a repository.

**Source hierarchy**: the repository (changelog, release notes, security advisories,
the code itself), then the official documentation, then the published package
(`npm view`, `composer show`, the registry API). A blog never establishes a fact; it
only points at one. A watch built on second-hand content is worthless.

Where to look, in order:

- Official GitHub changelogs for every recent major and minor of every library named
  in the guidelines
- Official blogs: `symfony.com/blog`, `react.dev/blog`, `vercel.com/blog`,
  `doctrine-project.org/blog`, `php.net/releases`, `tanstack.com/blog`
- Release notes and migration guides, often `UPGRADE.md` or `CHANGELOG.md`
- RFCs and GitHub discussions labelled "RFC" or "roadmap"
- Recent conference announcements for emerging patterns that have actually landed

Cover every dependency the guidelines name, not a sample. And check three things a
version bump alone will not tell you:

- **Security floors**: an advisory that raises a minimum version is the highest-value
  finding this skill produces. Look for them explicitly.
- **A watch item whose trigger has fired**: the guidelines say "revisit when X is
  stable" in several places. Go and check whether X is now stable. This is the single
  most common way guidelines go quietly stale.
- **Deprecations already merged** on the next branch, worth anticipating.

What not to do:

- **Do not take inspiration from a project's recent commits.** They may be legacy,
  delivery compromises, or patterns older than the current recommendation. The repo is
  what we want to *correct*, not the source.
- Do not trust a single tutorial: cross-check against at least two authoritative
  sources before adopting a pattern.
- Do not cite anything older than 18 months without confirming it still holds.
- Do not invent: a pattern with no official source gets marked "to validate with the
  user".

## 3. Analyse critically

For each file:

- **Version references**: still accurate? A version that has moved on is a correction.
- **Factual accuracy**: are the APIs and patterns described correctly? Check the
  signature, not the memory of it.
- **Completeness**: is an important pattern missing? Breaking change, security, a new
  stable feature that changes the recommendation.
- **Clarity**: ambiguities, duplication, sections that no longer read well.
- **Consistency across the three stacks**: where two files cover the same ground (the
  UI base, the React Compiler, the linter, the test stack), they must say the same
  thing. Three files quietly disagreeing is worse than one being out of date, because
  the reader cannot tell which one to trust.

## 4. Present the findings

- Factual corrections (must fix)
- Important additions (should add)
- Clarity improvements (nice to have)
- What is already right and should stay
- **If nothing needs changing, say so plainly**, with a summary of what was verified,
  so the user knows the review actually happened.

## 5. Apply, then re-date

After approval, edit the files, and **update the "Last watch" header**: the date and
any version that moved. A watch that does not move its own date will be redone from
the wrong starting point next time.

## Rules

- The guidelines are prescriptive (how code SHOULD be written), not descriptive (how it
  IS written). Never weaken a guideline because the current code does not follow it
  yet, and never strengthen one because recent commits happen to: the web sources win.
- **Never any per-project state in the guidelines.** These documents are shared: "the
  project is on ^12.5" or "not migrated here yet" is true for one repo and a lie in its
  neighbours. Observing project-to-guideline gaps is `/gap-analysis`'s job. Lessons
  drawn from history stay legitimate: that is justification, not state.
- **No project names, no company names, no ticket references** in a shared document,
  code examples included.
- Keep the tone and structure: prescriptive, dense, short code examples.
- English throughout.
- Do not remove a pattern that is still correct: update, add or clarify.
- Concluding "no changes needed" after a thorough review is a perfectly good outcome.
  The goal is accuracy, not change for its own sake.
