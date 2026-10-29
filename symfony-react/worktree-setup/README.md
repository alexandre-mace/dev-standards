# worktree-setup

Brings a new worktree of a Symfony + React project to the point where its first commit
passes the pre-commit hook. Without it, a new worktree fails that first commit on things
that have nothing to do with the change: dependencies missing, `lint:container` reading a
debug container that was never written, the guides of `docs/` pointing nowhere. Bash 3.2
or later, plus what the project already needs (PHP, Composer, pnpm).

```sh
bin/worktree-setup           # dependencies, dev cache warmed, hook and links checked, local files linked
bin/worktree-setup --build   # the same, then pnpm build, to look at the result in a browser
```

One line per step with its duration; at the first failure, the end of that step's log
and a stop. Logs in `var/worktree-setup/`. Measured on one project on 28/10/2026: 15 s on a
new worktree, 5 s on a second run, and the commit after it passed the hook in 78 s.

- **`composer install` with `memory_limit=1G`**, through a `php.d` added to
  `PHP_INI_SCAN_DIR`, so every PHP process Composer starts gets it. The `cache:clear` of
  Composer's auto-scripts runs under the `php.ini` limit, 128 MB by default, and dies
  there after writing the debug container's `.xml` and before its `.ser`.
- **`assets:install`**, when the public directory has no `bundles/` after Composer: a
  project whose auto-scripts do not run it, or stop before it, serves its back office
  without its styles.
- **`pnpm install --frozen-lockfile`**, whose `prepare` script installs the husky hook.
- **The dev cache warmed with 1 GB**, rebuilt from scratch when its `.ser` is missing: as
  long as the `.xml` stays fresh, nothing rewrites the `.ser`, and `lint:container` fails
  reading it. Then a check that the `.ser` is there, found by name
  (`var/cache/dev/*DevDebugContainer.ser`) rather than assumed from `App\Kernel`.
- **The hook is installed**: `core.hooksPath` names a folder that holds a `pre-commit` in
  this worktree. A worktree without it commits with no check at all.
- **The guides of `docs/` resolve.** They are tracked symlinks,
  `../../../dev-standards/…` from `docs/`, right for the main checkout and for a worktree
  next to it. From `.claude/worktrees/<name>/`, where Claude Code puts its worktrees, the
  same text points into `<main checkout>/.claude/dev-standards`: the script makes a link
  there, to the `dev-standards` it runs from, in a folder the project already ignores.
  Nothing tracked changes. Without it, a project's hook refused the commit on 28/10/2026,
  its conventions check reading the documents a comment cites. A link made while
  `DEV_STANDARDS` pointed at a worktree of this repository follows it: remove
  `<main checkout>/.claude/dev-standards` once that worktree is gone.
- **The local files of the main checkout are linked.** What a project keeps out of git
  on purpose lives only in its main checkout, and a new worktree starts without it: every
  entry of `.claude/skills/` (the project's own skills), `.claude/launch.json` (the
  preview launcher), `docs/*.local.md` (local notes, test accounts for one). Each is a
  symlink to the main checkout's copy, never a copy, so there is one version to keep up to
  date. A path that already exists in the worktree is left alone, and a path git would not
  ignore there is not linked, so none of it can be committed. Nothing happens from the main
  checkout itself.
- **`.env.local` is never read, written nor copied.** A new worktree has none, Symfony
  runs on `.env`, and a copy would carry the main checkout's secrets and database into a
  folder an agent works in.

## In a project

The project keeps a `bin/worktree-setup` of a few lines that finds this script next to
its main checkout and runs it from the worktree's root:

```bash
#!/usr/bin/env bash
# Met en route un worktree neuf : dépendances, cache dev préchauffé, hook vérifié
# (--build ajoute pnpm build). Le script est commun aux projets Symfony + React :
# dev-standards, symfony-react/worktree-setup/ (son README), trouvé à côté du
# checkout principal, ou là où pointe DEV_STANDARDS.

DEV_STANDARDS_DIR=${DEV_STANDARDS:-$(dirname "$(git -C "$(dirname "$0")" rev-parse --path-format=absolute --git-common-dir)")/../../dev-standards}
SETUP=$DEV_STANDARDS_DIR/symfony-react/worktree-setup/worktree-setup
if [ ! -x "$SETUP" ]; then
    echo "worktree-setup introuvable : $SETUP" >&2
    exit 2
fi
cd "$(dirname "$0")/.." && exec "$SETUP" "$@"
```

**Finding `dev-standards` from a worktree** goes through `git rev-parse --git-common-dir`,
whose parent is the project's main checkout, from any worktree: next to the project or
under `.claude/worktrees/`. The script's own path is not enough, since from
`.claude/worktrees/<name>/bin/` three levels up land inside `.claude/`. The full reasoning
is in `../machine-lock/README.md`, "Finding dev-standards from a worktree", and the same
lookup serves `bin/ci-local` and `bin/check-mechanical`.

The project's `AGENTS.md` says to run `bin/worktree-setup` from the root of any new
worktree, before the first commit.
