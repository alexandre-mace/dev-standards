# What is not a finding

The one list, read by every agent of a pass: zone, theme, refutation and fix lot. A rule
that keeps something out of the count is added here, nowhere else.

- **Taste.** A behaviour that works and could merely be otherwise. A rule broken, however
  small (a misplaced comment, a stale docblock, a missing `final`), is objective and counts.
- **The substance of a visible text**: labels, sentences, help texts, titles, buttons. The
  communication team writes them; only their form is a finding (a typo, an agreement,
  tutoiement, typography). A fact that matters and misleads (a figure, a price, a date, a
  legal or contractual commitment, a promise the product does not keep) is returned apart,
  as a question for that team, never as a finding and never fixed by a lot. A name that
  misstates its data (an export's column header, a field label) is not that team's text:
  it is a finding.
- **What the product shows, promotes or hides, and to whom.** The audit judges the code, not
  a product or marketing choice. Seen: a simulator's tax-reduction line hidden because no
  open fund offered it, when the business keeps advertising it between two fundraisings.
- **A behaviour a commit or a docblock explains.** `git log -L` on the cited lines, once,
  before calling it a defect: a deliberate choice is an accepted deviation, not a finding
  nor a question. Seen: a member already installed flagged for not seeing the installation
  path in the menu.
- **An accepted deviation** of the project's `docs/gap-analysis.md`, unless today's
  guideline overrules it: one below a version floor or a mandated configuration stands only
  on a named blocker and the date to re-check it.
- **What a feature behind a switch that is off in production does not do yet.** The code it
  has still counts. The project's `AGENTS.md` names the switch.
- **"Nothing uses it yet."** Data the product collects, a feature or a text is never removed
  on that ground. Seen: the address of each price search, collected for a dashboard to come,
  removed as "personal data nobody reads".
- **A canonical pattern that does not fit.** Code that departs from it is sometimes right for
  its situation. Seen: mutation hooks flagged for not using `useMutation`, in islands mounted
  without a `QueryClientProvider`, where the hook crashes on render; hand-written Zod schemas
  that validate the state of a form, not a payload.
