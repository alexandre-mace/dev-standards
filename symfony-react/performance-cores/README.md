# performance-cores

Prints the machine's performance cores, so that `bin/ci-local` sets ParaTest's process
count from the machine instead of a number written in each project. Measured on
28/10/2026 on an Apple M5 Max (6 performance cores, 12 efficiency): three projects all
landed on six processes, the performance cores. ParaTest's default, one per core, took
every efficiency core too: on one project eight processes saved only 6 s over six, and
another ran as fast on six as on eighteen with half the load (the measurements are in each
project's test documentation).

```sh
symfony-react/performance-cores/performance-cores   # 6 on an M5 Max
```

- **Apple silicon**: `hw.perflevel0.physicalcpu`, the performance cores.
- **Intel Mac**: `hw.physicalcpu`, every physical core.
- **Linux**: `nproc`, which honours a container's CPU limit.
- Anything unreadable gives 1.

## In a project

`bin/ci-local` reads it through its shared library (`ci_phpunit_processes`,
`../ci-local/README.md`), from the same `dev-standards` as the library, which the
project's script found next to its main checkout, the lookup that works from every
worktree, including one under `.claude/worktrees/` (`../machine-lock/README.md`, "Finding
dev-standards from a worktree"). `CI_LOCAL_PHPUNIT_PROCESSES` overrides the count, and the
PHPUnit line says so when the script was not found, rather than falling back to one
process in silence: on 28/10/2026 one project's ParaTest dropped to one process from a
`.claude/worktrees/` worktree without a word.
