# worktree-database

A test database per worktree. The test bootstrap of these projects drops and recreates
the schema, and Foundry's reset cuts every other connection to the database: two
`bin/phpunit` started from two worktrees of one project, on the one test database
`.env.test` names, broke each other. This script gives each linked worktree a suffix
that the test configuration appends to the database name; the main checkout gets none
and keeps the name it always had.

```sh
worktree-database suffix [folder]     # _wt_<folder>_<hash> in a linked worktree, nothing in the main checkout
worktree-database orphans <prefix>…   # the databases on stdin whose worktree no longer exists
symfony-react/worktree-database/tests/run.sh
```

- **The hash names the worktree**: the first eight characters of the SHA-1 of its
  physical path (`pwd -P`, so a symlinked path gives the same one). The folder name, cut
  to twenty characters, is there for a reader of `\l`. A ParaTest process number may
  follow (`TEST_TOKEN`): `app_test_wt_fix_login_1a2b3c4d3`.
- **Under PostgreSQL's 63 characters** as long as the name before the suffix keeps to 28:
  the suffix takes at most 33, and two more for the process number.
- **A value already set wins, even empty.** The bootstrap only asks when
  `TEST_DATABASE_SUFFIX` is absent from the environment: a ParaTest process inherits its
  parent's, and a local CI or a hook that names its own databases by role sets it, to
  empty or to its own suffix.
- **A SQLite file needs none** when it lives in the worktree's `var/`.
- **`orphans` reads names, not a server**: it takes the database list on stdin, keeps
  those that start with one of the prefixes followed by `_wt_`, and prints those whose
  hash matches no worktree git still lists with its folder present. Run it from any
  checkout of the project.

## In a project

The test bootstrap reads `TEST_DATABASE_SUFFIX` before Dotenv, like `TEST_TOKEN`, asks
this script when it is absent, and writes the value back after Dotenv; the test
configuration appends it, before the process number:

```yaml
when@test:
  doctrine:
    dbal:
      dbname_suffix: '_test%env(default::TEST_DATABASE_SUFFIX)%%env(default::TEST_TOKEN)%'
```

```php
$databaseSuffix = $_SERVER['TEST_DATABASE_SUFFIX'] ?? getenv('TEST_DATABASE_SUFFIX');
if (!\is_string($databaseSuffix)) {
    $databaseSuffix = WorktreeDatabase::suffix(dirname(__DIR__)); // refuses to run when the script is missing
}
new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
$_SERVER['TEST_DATABASE_SUFFIX'] = $_ENV['TEST_DATABASE_SUFFIX'] = $databaseSuffix;
```

`WorktreeDatabase::suffix()` returns an empty string when `.git` is a folder, the main
checkout or a hosted CI's clone, without starting git nor needing this repository.
In a linked worktree it finds the script next to the main checkout (`DEV_STANDARDS`
first, then `git rev-parse --git-common-dir`, `../machine-lock/README.md`), and the
bootstrap refuses to run when it is missing rather than falling back to the shared
database. The local CI exports `TEST_DATABASE_SUFFIX=''` (or its own role's suffix), so
its throwaway databases keep the names it creates and drops.

Every connection the tests write to takes the suffix, a second one included: the name
guard of the bootstrap (`symfony-guidelines.md`, « Mandatory safeguard ») reads the
effective name, suffix included, and still needs « test » in it.

The databases of worktrees removed since, listed from the main checkout and then dropped:

```sh
psql -d postgres -XAtc 'select datname from pg_database' | ../../dev-standards/symfony-react/worktree-database/worktree-database orphans app_test
psql -d postgres -XAtc 'select datname from pg_database' | ../../dev-standards/symfony-react/worktree-database/worktree-database orphans app_test | xargs -n1 dropdb --force
```
