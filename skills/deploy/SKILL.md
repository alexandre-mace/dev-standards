---
name: deploy
description: Puts the current feature branch into production through the shared deploy script, then checks Sentry. Triggers - "mets en prod", "tu peux déployer", "ship it", "merge dans main", once the user has given the go.
allowed-tools: Bash(git *), Bash(deploy *), Bash(clever *), Bash(bin/ci-local *), Bash(pnpm ci:local*), Bash(machine-lock *), mcp__sentry__*
---

# Deploy to production

`main` is what production tracks, on CleverCloud as on Vercel: putting the branch's commits
on `main` is the deploy. On CleverCloud, `deploy` (`symfony-react/deploy/`, on the `PATH`
through `install.sh`) does it from the checkout or any worktree: it refuses a branch that
lacks commits already on `origin/main`, pushes `HEAD:main`, and follows the deployment of
that commit to its verdict. This skill holds what a script cannot decide.

## Rules

- **Only on the user's explicit go, in this conversation.** A green review, a passed UAT or
  a finished ticket is not a go. The deploy is irreversible and the decision is theirs. The
  one exception is a minor change as `/ticket` defines it, which ships without asking.
- **Nothing reaches `main` without `/review-diff`**, UAT fixes included. If the last
  review predates the last commit, stop and review first.
- Abort if the current branch is `main` or `preprod` (the script refuses it too).
- Stop and ask when changes are uncommitted. Offer `/commit`, never commit or stash
  silently.
- **Never force**, neither `main` nor `preprod`. A rejected push means someone deployed in
  between: fetch and start again from step 1.
- **A conflict goes back to the user.** Rebasing onto `origin/main` is routine; resolving a
  conflict in it is a decision about someone else's code.

## 1. Up to date with `origin/main`

`git fetch origin`, then `git log --oneline HEAD..origin/main`. Local `main` is no reference:
deploys pushed from worktrees do not move it. Empty, carry on. Otherwise
`git rebase origin/main`, and the commits reviewed are no longer the ones on the branch:
rerun `/quality` on the result.

## 2. One full local CI, under machine-lock

The hosted CI runs only on pull requests and at night, so a push to `main` starts no run:
`gh run list --branch` shows nothing to wait for and is not a gate. The gate is the
project's local CI, once, on the commit about to ship:

- `bin/ci-local` (`pnpm ci:local`), which takes `machine-lock` by itself. Where a project has
  none, its full suites through `machine-lock run -- <command>`, one at a time.
- Name the commit it ran on. A CI green on an earlier commit says nothing about this one.
- A red step stops the deploy. A test red then green on a rerun is a finding to report, not a
  pass (`agent/long-running-checks.md`): never rerun until it is green.
- If `origin/main` moves during the run, rebase again; rerun the groups the incoming commits
  reach (`bin/ci-local --only php|front|e2e`), a second full pass only when they touch the same
  files as the branch.

## 3. Deploy

Delete `.claude/plan.md` first: a stale plan would be read as the current one by the next
`/review-diff`.

```bash
deploy                                   # push HEAD to main, follow the production deployment
deploy --preprod                         # then realign preprod on main, if it holds nothing else
deploy --preprod --remove-worktree       # and remove this worktree and its branch
```

- **`--preprod`** whenever the project has a `preprod` branch: the next UAT then starts from
  production. The script leaves preprod alone when it holds commits `main` lacks, lists them
  and exits 4: report them, someone may be testing them. Never align by force.
- **`--remove-worktree`** when the session works in the worktree of this feature. It goes only
  if clean and in `origin/main`; the session continues from the main checkout. A remote
  branch of the same name: `git push origin --delete <branch>`, as the script says.
- **Exit codes**: 0 deployed, 1 the deployment failed (production stays on the previous
  version: read the logs the script names, report, never announce a deploy), 2 refused
  before pushing (read why), 3 no verdict within the bound (`clever activity --app <id>`),
  4 deployed but preprod or the worktree left as they were.
- The application followed is the `.clever.json` entry whose alias starts with `prod`;
  `--app <alias|id>` when a project names it otherwise.

**On Vercel**, no script: the same steps by hand. `git fetch`, refuse when `origin/main` is
ahead, `git push origin HEAD:main`, then wait for the production deployment of that commit
to be ready (`vercel ls`, or the dashboard) before announcing anything.

## 4. Sentry

The script ends on the time of the deploy. With the Sentry tools, in the project's
organisation and project (its `AGENTS.md` names them), look at the issues first seen or
regressed since that time, in production:

- A new issue whose stack trace runs through the diff is the deploy's: report it at once,
  with the fix or a revert commit, which goes through this same skill.
- Nothing new is a result too: say what was searched (project, window, environment).
- A few minutes after the verdict is the earliest it means anything: an error needs a request
  to happen. Say how long after the deploy you looked.

## Report

What reached `main` (commits, `<from>..<to>`), the local CI it passed (commit, duration), the
deployment's verdict and duration, what Sentry showed, preprod's state, and whether the
worktree and the branch are gone, local and remote.
