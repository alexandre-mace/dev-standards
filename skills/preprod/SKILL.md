---
name: preprod
description: Merges the current feature branch into preprod for UAT and follows the preprod deployment to its verdict. Never touches main. Triggers - "pousse en preprod", "mets en preprod", "envoie en recette", or as the last step of /ticket once /review-diff passed.
allowed-tools: Bash(git *), Bash(deploy *)
---

# Push to preprod

`deploy --uat`, from the feature branch (`symfony-react/deploy/`, on the `PATH`): it pushes
the branch, merges it into `preprod` without a checkout, pushes that, and follows the
preprod deployment, relaunching once a deployment that never started. The branch you are
on does not move.

**Applies to**: the Symfony + React stack, which has a long-lived `preprod` branch that
CleverCloud tracks. On Next and TanStack Start there is nothing to merge: Vercel builds a
preview per branch, so pushing the branch is the UAT deploy. Skip this skill there and
hand over the preview URL.

## Rules

- **The feature request is the go.** When `/ticket` (or the user) asked for the feature and
  `/review-diff` passed, merge without asking: the user does not want to be asked to approve
  UAT for work they requested. Ask first only when preprod may be holding someone else's
  test in progress (`git log --no-merges origin/main..origin/preprod`, which the script also
  lists), or when the branch is not the one the user asked for.
- **Never delete the feature branch.** It stays for the UAT fixes and the eventual
  `/deploy`.
- Stop and ask when changes are uncommitted. Offer `/commit`, never stash or commit
  silently.
- A merge conflict (exit 2, the files named) stops everything. Resolving it is the user's
  call, not a guess.
- Hand over only on exit 0. Exit 1 is a failed build: the user would test the previous
  version. Exit 3 is no verdict: say so, with the `clever activity` line it gives.
