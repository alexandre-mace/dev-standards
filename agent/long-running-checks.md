# Long-running checks

Applies whenever a task runs a slow check in a loop: a full CI pass, an E2E suite, a
benchmark, a deploy to watch. Whoever runs it, the main session or a delegated agent.

## Bound the run before starting it

- **A time budget and a count of full passes**, set before the first run and written into
  any brief handed to an agent: "ninety minutes, two full passes at the end, targeted
  runs in between". A brief that only says "three green passes in a row" has no end when
  a test flakes. Seen: an agent rerunning the whole CI for over three hours, each green
  pass followed by a red one, without ever reporting.
- **Iterate on the part that changed.** A full pass is the proof at the end, not the
  feedback loop: rerun the one group, file or spec touched.

## Stop on instability, do not retry it away

- A test that is red on one pass and green on the next is a finding, not noise. Stop,
  report which test, at which step, on how many tries, and what ran alongside it. Rerunning
  until a green pass shows up hides the defect and spends the budget.
- Raising a timeout or adding a retry to make it pass is the same hiding, unless the
  report says why the old value was wrong.

## Keep the machine able to finish

- A pass that spans a sleep is not a measurement: every step slows tenfold and timeouts
  fail at random. Ask the host to keep the machine awake before a long run; a closed lid
  or a manual sleep still sleeps, so say so to the user when the run is long.
- Two heavy suites running at once on one machine fight for the processor and make
  tests flaky: three local CIs started together on one laptop took the load to 40 to 65.
  A full suite (a local CI, `playwright test` or `phpunit` without a filter) goes through
  `machine-lock run --` (`symfony-react/machine-lock/`), which makes the second one wait
  and says who holds the machine; `bin/ci-local` takes it by itself. Checking `pgrep`
  first is not enough: it misses a suite started directly, and two sessions checking at
  the same instant both start.

## Report the measurement, not the impression

Give the duration before and after, the number of tests in each suite before and after,
and what was dropped and why. A speed-up that loses a test is not a speed-up.
