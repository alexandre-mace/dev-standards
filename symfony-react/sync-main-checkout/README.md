# sync-main-checkout

Keeps a project's main checkout, the folder the worktrees hang from, on `origin/main`.
Nobody works there, every change has its worktree, but two things read it:

- **A session started there reads its `AGENTS.md` and `CLAUDE.md`.** Seen: a session read an
  `AGENTS.md` 59 commits old, which still said to commit on `main` and
  to write Stimulus controllers, two rules removed since.
- **A worktree cut « from HEAD » starts from it**: the worktrees of subagents, and any
  `git worktree add` without a base. `/ticket` cuts from `origin/main` and is not concerned.

`deploy` pushes `HEAD:main` from the worktree, which moves `origin/main` and never the
checkout's `main`: without this script, it falls behind on every deploy.

```sh
sync-main-checkout             # fetch origin, then fast-forward the main checkout
sync-main-checkout --no-fetch  # without fetching: what deploy runs after the production verdict
sync-main-checkout --hook      # for the SessionStart hook: silent when up to date, exit 0 outside a repository
```

- **From the checkout or any of its worktrees**: the checkout is the parent of
  `git rev-parse --git-common-dir`. A bare repository has none, and nothing happens.
- **Fast-forward only, on the default branch** (`origin/HEAD`, `main` failing that). Nothing
  moves, and one line says why, when the checkout is on another branch or a detached HEAD,
  holds an uncommitted change to a tracked file, or a commit `origin` lacks. Git itself
  refuses a fast-forward that would overwrite an untracked file. Never a reset, a stash or a
  merge commit.
- **After moving**, one line each when a lockfile changed (reinstall before running code from
  there) and when an `AGENTS.md` or `CLAUDE.md` changed: printed by the hook, the line enters
  the session's context, so a session that loaded the old instructions knows to reread them.
  It always has: Claude Code reads `CLAUDE.md` before the SessionStart hooks run, even an
  instant one (measured with Claude Code 2.1.318), so the move only reaches the next session.
- **Never hangs a session's start**: no credential prompt (`GIT_TERMINAL_PROMPT=0`), ssh in
  batch mode with a five-second connect timeout unless an ssh command is configured, and a
  failed fetch is one line, exit 0.
- Exit 0 whether it moved or not, 2 on a wrong option or outside a repository without `--hook`.

## The SessionStart hook

`deploy` covers the pushes made from this machine; the hook covers the others (a cloud
session, another machine). It lives in `~/.claude/settings.json`, outside this repository,
so here is the entry to recreate, next to the other `SessionStart` hooks:

```json
{
  "type": "command",
  "command": "/Users/<you>/.local/bin/sync-main-checkout --hook",
  "timeout": 15
}
```

About two seconds at a session's start, most of it the fetch.

## Tests

```sh
symfony-react/sync-main-checkout/tests/run.sh
```

A few seconds, on throwaway repositories: from a worktree and from the checkout itself, the
checkout moves and the worktree does not; up to date, `--hook` says nothing; a changed
`AGENTS.md` or `composer.lock` is named; an uncommitted change, an untracked file in the way,
another branch, a local commit and an unreachable origin each leave the checkout where it
was and say why; `--no-fetch` moves on what is already fetched; outside a repository,
`--hook` is silent and exits 0, without it exits 2.
