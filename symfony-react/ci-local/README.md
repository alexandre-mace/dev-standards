# ci-local

The shared core of a Symfony + React project's `bin/ci-local`, the local replay of its
GitHub CI that gates every merge. Sourced, never run: the project's script declares its
groups and keeps only its own steps, and everything that does not depend on the project
lives here. Bash 3.2 or later (the one macOS ships), plus what the steps need.

Before it, three projects carried their own copy, 866 to 981 lines each, twelve functions
byte-identical, and a fix landed in one copy and not the others. The check on a Symfony
server already running read `symfony server:status | grep -q` under `pipefail`, which
fails whenever grep closes the pipe before the end of the write, in one project after the
two others had fixed it; two of the three ran their steps without `set -e`, which the
third had added after a `cache:warmup` killed for lack of memory passed green. After the
move, the projects keep 347 to 498 lines.

```bash
#!/usr/bin/env bash
# What the script replays, in a paragraph.
#
#   bin/ci-local              everything, E2E included
#   bin/ci-local --fast       everything but the E2E
#   bin/ci-local --only php   one family: php, front or e2e
#   bin/ci-local --spec e2e/x.spec.ts
#                             the E2E alone, on that spec (repeatable)
#
# The rest of the header, not shown by --help.

CI_LOCAL_LIB=${DEV_STANDARDS:-$(dirname "$(git -C "$(dirname "$0")" rev-parse --path-format=absolute --git-common-dir)")/../../dev-standards}/symfony-react/ci-local/ci-local-lib.sh
[ -r "$CI_LOCAL_LIB" ] || { echo "ci-local-lib.sh introuvable : $CI_LOCAL_LIB" >&2; exit 2; }
. "$CI_LOCAL_LIB"

ci_group php 'PHP' run_php php 10          # id, title, function, family, niceness
ci_group front 'Front' run_front front 10
ci_group vitest 'Vitest' run_vitest front
ci_group e2e 'E2E' run_e2e e2e
ci_parse_args "$@"
ci_phpunit_processes                       # PHPUNIT_PROCESSES, PHPUNIT_NOTE
ci_start DATABASE_URL APP_CACHE_DIR        # lock, var/ci-local/, CI=true, unsets, traps

dotenv_files() { case "$1" in test) cp .env.test "$2/.env.test" ;; esac; }
on_cleanup() { ci_stop_server; }
ci_context "de travail, et la dérive des types compare à l'état commité."

# … the project's steps and its run_* functions …

echo 'Préparation'
step 'Dépendances Composer' composer_install
ci_run                                     # the selected groups, the summary, the exit
```

The lookup of `dev-standards` is the one line every project writes, since the library
cannot find itself: next to the project's main checkout, which `git rev-parse
--git-common-dir` gives from any worktree, `.claude/worktrees/` included
(`../machine-lock/README.md`, "Finding dev-standards from a worktree"). The library then
reads `machine-lock` and `performance-cores` from its own folder.

## What it does

- **Groups in parallel after a common preparation.** A group is a function of steps run in
  the background, its output shown in one block when it ends, in the order groups finish.
  Each stops at its first failure, the others go on. A group with no timeout to keep (the
  analyses, PHPUnit) takes a niceness of 10, since Playwright waits a few seconds for each
  answer next to it.
- **Meeting points through marker files**: `signal <marker>` in one group, `await
  <marker> <group>` in another. The wait ends on the marker or on the end of that group,
  whatever its outcome, so a group that stopped early never leaves another waiting; and a
  group that is not in the pass (`--only`, `--fast`) is not waited for.
- **`step 'Name' function`**: the function runs in a subshell under `set -e`, so a step of
  several commands fails at the first one, not only at the last. Its output goes to
  `var/ci-local/<group>-<number>-<function>.log`, its line (`OK`, duration, name, and
  `STEP_EXTRA` in brackets) to the group's block; on failure, the last 60 lines of its log
  follow. `skip` writes a `SAUTÉ` line and a note, `fail` an `ÉCHEC` line outside a step
  (a database unreachable, a port taken), `note` a line for the summary.
- **A timeline**: each step's start and end to the second, its group and the load average
  at both instants, in `var/ci-local/timeline`, sorted at the end. A log's date is only its
  last write, and `ls -l` rounds it to the minute: a step ended just before Vitest looked
  as if it had run during it.
- **Vitest sampled**: `vitest_step 'Name'` runs Vitest under the latest Node of the CI's
  major (`CI_NODE_MAJOR`, 22 by default, its version on the step's line) and samples `ps`
  every 2 s meanwhile, into `var/ci-local/vitest-cpu.tsv`; one line under the group names
  what used the processor next to it, the rest of the run or the rest of the machine. It is
  the « what ran alongside » that `agent/long-running-checks.md` asks of a flaky report.
  The project still decides when Vitest runs: alone, once the analyses and the build have
  met it, since next to them it missed its timeouts (`../symfony-guidelines.md`, §13).
- **Throwaway databases**: `ci_db_base <prefix> <length>` names them
  `<prefix>_ci_<branch>_<hash>`, two worktrees being on two branches; `pg_create` records a
  database before creating it, `pg_reserve` one a group will create (a group is a subshell,
  what it records never reaches the cleanup), `pg_reserve_copies` and `pg_copy` the E2E
  workers' copies (`createdb -T`, `_w1` to `_w<n-1>`). `pg_url` builds the URL with the
  local server's version (`pg_version <CI version>`, a note when they differ).
- **Cleanup, whatever happens**, `trap … EXIT`: the groups' process trees (a background
  job of a non-interactive shell ignores SIGINT), the project's `on_cleanup` when it
  defines one, the recorded databases, the run's transient files. Ctrl-C prints
  « Interrompu. » and exits 130. Logs and timeline stay.
- **Warnings at start**: the deprecated `--only` name used, uncommitted changes (the steps
  read the working tree; the argument of `ci_context` says what the project's checks
  compare to), `.husky/_` missing (git then runs no hook in that worktree), and a branch
  that does not contain a freshly fetched `origin/main` (`CI_FETCH_REFS` fetches more refs).
- **The summary**: the total duration, the notes (`CI_NOTES_TITLE` renames their heading),
  then `CI locale : OK` or `ÉCHEC`, and the exit status.
- **The environment the CI sees**: `CI=true`, the caller's `APP_DEBUG`,
  `PLAYWRIGHT_BASE_URL`, `TEST_TOKEN`, `SYMFONY_DOTENV_VARS` and `APP_RUNTIME` unset, plus
  what `ci_start` is given. `compute_env <mode> VAR=value…` composes the `.env` files with
  Dotenv in a temporary folder, without `.env.local`, from the files the project's
  `dotenv_files <mode> <folder>` copies there; `load_env <mode>` exports the result in a
  step.
- **The E2E server**: `ci_e2e_preflight [port]` refuses, in the preparation, a missing
  Symfony CLI, a server already serving the folder (Symfony accepts one per folder, and the
  cleanup would stop the development server) and a taken port. `ci_server_opcache` enlarges
  OPcache: at the default 8 MB the interned strings buffer overflowed and a PHP-FPM process
  died once in SIGSEGV with four workers connecting. `ci_serve <port> <path>` starts the
  server and waits up to 30 s for that page, `ci_stop_server` stops it, `ci_server_errors`
  prints its errors and 5xx answers after a red Playwright.

## The command line

- **`--only` takes a family, `php`, `front` or `e2e`**, declared by each group, so the
  same three names select the same kind of work in every project whatever its groups are
  called. A project whose groups were selected by their own names keeps them with
  `ci_only_alias <name> <groups…>`: the old name selects the same groups as before, and the
  start of the run names the family that replaces it.
- **`--fast`** drops the `e2e` family; **`--spec <file>`**, repeatable, keeps it alone and
  passes the specs, made absolute, in `E2E_SPECS`.
- **Refused**: `--spec` with `--fast` or `--only`, two `--only`, `--fast` with `--only e2e`,
  an unknown family. Options used to reset one another, the last one winning: `--spec x
  --fast` ran no group and concluded « OK », and a second `--only` replaced the first
  without a word.
- **`--help`** prints the header of the project's script, from its second line to its
  second bare `#`: what it does, then how to call it.
- `RUN_<GROUP>` (1 or 0) and `ci_runs <group>` tell the project's preparation which groups
  run.

## The machine

- **Under `machine-lock run`**: `ci_start` reruns the script under the lock once its
  arguments are read, so `--help` and a refused option do not wait, and before it touches
  `var/ci-local/`. A second run waits its turn and says who holds the machine; the lock
  keeps a Mac awake while the run lasts (`../machine-lock/README.md`). Nothing on a GitHub
  runner.
- **ParaTest's process count**: `ci_phpunit_processes` reads the performance cores
  (`../performance-cores/README.md`), `CI_LOCAL_PHPUNIT_PROCESSES` overriding them (`auto`
  too when the project passes `auto`); without the script, one process and `PHPUNIT_NOTE`
  saying why, for the PHPUnit line. Read before the lock, so a wrong value does not wait
  for the machine.

## Tests

```sh
symfony-react/ci-local/tests/run.sh
```

About thirty seconds, on a throwaway project with stubs for `dropdb`, `createdb`, `psql`
and `npx`, and a lock of its own: the refusals of the command line, the header as help, a
green pass (one block per group, one line per step, a sorted timeline, logs named by
group and step, transient files removed), a meeting point that waits for its marker and
one that does not hang on a stopped group, `--fast`, `--only` by family and by an old name
with its warning, `--spec` with absolute paths, a red step and the rest of its group
skipped while the others finish, `set -e` within a step, a red preparation, the notes in
the summary, Vitest's sampling line, an interrupted run that exits 130 with its processes
killed and its databases dropped, the wait for a lock held elsewhere, and no lock on a
GitHub runner.
