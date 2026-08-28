# Dev Standards

The recipe for each stack Alexandre Macé builds on, in the version he and the `/sota-gap` watch agree is state of the art, plus the Claude Code skills that go with them.

## Stacks

### `symfony-react/` : pro (lagrange, fabacee, legrenier)

- **`symfony-guidelines.md`** : Backend architecture: Domain/Service/Controller, enums, repositories, commands, DTOs, Doctrine patterns
- **`reactony.md`** : Symfony + React integration: forms, mutations, type pipeline, error handling, SDK usage

### `next/` : perso (portfolio, climatelab, wealth, ui kit…)

- **`next-guidelines.md`** : Static-first Next.js sites: App Router, aria-nova kit, baked data pipeline, SEO, Vercel

### Transverse

- **`design-system-page.md`** : The living design-system page: one format across the three worlds (classic repo, Webflow via API, the kit itself)

## Usage

Clone this repo and symlink the relevant stack's files into your project's `docs/` directory:

```bash
# Symfony + React project
ln -s ~/dev/dev-standards/symfony-react/symfony-guidelines.md docs/symfony-guidelines.md
ln -s ~/dev/dev-standards/symfony-react/reactony.md docs/reactony.md

# Next.js project
ln -s ~/dev/dev-standards/next/next-guidelines.md docs/next-guidelines.md
```

Then reference them in your project's `CLAUDE.md`:

```markdown
## Architecture & Guidelines

Detailed conventions are in `docs/` (symlinked from dev-standards).
These docs are the source of truth for code conventions.
```

## Claude Code Skills

Custom skills live in `skills/` and are symlinked from `~/.claude/skills/` so they're discoverable by Claude Code globally.

**Installed skills:**

| Skill | Purpose |
|---|---|
| `commit` | Conventional Commits message generator for staged changes |
| `feature-start` | Start a new feature branch from an up-to-date main |
| `preprod` | Push current feature branch to the `preprod` branch for testing |
| `deploy` | Merge current feature branch into `main` and push |
| `quality` | Run all quality checks (PHPStan, PHP-CS-Fixer, tsc, ESLint, Prettier) |
| `ticket` | Deep-analyze a product ticket before coding (forces questions, prevents premature implementation) |
| `gap-analysis` | Audit a codebase against the guidelines, produce `docs/gap-analysis.md` |
| `sota-gap` | Measure how far the guidelines sit from the state of the art, web sources being the judge |
| `check-implementation` | Verify recent code against latest official docs of the techs used |
| `check-logs` | Prod health audit: CleverCloud logs + Messenger DB + Sentry, prioritized report |
| `unslop` | Strip AI writing tells from any text, French and English |
| `review-diff` | Pre-deploy review: diff vs ticket + guidelines, then runs `/quality` (+ `/check-implementation` if relevant) |
| `diagnosing-bugs` | Disciplined bug-fix loop: reproduce, hypothesis, instrument, root cause, regression test |

### The thread of a feature

The skills chain in a fixed order. Following it is what makes a feature
land coherent on the first pass:

```
/ticket  →  /feature-start  →  implement  →  /quality  →  /commit  →  /review-diff¹  →  /preprod  →  recette  →  /review-diff²  →  /deploy
   │                              │                                        │                                         │
   │                              │                                        └─ passage 1 : revue complète             └─ passage 2 : delta + /quality frais
   │                              └─ Feature playbook (symfony-guidelines §one-shot)
   └─ investigation + blast radius + assumed decisions

Bug ?  /diagnosing-bugs remplace /ticket, le reste du fil est identique.
```

- **`/ticket`** digests the PM ticket: investigates, maps the impact radius,
  decides everything the code can decide, surfaces only what truly blocks.
- **`/feature-start`** cuts the branch once the plan is clear : not before.
- **Implement** follows the Feature playbook and the guidelines;
  `/check-implementation` on demand when touching an API you don't use often.
- **`/quality`** before declaring done, **`/commit`** per coherent step,
  **`/preprod`** for the recette.
- **`/review-diff`** is the review a no-PR flow doesn't have: full **branch**
  diff (`main...HEAD`) vs the original ticket (everything asked, nothing more)
  and vs the guidelines, then it runs `/quality` and, only when the diff uses
  unfamiliar APIs, `/check-implementation`. It runs **twice**: pass 1 before
  `/preprod` (full review : don't make the PM test a diff the review would
  send back), pass 2 before `/deploy` (delta since pass 1 + fresh `/quality`,
  fast by design). Commits are save points, the merge is the irreversible act:
  nothing lands on main unreviewed, recette fixes included.
  **`/deploy`** merges to main and cleans up.

Out of band, the hygiene loop: `/check-logs` monthly on prod,
`/gap-analysis` after a big delivery, `/sota-gap` as tech watch.
Each feeds the next cycle's `/ticket` with a healthier baseline.

### Setup on a new machine

```bash
git clone git@github.com:alexandre-mace/dev-standards.git ~/dev/dev-standards
~/dev/dev-standards/skills/install.sh
```

The `install.sh` script is idempotent : safe to re-run when skills are added or renamed. It creates (or refreshes) symlinks from `~/.claude/skills/<name>/` to `~/dev/dev-standards/skills/<name>/`.

### Adding a new skill

1. Create `skills/<skill-name>/SKILL.md`
2. Run `./skills/install.sh` to register it
3. Commit + push

All machines that `git pull` and rerun `install.sh` will get the new skill.
