# check-mechanical

The mechanical rules of the Symfony + React stack, in one script every project of the
stack calls: each anti-pattern that reduces to a pattern in the text is refused at commit
instead of being found again by every `/gap-code` pass. The why is in
`symfony-guidelines.md`, section "Mechanical rules: refused at commit, not found at audit".
PHP 8.1 or later, no dependency.

## In a project

`bin/check-mechanical` only calls this script, found next to the project's main checkout,
where the guides of `docs/` point too: the projects sit next to each other under
`~/dev/<organisation>/`, and `dev-standards` under `~/dev/`. The main checkout comes from
`git rev-parse --git-common-dir`, not from the script's own path: a worktree that does not
sit next to the project (`.claude/worktrees/<name>/`, where Claude Code puts them) would
otherwise look for `dev-standards` inside `.claude/`, and the hook would exit in error.
`DEV_STANDARDS` points to another copy, a worktree of this repository for instance.

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

$commonDir = trim((string) shell_exec('git -C '.escapeshellarg(__DIR__).' rev-parse --path-format=absolute --git-common-dir 2>/dev/null'));
$checkout = '' !== $commonDir ? \dirname($commonDir) : __DIR__.'/..';
$devStandards = getenv('DEV_STANDARDS') ?: $checkout.'/../../dev-standards';
$script = $devStandards.'/symfony-react/check-mechanical/check-mechanical.php';
if (!is_file($script)) {
    fwrite(\STDERR, "check-mechanical introuvable : $script\n");
    exit(2);
}

passthru(implode(' ', array_map(escapeshellarg(...), [\PHP_BINARY, $script, ...array_slice($argv, 1)])), $code);
exit($code);
```

- The pre-commit hook runs `bin/check-mechanical`: the staged files, in their staged version.
- `bin/ci-local` runs `bin/check-mechanical --all`: every tracked file,
  the added lines counted from the merge base with `origin/main` (`--base` for another).
- A hosted CI that runs it clones this public repository next to the project first:
  `git clone --depth 1 https://github.com/alexandre-mace/dev-standards.git ../../dev-standards`.

`check-mechanical.php --rules` lists the rules, their kind and their message.

## Two kinds of rule

A **fichier** rule reads every line of the files it sees. An **ajout** rule reads only the
lines added since the merge base (the staged lines, in the hook), so it refuses new
occurrences while a project still holds old ones. A new rule starts as **ajout**, and
becomes **fichier** once every project has cleaned its stock. `strict-types` and
`classe-finale` stay **ajout** on purpose: they report on the first line and on the class
declaration, which only a new file adds, and judge new files only.

| Kind | Rules |
|---|---|
| fichier | `tiret-long`, `point-median`, `faute`, `trait-union`, `entite-html`, `diacritique-invisible`, `identifiant-constat`, `classe-inexistante`, `libelle-anglais`, `label-natif`, `case-native`, `main-ilot`, `boite-native`, `balise-en-chaine`, `pourcentage-colle`, `target-blank`, `hote-tiers`, `cdn-webflow`, `emoji-gabarit`, `emoji-stimulus`, `url-feve`, `contact-feve`, `chemin-relatif`, `choice-litteral`, `id-sans-borne`, `docblock-double`, `methode-depot`, `sommeil-test`, `gitignore-src` |
| ajout | `identifiant-audit`, `faute-recente`, `couleur-brute`, `blanc-noir`, `rgba`, `icone-racine`, `formulaire-twig`, `strict-types`, `classe-finale`, `commentaire-anglais`, `guillemets-anglais`, `guillemets-droits`, `capitale-sans-accent`, `ponctuation-detachee`, `anglicisme`, `points-suspension`, `label-orphelin`, `main-gabarit`, `contour-sans-anneau`, `couleur-litterale`, `contenu-attenue`, `hauteur-vh`, `chart-gabarit`, `script-en-ligne`, `style-en-ligne`, `classe-en-morceaux`, `map-query-statut`, `ancre-sans-d`, `find-all`, `accord-parenthese`, `guillemets-message`, `non-applicable`, `balise-console`, `document-introuvable` |

A **fichier** rule can read only the added lines of some paths, where the projects still
hold old occurrences (`addedOnly`, shown by `--rules`): `target-blank` and `trait-union` in
PHP, where two projects keep links with `rel="noopener"` alone and CLI messages
or third-party labels with a spaced hyphen; `label-natif` in `assets/components/ui/`, whose
composites used to be skipped with the registry.

`rgba` and `commentaire-anglais` become **fichier** once every project has cleaned them.
`diacritique-invisible` became **fichier** on 29/10/2026, clean on the three projects.
`couleur-brute`, `blanc-noir`, `document-introuvable`, `balise-console` and `faute-recente`
(the typos one project's script refused) stay **ajout** while a project's main branch still
holds an occurrence, even one its pending cleanup removes: a **fichier** rule that fails on a
project's main turns every hook, local CI and nightly run of that project red. A rule
becomes **fichier** once each project's cleanup is in production, checked with
`check-mechanical.php --all` on each project's `origin/main`.

Twenty-two rules came, the same day, from the convention checks the projects kept each on
their side: a grep script, Vitest guards, PHPUnit tests reading files with a regex, ESLint
selectors that only matched a literal against a pattern. Five were clean on every project
and are **fichier**: `boite-native`, `balise-en-chaine`, `pourcentage-colle`,
`methode-depot`, `sommeil-test`. The others, in the **ajout** row, were refused in full by one project
and held by another, up to 196 occurrences of `couleur-litterale` on one of them: they judge
the new lines, and become **fichier** once the stocks are gone. `label-orphelin` reads the
templates as well. What needs the syntax tree (a call's structure, a scope, a type) stays in
the project's ESLint or PHPStan rules.

`couleur-litterale`, `contenu-attenue` and `style-en-ligne` leave the `emails/` and `pdf/`
folders of `templates/` out, at any depth: a mail client and a PDF renderer read neither the application's
stylesheet nor its tokens, and inline styles are how those templates carry their colours.

`commentaire-anglais` reads a comment line by line, and calls a line English when it
holds two English function words or more, no French one and no accented letter, among
four words or more, once its code spans, URLs, double-quoted citations, phpdoc types and
variables are set aside. It is a measure, not a parser: on one project's `assets/` and
`templates/`, it found 493 lines before their translation, and the 30 sampled were all real
English comments. A third-party text quoted as is takes an exception.

A rule that holds for one project only stays out of the script. `cta-je` (a button in
« Je ») stays in the design system of the project that wants it: another asks for the
first person only when the button commits the user (« J'investis »).

## Exceptions

An exception is a comment in the code, with its reason, never a list in a file of its own:

```
check-mechanical-ignore <rule>: <reason>
```

On the line above the occurrence, it covers that line; several can stack above one line.
In the first three lines of a file (and the exceptions stacked right under them), it
covers the whole file: the prompts sent to a language model, a test that quotes the
characters a rule refuses. Any comment syntax works (`//`, `#`, `{# #}`, `{/* */}`,
`<!-- -->`); in Markdown, one quoted in code is an example, not an exception. The line of
an exception is not judged, so its reason may name what it excuses.

An exception without a reason, naming an unknown rule, or covering no occurrence any more
is refused too: it goes away with the code it covered.

The script skips by itself what every project shares: `docs/gap-analysis.md`, which quotes
the deviations, `data/`, which holds imported datasets and sources quoted as they are
(prefectoral orders, official texts) rather than the project's prose, the shadcn registry
components (the kebab-case files at the top of `assets/components/ui/`; `composites/`, the
tests and a project's own `data-table.tsx` and `multi-select.tsx` are read), the generated
code (`assets/lib/api/`, `openapi.yaml`, `public/build/`), `vendor/`, `node_modules/` and
the lockfiles. No other folder is out of reach, old code included.

## Adding a rule

A finding of `/gap-code` or `/review-diff` that reduces to a pattern becomes a rule here, in
the pull request that fixes it: its entry in `rules()`, a folder `tests/fixtures/<rule>/`
with the files it refuses under `refused/` and the forms it lets through under `allowed/`
(each at the path it would have in a project), then `php tests/run.php`. The runner also
covers the exceptions (`tests/fixtures/_exceptions/`), the hook, the CI and a full index,
in throwaway git repositories.
