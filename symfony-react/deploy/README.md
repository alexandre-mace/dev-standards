# deploy

Puts the current branch into production on a CleverCloud project, then follows the
deployment until CleverCloud gives its verdict. CleverCloud deploys `main` on every push,
so the push is the deploy; what this script adds is everything around it that a hand-typed
`git push` skips: refusing a branch that lacks commits already on `main`, and waiting for
the build's verdict instead of announcing a deploy that failed. Bash 3.2 or later, plus
`git`, `php` (to read JSON) and `clever` (clever-tools, logged in).

```sh
deploy                       # push HEAD to main, follow the production deployment
deploy --preprod             # then bring preprod back in line with main
deploy --remove-worktree     # then remove this worktree and its local branch
deploy --app <alias|id>      # follow another application than the prod alias
deploy --timeout <s>         # bound on the wait for the verdict, 1800 by default
deploy --start-timeout <s>   # wait before relaunching a deployment that never started, 180 by default
```

`/deploy` runs it once the user has given the go; the rules around it (the go, the review
before, Sentry after) are in that skill. The script only does what a script can check.

- **From anywhere in the project**: the checkout, a worktree under `.claude/worktrees/`,
  a subfolder of either. On a branch, never on `main` or `preprod`.
- **Refused before any push** (exit 2): uncommitted changes, untracked files included;
  `origin/main` holding commits HEAD lacks, after a `git fetch`, listed so the branch can be
  rebased and checked again. The script never rebases nor merges by itself: a conflict is the
  user's call.
- **`git push origin HEAD:main`**, never forced. When `origin/main` is already HEAD, nothing
  is pushed and the script follows the deployment of that commit.
- **The application followed** is the entry of the project's `.clever.json` whose alias
  starts with `prod`, whatever its case, read from the worktree or, failing that, from the
  main checkout. Its id goes to `clever activity --app <id>`, which works from any directory,
  unlike `clever activity` alone, which needs a folder linked to the application.
  `--app` takes an alias or an id from that file, or any `app_…` id.
- **The verdict** is that of the deployment of the pushed commit, or of a later commit that
  contains it (someone pushed in between and CleverCloud dropped ours as `CANCELLED`).
  A deployment of the same commit older than the push does not count. `OK` exits 0, `FAIL`
  exits 1 with the last 30 lines of its log, no verdict before the bound exits 3, and so
  does `clever activity` failing three times in a row (not logged in). One line each time
  the state changes, polled every 15 s (`DEPLOY_POLL_INTERVAL`).
- **The log of a failed deployment** comes from `clever logs --app <id> --deployment-id <id>`,
  the id read in `clever activity`, between a minute before the deployment and now:
  `--before` makes `clever logs` stop instead of streaming, and the call is killed after
  60 s anyway. Colours and blank lines removed. When it cannot be read, the script gives
  the command.
- **One relaunch at most per run**, with `clever restart --app <id> --commit <sha>`, which
  hands back once the deployment has started (`--exit-on deploy-start`), the script then
  following it like any other. Two causes, seen in production:
  - no deployment of the pushed commit after `--start-timeout` (180 s): GitHub's webhook
    never reached CleverCloud, and nothing would have happened until someone ran the same
    command by hand. A deployment already listed, in any state, is never relaunched;
  - a `FAIL` whose last log lines show a download that failed on the way (`HTTP/2 429`,
    502, 503 or 504, `could not be downloaded`, a connection reset or timed out): Composer
    fetching a dist archive from GitHub, which the same commit passes a few minutes later.
    The commit relaunched is the failed deployment's. Any other failure exits 1 at once.

  The two share the single relaunch, production and preprod together: a deployment
  relaunched for not starting that then fails on a 429 exits 1.
- **Sentry is not read here**: a script would need a token, and the skill has the Sentry
  tools. The last line gives the time of the deploy, to look from.

## `--preprod`

Once production is OK, the `preprod` branch is brought back to `main`'s content, so the
next UAT starts from what is in production rather than from older tests.

- **Only when preprod holds nothing main lacks.** `git cherry origin/main origin/preprod`
  lists each preprod commit with no patch-equivalent on main; merges are left out, their
  content being the commits they bring. If it lists one, preprod stays as it is, the
  commits are printed with their author, and the exit code is 4: deploying them or dropping
  them is a decision, and someone may be testing them.
- **Never forced.** When preprod is an ancestor of main, a fast-forward. Otherwise (the
  merges `/preprod` leaves behind), a merge commit whose tree is main's, made with
  `git commit-tree` on top of preprod, so the push is still a fast-forward and preprod's
  history stays.
- The preprod deployment is then followed like production's, when `.clever.json` has an
  alias starting with `preprod`.

## `--remove-worktree`

Once deployed, the worktree the script runs from goes, with its local branch, when it is
still clean and HEAD is in `origin/main`. `git worktree remove` without `--force`: ignored
files (`vendor/`, `var/`, `node_modules/`) go with it, anything else stops it. The branch is
deleted with `-D` because `-d` compares with the main checkout's HEAD, which deploys pushed
from worktrees leave behind; the script has just checked it against `origin/main`. A remote
branch of the same name is not deleted, the script gives the command. From the main
checkout, there is no worktree to remove and nothing happens.

The session's working directory disappears with the worktree: continue from the main
checkout.

## Exit codes

| Code | Meaning |
|---|---|
| 0 | deployed (and preprod aligned, worktree removed, when asked) |
| 1 | the deployment failed, production stays on the previous version |
| 2 | refused before pushing, or wrong usage |
| 3 | pushed, no verdict within the bound, or `clever` unreachable |
| 4 | deployed, but preprod or the worktree were left as they were |

## Install

`install.sh` links it into `~/.local/bin`, with `machine-lock` and `performance-cores`, so
`deploy` runs from any project. The link follows this checkout: a `git pull` here updates it.

## Tests

```sh
symfony-react/deploy/tests/run.sh
```

About thirty seconds, on throwaway repositories (a bare origin, a clone, a worktree) and a
fake `clever` that answers from files: the refusals (on `main`, uncommitted changes,
`origin/main` ahead) push nothing; `OK`, `FAIL`, no verdict and `clever` in error give 0, 1,
3 and 3; an older deployment of the same commit does not count, a running one is never
relaunched; a deployment that never starts is relaunched once with the pushed commit, then
followed to `OK`, or to exit 3 when nothing comes; a `FAIL` on a 429 prints the end of that
deployment's log and is relaunched once, `OK` gives 0 and a second `FAIL` gives 1; any
other `FAIL` gives 1 without relaunch; a relaunch for not starting leaves none for the
429 that follows; the prod alias is found
whatever its case, `--app` picks another one, from a subfolder; preprod holding only a merge
is aligned to main's tree without losing its history, preprod holding its own commit is left
alone with exit 4; the worktree and its branch go once deployed, ignored files included,
and nothing happens from the main checkout.
