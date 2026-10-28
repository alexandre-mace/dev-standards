# performance-cores

Prints the machine's performance cores, so that `bin/ci-local` sets ParaTest's process
count from the machine instead of a number written in each project. Measured on
28/10/2026 on an Apple M5 Max (6 performance cores, 12 efficiency): Fabacée, Le Grenier and
La Grange all landed on six processes, the performance cores. ParaTest's default, one per
core, took every efficiency core too: on Fabacée eight processes saved only 6 s over six,
and La Grange ran as fast on six as on eighteen with half the load (the measurements are in
each project's test documentation).

```sh
symfony-react/performance-cores/performance-cores   # 6 on an M5 Max
```

- **Apple silicon**: `hw.perflevel0.physicalcpu`, the performance cores.
- **Intel Mac**: `hw.physicalcpu`, every physical core.
- **Linux**: `nproc`, which honours a container's CPU limit.
- Anything unreadable gives 1.

## In a project

`bin/ci-local` finds it like `machine-lock`, next to the main checkout or wherever
`DEV_STANDARDS` points, and keeps its variable as an override:

```bash
PERFORMANCE_CORES=${DEV_STANDARDS:-$CHECKOUT/../../dev-standards}/symfony-react/performance-cores/performance-cores
PHPUNIT_PROCESSES=${CI_LOCAL_PHPUNIT_PROCESSES:-$("$PERFORMANCE_CORES" 2> /dev/null || echo 1)}
```
