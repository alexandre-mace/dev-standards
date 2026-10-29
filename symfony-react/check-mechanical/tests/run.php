<?php

/*
 * The tests of check-mechanical.php, without Composer: `php tests/run.php`.
 *
 * Each rule has a folder in fixtures/: every file under refused/ must be refused by that
 * rule, every file under allowed/ must pass it. A rule that no longer sees anything
 * would otherwise turn green without a sound. fixtures/_exceptions/ holds the comments
 * that excuse an occurrence, and the cases below run the script in throwaway git
 * repositories: the hook, the CI, a full index.
 */

declare(strict_types=1);

const SCRIPT = __DIR__.'/../check-mechanical.php';
const FIXTURES = __DIR__.'/fixtures';

$failures = [];
$count = 0;

/**
 * The script, run outside any calling repository: under the pre-commit hook, GIT_DIR and
 * GIT_INDEX_FILE name the repository being committed, and the throwaway repositories'
 * git commands would write into it.
 *
 * @param list<string> $arguments
 *
 * @return array{int, string, string} exit code, standard output, error output
 */
function run(array $command, ?string $cwd = null): array
{
    $env = array_filter(getenv(), static fn (string $name): bool => !str_starts_with($name, 'GIT_'), \ARRAY_FILTER_USE_KEY);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $out, (string) $err];
}

/** @param list<string> $arguments */
function check(array $arguments, ?string $cwd = null): array
{
    return run(['php', SCRIPT, ...$arguments], $cwd);
}

function expect(bool $condition, string $case, string $detail = ''): void
{
    global $failures, $count;
    ++$count;
    if (!$condition) {
        $failures[] = $case.('' !== $detail ? "\n".preg_replace('/^/m', '    ', rtrim($detail)) : '');
    }
}

/** @return list<string> the files under the folder, relative to it, dotfiles included */
function filesUnder(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $files[] = substr($file->getPathname(), \strlen($dir) + 1);
    }
    sort($files);

    return $files;
}

function directory(): string
{
    $dir = sys_get_temp_dir().'/check-mechanical-'.bin2hex(random_bytes(6));
    mkdir($dir);
    register_shutdown_function(static fn () => run(['rm', '-rf', $dir]));

    return (string) realpath($dir);
}

function write(string $root, string $path, string $content, bool $append = false): void
{
    @mkdir(\dirname($root.'/'.$path), 0777, true);
    file_put_contents($root.'/'.$path, $content, $append ? \FILE_APPEND : 0);
}

function git(string $root, string ...$arguments): void
{
    [$code, , $err] = run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.test', '-c', 'commit.gpgsign=false', '-c', 'init.defaultBranch=main', ...$arguments], $root);
    if (0 !== $code) {
        throw new RuntimeException('git '.implode(' ', $arguments).": $err");
    }
}

/** A repository whose first commit holds an audit pass number, from before the rule. */
function repositoryWithAnOldPassNumber(): string
{
    $root = directory();
    write($root, 'src/A.php', "<?php\n\$a = 1;\n// Corrigé en 11e\x20passe.\n");
    git($root, 'init', '-q');
    git($root, 'add', '.');
    git($root, 'commit', '-qm', 'ancien');

    return $root;
}

// Every rule has its fixtures, and every fixture folder names a rule.
[, $listing] = check(['--rules']);
$rules = [];
foreach (explode("\n", trim($listing)) as $line) {
    [$id, $kind] = preg_split('/\s+/', $line, 3);
    $rules[$id] = $kind;
}
foreach (array_keys($rules) as $id) {
    expect(is_dir(FIXTURES."/$id/refused"), "la règle $id a des cas refusés dans fixtures/$id/refused");
}
foreach (glob(FIXTURES.'/*', \GLOB_ONLYDIR) ?: [] as $dir) {
    $id = basename($dir);
    if (!str_starts_with($id, '_')) {
        expect(isset($rules[$id]), "fixtures/$id nomme une règle de check-mechanical.php");
    }
}

// Each rule refuses its cases and lets the allowed forms through.
foreach (array_keys($rules) as $id) {
    foreach (['refused', 'allowed'] as $kind) {
        $root = FIXTURES."/$id/$kind";
        if (!is_dir($root)) {
            continue;
        }
        foreach (filesUnder($root) as $path) {
            [$code, , $err] = check(['--root', $root, $path]);
            $hit = str_contains($err, "$path:") && str_contains($err, "[$id]");
            if ('refused' === $kind) {
                expect(1 === $code && $hit, "$id refuse $path", $err);
            } else {
                expect(!str_contains($err, "[$id]"), "$id laisse passer $path", $err);
            }
        }
    }
}

// The exceptions written in the code.
$exceptions = FIXTURES.'/_exceptions';
foreach (filesUnder("$exceptions/covered") as $path) {
    [$code, , $err] = check(['--root', "$exceptions/covered", $path]);
    expect(0 === $code, "une exception couvre son occurrence : $path", $err);
}
foreach ([
    'stale' => 'exception périmée',
    'unknown' => "exception d'une règle inconnue : label-natf",
    'reasonless' => 'exception sans raison',
    'wrongline' => 'exception périmée',
] as $case => $message) {
    foreach (filesUnder("$exceptions/$case") as $path) {
        [$code, , $err] = check(['--root', "$exceptions/$case", $path]);
        expect(1 === $code && str_contains($err, "$path:") && str_contains($err, $message), "$case : $path dit « $message »", $err);
    }
}
[, , $err] = check(['--root', "$exceptions/wrongline", 'assets/WrongLine.tsx']);
expect(str_contains($err, 'assets/WrongLine.tsx:8: [label-natif]'), "une exception ne couvre que la ligne qui la suit", $err);
[, , $err] = check(['--root', "$exceptions/unknown", 'assets/Unknown.tsx']);
expect(str_contains($err, '[label-natif]'), "une exception d'une règle inconnue n'excuse rien", $err);

// The help shows every mode.
[$code, $out] = check(['--help']);
expect(0 === $code && str_contains($out, '--all [--base R]') && str_contains($out, 'origin/main by default') && str_contains($out, '[--root D] F...'), "l'aide montre chaque mode", $out);

// What every project shares is skipped by the script itself.
$root = directory();
foreach (['docs/gap-analysis.md', 'data/fermage/source.json', 'assets/components/ui/button.tsx', 'assets/lib/api/types.gen.ts', 'vendor/a/b.php', 'node_modules/a/b.js', 'pnpm-lock.yaml', 'composer.lock', 'openapi.yaml'] as $path) {
    write($root, $path, "A \u{2014} B\n");
}
[$code, , $err] = check(['--root', $root, ...filesUnder($root)]);
expect(0 === $code, 'le relevé, les données de data/, les composants du registre, le code généré et les dépendances ne sont pas lus', $err);

// Of assets/components/ui/, only the registry files are skipped: composites/, the tests and
// a project's own data-table.tsx and multi-select.tsx are read.
$root = directory();
foreach (['assets/components/ui/composites/Champ.tsx', 'assets/components/ui/data-table.tsx', 'assets/components/ui/multi-select.tsx', 'assets/components/ui/FeveSlider.tsx'] as $path) {
    write($root, $path, "export const a = 'A \u{2014} B';\n");
}
foreach (filesUnder($root) as $path) {
    [, , $err] = check(['--root', $root, $path]);
    expect(str_contains($err, "$path:1: [tiret-long]"), "$path n'est pas un composant du registre : il est lu", $err);
}

// A guide linked from another repository counts as present, even when its target is not
// checked out next to it: a throwaway worktree would otherwise refuse every reference.
$root = directory();
write($root, 'src/Guide.php', "<?php\n\ndeclare(strict_types=1);\n\n// cf. docs/guide.md\n");
mkdir($root.'/docs');
symlink('../../nowhere/guide.md', $root.'/docs/guide.md');
[, , $err] = check(['--root', $root, 'src/Guide.php']);
expect(!str_contains($err, '[document-introuvable]'), 'un guide lié par un lien symbolique, même cassé, est présent', $err);

// A "fichier" rule may read only the added lines of some paths: target-blank and
// trait-union in PHP, where the projects still hold old occurrences, while a TSX file is
// read whole.
$root = repositoryWithAnOldPassNumber();
write($root, 'src/Link.php', "<?php\n\ndeclare(strict_types=1);\n\n\$a = '<a href=\"/x\" target=\"_blank\">x</a>';\n");
write($root, 'assets/Old.tsx', "export const A = () => <a href='/x' target='_blank'>x</a>;\n");
git($root, 'add', '.');
git($root, 'commit', '-qm', 'liens');
git($root, 'branch', 'base');
write($root, 'src/Link.php', "\$b = '<a href=\"/y\" target=\"_blank\">y</a>';\n", append: true);
git($root, 'add', '.');
git($root, 'commit', '-qm', 'nouveau lien');
[$code, , $err] = check(['--all', '--base', 'base'], $root);
expect(
    1 === $code && str_contains($err, 'src/Link.php:6: [target-blank]') && !str_contains($err, 'src/Link.php:5:') && str_contains($err, 'assets/Old.tsx:1: [target-blank]'),
    'target-blank lit en PHP les seules lignes ajoutées, et tout un fichier TSX',
    $err,
);

// The hook judges the staged version, and only its new lines for an "ajout" rule: an
// old pass number of the same file passes, the new one does not, even under a non-ASCII
// name, which git would quote.
$root = repositoryWithAnOldPassNumber();
write($root, 'src/A.php', "// Corrigé en 12e\x20passe.\n", append: true);
write($root, 'docs/Équipe.md', "Vu en 13e\x20passe.\n");
git($root, 'add', '.');
// A fix left out of the index does not count: the commit leaves without it.
write($root, 'docs/Équipe.md', "Rien.\n");
[$code, , $err] = check([], $root);
expect(1 === $code && str_contains($err, 'src/A.php:4:') && !str_contains($err, 'src/A.php:3:') && str_contains($err, 'docs/Équipe.md:1:'), 'le hook juge les seuls ajouts indexés', $err);

// A modified PHP file keeps its old form; a created one owes strict_types and a closed class.
$root = repositoryWithAnOldPassNumber();
write($root, 'src/A.php', "\$b = 2;\n", append: true);
write($root, 'src/B.php', "<?php\n\nclass B\n{\n}\n");
git($root, 'add', '.');
[$code, , $err] = check([], $root);
expect(
    1 === $code && !str_contains($err, 'src/A.php') && str_contains($err, 'src/B.php:1: [strict-types]') && str_contains($err, 'src/B.php:3: [classe-finale]'),
    'le hook demande strict_types et une classe fermée au seul fichier créé',
    $err,
);

// The CI counts the additions from the merge base with the given branch.
$root = repositoryWithAnOldPassNumber();
git($root, 'branch', 'base');
write($root, 'src/A.php', "// Corrigé en 12e\x20passe.\n", append: true);
write($root, 'src/B.php', "<?php\n\nfinal class B\n{\n}\n");
git($root, 'add', '.');
git($root, 'commit', '-qm', 'neuf');
[$code, , $err] = check(['--all', '--base', 'base'], $root);
expect(
    1 === $code && str_contains($err, 'src/A.php:4:') && !str_contains($err, 'src/A.php:3:') && !str_contains($err, 'src/A.php:1:') && str_contains($err, 'src/B.php:1: [strict-types]'),
    'la CI compte les ajouts depuis la base de fusion',
    $err,
);

// A file rule reads every line in the CI, old ones included, and an exception excuses one.
$root = repositoryWithAnOldPassNumber();
write($root, 'src/C.php', "<?php\n\ndeclare(strict_types=1);\n\n// check-mechanical-ignore tiret-long: cité tel quel\n\$a = 'un \u{2014} deux';\n\$b = 'trois \u{2014} quatre';\n");
git($root, 'add', '.');
git($root, 'commit', '-qm', 'tirets');
[$code, , $err] = check(['--all', '--base', 'HEAD'], $root);
expect(1 === $code && str_contains($err, 'src/C.php:7: [tiret-long]') && !str_contains($err, 'src/C.php:6:'), 'une règle « fichier » lit les lignes anciennes, et une exception en excuse une', $err);

// A merge: only the lines new to both parents are ours.
$root = repositoryWithAnOldPassNumber();
git($root, 'checkout', '-qb', 'other');
write($root, 'src/Other.php', "<?php\n// Vu en 14e\x20passe.\n");
git($root, 'add', '.');
git($root, 'commit', '-qm', 'autre');
git($root, 'checkout', '-q', 'main');
write($root, 'src/Mine.php', "<?php\n\ndeclare(strict_types=1);\n");
git($root, 'add', '.');
git($root, 'commit', '-qm', 'mien');
git($root, 'merge', '-q', '--no-commit', '--no-ff', 'other');
[$code, , $err] = check([], $root);
expect(!str_contains($err, 'src/Other.php'), "une fusion ne compte pas comme siennes les lignes de l'autre branche", $err);

// The hook used to write every request to `git cat-file --batch` before reading an
// answer: on a merge of over 3,000 files, git, stuck on its full output, stopped
// reading them, and the commit waited forever.
$root = directory();
for ($i = 0; $i < 3500; ++$i) {
    write($root, \sprintf('assets/components/a-folder-with-a-rather-long-name/file-%04d.ts', $i), str_repeat("export const value = 'texte sans défaut';\n", 40));
}
git($root, 'init', '-q');
git($root, 'add', '.');
$started = microtime(true);
[$code, , $err] = check(['--root', $root], $root);
expect(0 === $code && microtime(true) - $started < 30, 'un index plein se lit sans interblocage', $err);

if ([] !== $failures) {
    fwrite(\STDERR, implode("\n\n", $failures)."\n\n".\count($failures)." échec(s) sur $count.\n");
    exit(1);
}
echo "$count vérifications, toutes vertes.\n";
