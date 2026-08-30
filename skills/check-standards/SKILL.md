---
name: check-standards
description: Audits the dev-standards repository against itself - broken cross-references, a README out of step with the skills, sibling skills that disagree. Use after renaming or adding a skill, and before trusting the repo again.
---

# Check the standards repo

**This repo is the only one nothing audits.** `/gap-analysis` measures a project against
the guidelines, `/sota-gap` measures the guidelines against the ecosystem, and neither
looks at whether this repository still agrees with itself.

## 1. Run the checker

`check-repo.py`, in this skill's own directory, takes the repo root:

```bash
python3 check-repo.py ~/dev/dev-standards
```

It settles what needs no judgement: a skill cited by name but absent, a README table out
of step with the skills on disk, an installation path hardcoded into a skill, a cross-
reference to a guideline section that does not exist, an em dash, CRLF line endings.

It exits non-zero on findings, so it also works as a pre-commit hook.

## 2. Read what a script cannot see

**A renamed skill leaves references that still resolve.** The checker compares names, not
meanings: a skill that kept its name and changed its job leaves every reference pointing
at the right file and the wrong thing. This is the failure that actually happens, and
only reading catches it.

- Every skill named inside another one: does it still do what the sentence assumes?
- Sibling skills must agree where they overlap. `deploy` and `preprod` are variants of
  one act, and a rule fixed in one has a twin in the other.
- The chain in `ticket`, the one in `diagnosing-bugs` and the one in the README: same
  order, same steps.
- An artifact one skill writes and another reads (`.claude/plan.md`): does the writer
  produce every section the reader expects?

## 3. Read the skills against `/redaction`

The rules that need judgement, and that the checker leaves alone:

- Bold used on more than a couple of items in a section signals nothing any more.
- A rule stated twice, in the body and again in a closing summary.
- A sentence addressed to whoever edits the skill rather than to whoever runs it.
- A justification of a design decision, which belongs in the commit message.

## 4. Report

Group by what it costs to be wrong: a broken reference sends an agent to the wrong file,
a disagreement between siblings makes it apply the wrong rule, a redaction defect only
slows the reading. Every finding names a file and a line.

Fix nothing without asking. This repository is the one place where a silent correction
propagates to every project at once.
