# machine-lock

One lock for the whole workstation, so that two sessions never run two heavy suites at
once. A local CI takes the whole machine (ParaTest one process per core, Playwright on
four workers, Vitest): measured on an M1 Pro with three projects' CI started together, the
load reached 40 to 65 and tests unrelated to any change missed their timeouts. Checking
`pgrep -f bin/ci-local` before starting missed a session running `playwright test` or
`phpunit` directly, and two sessions checking at the same instant both started. Bash 3.2
(the one macOS ships), nothing to install.

```sh
machine-lock run -- vendor/bin/phpunit   # waits for the lock, runs, gives it back
machine-lock status                      # who holds it, or "libre", then who waits
machine-lock held                        # exit 0 if this process chain holds it
```

- **The lock comes back whatever happens**: success, failure, Ctrl-C, `kill -9`. It is a
  BSD lock (`flock(2)`) held by the script's shell, through `lockf` on macOS and `flock`
  on Linux, and the kernel drops it when that shell dies; after a reboot nobody holds it.
  The command runs with the lock's descriptor closed, so a daemon it leaves behind does
  not keep the machine locked.
- **The status is the command's**, 130 after a Ctrl-C.
- **While waiting**, one line says who holds it (repository, folder, command, since when)
  and, with others waiting, this run's place: « position 2 sur 3 ».
  A terminal gets that line rewritten in place; a log gets a new line when the holder
  changes, then one a minute. `MACHINE_LOCK_TIMEOUT` bounds the wait in seconds; past it,
  exit 75 with the same line.
- **A nested run does not wait on itself**: a run under a run of the same process chain
  (a CI rerunning one step under the lock) starts at once. `MACHINE_LOCK_HELD`, set by
  the outer run, counts only when the holder it names is an ancestor of the caller.
- **The machine stays awake while the command runs**, on macOS: `caffeinate -i -w <pid>`
  follows the run's shell and lets the machine sleep again once it is gone, `kill -9`
  included. A pass that spans a sleep measures nothing: every step slows down and timeouts
  fail at random (`agent/long-running-checks.md`). It prevents idle sleep only: a closed
  lid or a sleep asked from the menu still sleeps, so say so before a long run. Elsewhere,
  nothing.
- **No lock on a hosted runner** (`GITHUB_ACTIONS=true`): the lock is a workstation
  matter. `CI` alone does not tell, since `bin/ci-local` sets `CI=true` itself.
- **One place per user**, outside any repository: `~/.local/state/machine-lock/`, so
  every checkout and worktree shares it. `MACHINE_LOCK_DIR` moves it, for the tests.
- **First come, first served.** Each waiter draws a numbered ticket in
  `~/.local/state/machine-lock/queue/` and tries the lock only once its ticket is the
  oldest alive. Without it, the waiter that happened to retry first took the lock: on
  28/10/2026 a local CI waited 28 minutes while three runs started after it went first.
  A ticket names its process and that process's start time, so a waiter killed with
  `kill -9`, or a pid reused by another process, is swept away by the next waiter that
  looks; Ctrl-C or a bounded wait takes the ticket back at once. `status` lists the queue
  in the order it will be served.

## In a project

`bin/ci-local` takes the lock by itself: it reruns itself under `machine-lock run` at
start, once its arguments are read (so `--help` does not wait) and before it touches
`var/ci-local/`. It finds `dev-standards` next to the project's main checkout, the one
lookup that works from every worktree (see "Finding dev-standards from a worktree" below).

```bash
ARGS=("$@")
# … arguments read …
if [ "${GITHUB_ACTIONS:-}" != true ]; then
    DEV_STANDARDS_DIR=${DEV_STANDARDS:-$(dirname "$(git -C "$(dirname "$0")" rev-parse --path-format=absolute --git-common-dir)")/../../dev-standards}
    MACHINE_LOCK=$DEV_STANDARDS_DIR/symfony-react/machine-lock/machine-lock
    if [ ! -x "$MACHINE_LOCK" ]; then
        echo "machine-lock introuvable : $MACHINE_LOCK" >&2
        exit 2
    fi
    "$MACHINE_LOCK" held || exec "$MACHINE_LOCK" run -- "$0" ${ARGS[@]+"${ARGS[@]}"}
fi
```

A full suite run on its own, `playwright test` or `phpunit` without a filter, goes through
`machine-lock run` too. A filtered run (one file, one test) does not need it.

## Finding dev-standards from a worktree

The projects sit next to each other under `~/dev/<organisation>/`, and `dev-standards`
under `~/dev/`. Every script that calls into it (`bin/ci-local`, `bin/check-mechanical`,
`bin/worktree-setup`) looks for it next to the project's **main checkout**, which
`git rev-parse --git-common-dir` gives from any worktree: its parent is that checkout.

```bash
DEV_STANDARDS_DIR=${DEV_STANDARDS:-$(dirname "$(git -C "$(dirname "$0")" rev-parse --path-format=absolute --git-common-dir)")/../../dev-standards}
```

- **Not from the script's own path** (`$(dirname "$0")/../../../dev-standards`): it works
  from a worktree that sits next to the project (`~/dev/<project>-x`), and fails from
  one under `.claude/worktrees/<name>/`, where Claude Code puts them, by looking for
  `dev-standards` inside `.claude/`. On 28/10/2026, two projects' `bin/ci-local` and
  `bin/check-mechanical` still looked that way: the local CI and the pre-commit hook
  exited in 2 there, and one project's ParaTest fell silently to one process.
- **`DEV_STANDARDS`** points at another copy, a worktree of this repository for instance,
  to try a change to a shared script before it is merged.
- **`--path-format=absolute`** (git 2.31): without it, the main checkout answers a
  relative `.git`.

The PHP version, for `bin/check-mechanical`, is in `../check-mechanical/README.md`.

## Tests

```sh
symfony-react/machine-lock/tests/run.sh
```

On a lock of their own, about thirty seconds: three concurrent holders follow one
another, four waiters are served in their order of arrival even when a later one retries
first, `status` lists the queue and a waiter gives its place, a waiter killed with
`kill -9` does not hold up the next one, Ctrl-C while waiting takes the ticket back, a holder killed with `kill -9` or interrupted with Ctrl-C gives the lock back,
the command's status comes back, the wait is bounded and names the holder, a nested run
passes and a `MACHINE_LOCK_HELD` inherited from elsewhere does not, a hosted runner
skips the lock, and on macOS the machine stays awake while the command runs and may sleep
again once it ends or its holder is killed (read from `pmset -g assertions`).
