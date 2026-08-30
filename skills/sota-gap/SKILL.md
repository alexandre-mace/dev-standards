---
name: sota-gap
description: Measure how far the dev-standards guidelines sit from the state of the art, judging them against the ecosystem's own sources. Triggers - sota gap, veille, update guidelines, "are the guidelines still current?", state of the art.
---

# sota-gap

The distance between the guidelines and the ecosystem.

**The ecosystem is right, the guidelines get corrected.** The mirror of `/gap-analysis`,
where the guidelines are right and the code gets corrected. A project repository is
**never a source**: it is full of legacy, and it is what we want to fix.

| Stack | Files |
|---|---|
| Symfony + React | `symfony-react/symfony-guidelines.md`, `symfony-react/reactony.md` |
| Next | `next/next-guidelines.md` |
| TanStack Start | `tanstack-start/tanstack-start-guidelines.md` |

All three by default; the user can target one. Run from a project, the same files are
reachable through its `docs/` symlinks.

## 1. Read the current state

- Start from the "Last watch" header: it dates the previous run and lists the versions
  verified then.
- Note every version claim and every prescription. Those are what you are about to test.

## 2. Research, web first

**Source hierarchy**: the repository (changelog, release notes, advisories, the code),
then the official docs, then the published package (`npm view`, `composer show`, the
registry API). A blog never establishes a fact, it only points at one.

Where to look:

- Official changelogs for every recent major and minor of every library named
- Official blogs: `symfony.com/blog`, `react.dev/blog`, `vercel.com/blog`,
  `doctrine-project.org/blog`, `php.net/releases`, `tanstack.com/blog`
- Release notes and migration guides, often `UPGRADE.md` or `CHANGELOG.md`
- RFCs and roadmap discussions

Three things a version bump alone will not tell you:

- **Security floors.** An advisory raising a minimum version is the highest-value
  finding this skill produces. Look for them explicitly.
- **A watch item whose trigger has fired.** The guidelines say "revisit when X is
  stable" in several places. Go and check whether X is stable now. This is the most
  common way guidelines go quietly stale.
- **Deprecations already merged** on the next branch, worth anticipating.

Never:

- **Take inspiration from a project's recent commits.** Legacy, delivery compromises,
  patterns older than the current recommendation.
- Trust one tutorial: cross-check two authoritative sources.
- Cite anything older than 18 months without confirming it holds.
- Invent: a pattern with no official source is marked "to validate with the user".

## 3. Analyse

- **Version references**: still accurate?
- **Factual accuracy**: check the signature, not your memory of it.
- **Completeness**: a breaking change, a security item, a new stable feature that
  changes the recommendation.
- **Clarity**: ambiguity, duplication, sections that no longer read well.
- **Consistency across the three stacks**: where two files cover the same ground (the UI
  base, the React Compiler, the linter, the test stack) they must say the same thing.
  Three files quietly disagreeing is worse than one being out of date: the reader cannot
  tell which to trust.

## 4. Present

- Factual corrections (must fix)
- Important additions (should add)
- Clarity improvements (nice to have)
- What is already right and stays
- **Nothing to change is a valid outcome.** Say it plainly, with what was verified, so
  the user knows the review happened.

## 5. Apply, then re-date

After approval: edit, then **update the "Last watch" header**, date and versions. A watch
that does not move its own date sends the next run to the wrong starting point.

## Rules

- **Prescriptive, not descriptive.** Never weaken a guideline because the code does not
  follow it yet, never strengthen one because recent commits happen to.
- **No per-project state.** "The project is on ^12.5" is true in one repo and a lie in
  its neighbours. Project-to-guideline gaps are `/gap-analysis`'s job. A lesson drawn
  from history stays legitimate: that is justification, not state.
- **No project names, no company names, no ticket references**, code examples included.
- English throughout. Keep the tone: prescriptive, dense, short code examples.
- Do not remove a pattern that is still correct: update, add or clarify.
