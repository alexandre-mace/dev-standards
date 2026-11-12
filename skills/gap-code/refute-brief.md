# Refutation brief

Handed as is to every refutation agent, placeholders filled in.

---

Repository: `<repo>`, on `<branch>` at `<commit>`. The findings come from a reading by
zones; you receive a subset of them, below.

**Your only job: try to refute each finding.** Read the cited code, its callers, its tests,
the commit that wrote the lines (`git log -L`), and the guideline it cites (`<guidelines>`,
the project's `AGENTS.md`). You edit no file, commit nothing, run neither CI nor a server; a
targeted test or a read-only script to prove a point is allowed. Write your verdicts to
`<return file>` and answer with their counts only.

What refutes a finding:

- the code already does what the finding says it does not, or the defect cannot happen;
- a commit or a docblock shows it is deliberate (verdict: accepted deviation, with the reason);
- it falls under `<skill>/not-a-finding.md`, which you read first;
- the proposed fix would break something (say what).

For each finding, one entry:

- **verdict**: `confirmé`, `réfuté` (why), `écart assumé` (the reason) or `à revoir` (what is missing);
- the priority you keep, lowered when the effect is smaller than claimed;
- the right fix, in one or two lines, when it differs from the one proposed;
- what to check in production before fixing, if anything.

<the findings>

In the project's language, concise, without em dashes or middle dots.
