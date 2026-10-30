---
name: gap-sota
description: Measures how far the dev-standards guidelines sit from the state of the art, judging them against the ecosystem's own sources. Triggers - veille, update guidelines, "are the guidelines still current?", state of the art.
---

# gap-sota

**The ecosystem is right, the guidelines get corrected.** The mirror of `/gap-code`,
where the guidelines are right and the code gets corrected.

| Stack | Files |
|---|---|
| All three | `react/react-guidelines.md` |
| Symfony + React | `symfony-react/symfony-guidelines.md`, `symfony-react/reactony.md`, and the watch file `symfony-react/watch.md` |
| Next | `next/next-guidelines.md` |
| TanStack Start | `tanstack-start/tanstack-start-guidelines.md` |

All three by default; the user can target one. Run from a project, the same files are
reachable through its `docs/` symlinks.

## 1. Read the current state

- Start from the "Last watch" header: it dates the previous run and lists the versions
  verified then.
- On the Symfony stack, read `symfony-react/watch.md` too: the radar of the next versions,
  the advisories behind each security floor, the decisions and their last re-validation,
  the issues being followed. Its items are the first things to check: a radar release that
  shipped, an issue that closed.
- Note every version claim and every prescription. Those are what you are about to test.

## 2. Research, web first

**Source hierarchy**: the repository (changelog, release notes, advisories, the code),
then the official docs, then the published package (`npm view`, `composer show`, the
registry API). A blog never establishes a fact, it only points at one.

Where to look:

- Official changelogs for every recent major and minor of every library named
- Official blogs: `symfony.com/blog`, `react.dev/blog`, `vercel.com/blog`,
  `doctrine-project.org/blog`, `php.net/releases`, `tanstack.com/blog`
- **Hosted runtimes are referenced by what the host publishes, not by upstream.** PHP and
  PostgreSQL run on CleverCloud: the reference version is the newest one its images
  offer, read from `clever.cloud/developers/changelog/` and the runtime docs. php.net
  shipping a version CleverCloud has not published yet does not move the reference; it
  becomes a watch item until Clever ships it.
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

- **Take a project repository as a source.** Its commits carry legacy, delivery
  compromises and patterns older than the current recommendation. It is what we want to
  fix, not what we learn from.
- Trust one tutorial: cross-check two authoritative sources.
- Cite anything older than 18 months without confirming it holds.
- Invent: a pattern with no official source is marked "to validate with the user".

## 3. Analyse

**The latest stable release is the target.** A version that is stable (no alpha, beta,
RC, canary or experimental flag) becomes the prescription at the watch that finds it,
major versions included: do not hold one back out of caution. Only two things hold it
back: an experimental status, or a documented regression (an open issue confirmed by the
maintainers, a fix pending in the next patch). A floor for those who have not migrated
yet can sit next to it.

- Version references: still accurate?
- Factual accuracy: check the signature, not your memory of it.
- Completeness: a breaking change, a security item, a new stable feature that changes the
  recommendation.
- Clarity: ambiguity, duplication, sections that no longer read well.
- **Consistency across the three stacks**: where two files cover the same ground (the UI
  base, the React Compiler, the linter, the test stack) they must say the same thing.
  Three files quietly disagreeing is worse than one being out of date, because the reader
  cannot tell which to trust.
- **A tool the guidelines do not name** comes in only to close a gap they have (a defect
  that reached production, a check done by hand every time), never for being new. Either
  verdict goes into the stack's deliberate decisions, so the next watch does not propose
  it again.

## 4. Present

- Factual corrections (must fix)
- Important additions (should add)
- Clarity improvements (nice to have)
- What is already right and stays
- **Nothing to change is a valid outcome.** Say it plainly, with what was verified.

## 5. Apply, then re-date

After approval: edit, then update the "Last watch" header, date and versions. A watch
that does not move its own date sends the next run to the wrong starting point.

**Dated material goes to the watch file, not the guideline.** On the Symfony stack,
`symfony-react/watch.md` takes what carries a date: the radar of the next version, the
advisories (id, date, score) behind a floor, the date a decision was re-validated, an
upstream issue being followed. The guideline keeps the floor, the rule and a one-clause
reason, so it does not go stale between two watches. A radar item whose release shipped
leaves the radar: it becomes a rule in the guideline when it changes what someone writes,
configures or pins, and nothing otherwise. Then move the watch file's own date.

Then the dev-playbook project, the cheat sheet derived from these guidelines: every screen
whose `source` cites a section you changed is re-read against the new text. Nothing else
keeps it in step, and a stale screen teaches the old rule to whoever reads it to stay sharp.

## Rules

- **Prescriptive, not descriptive.** Never weaken a guideline because the code does not
  follow it yet, never strengthen one because recent commits happen to.
- No per-project state. "The project is on ^12.5" is true in one repo and a lie in its
  neighbours. Project-to-guideline gaps are `/gap-code`'s job. A lesson drawn from
  history stays legitimate: that is justification, not state.
- No project names, no company names, no ticket references, code examples included.
- English throughout. Keep the tone: prescriptive, dense, short code examples.
- Do not remove a pattern that is still correct: update, add or clarify.
- **The guidelines are not a changelog.** A release note earns a line only if it changes
  what someone writes, configures or pins: a floor, a trap, a migration step that breaks
  our patterns, a watch item that fired. A new feature nobody here uses, a deprecation of
  an API we do not call, a list of every merged change: the version number in the header
  is enough, and the changelog stays where it is.
