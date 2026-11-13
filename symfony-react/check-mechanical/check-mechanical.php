#!/usr/bin/env php
<?php

/*
 * The mechanical rules of the Symfony + React stack, shared by every project of the
 * stack: each anti-pattern that reduces to a pattern in the text, refused at commit
 * rather than found again by every audit. A project's bin/check-mechanical only calls
 * this script (README.md, next to it).
 *
 *   check-mechanical.php                    staged files, in their staged version (hook)
 *   check-mechanical.php --all [--base R]   every tracked file; added lines counted from
 *                                           the merge base with R (origin/main by default)
 *   check-mechanical.php [--root D] F...    the given files, every line counted as added
 *                                           (the tests of this script)
 *   check-mechanical.php --rules            the rules, their kind and their message
 *
 * A "file" rule reads every line of the files it sees; an "added" rule reads only the
 * lines added since the merge base, so it refuses new occurrences while a project still
 * holds old ones, except strict-types and classe-finale, which judge a renamed or copied
 * file whole. An exception lives in the code, on the line above the occurrence or
 * in the first three lines of a file for the whole file (several stacked from there):
 *
 *   check-mechanical-ignore <rule>: <reason>
 *
 * An exception without a reason, naming an unknown rule, or covering no occurrence is
 * refused too. Output: one line per finding, exit code 1 if there is one, 2 on misuse.
 */

declare(strict_types=1);

/**
 * Never read: generated, locked, vendored, or quoting on purpose: the deviations of
 * docs/gap-analysis.md, and data/, imported datasets and sources quoted as they are
 * (prefectoral orders, official texts), not the project's own prose. Of
 * assets/components/ui/, only the shadcn registry files, named in kebab case at its top:
 * composites/, the tests and a project's own components (data-table.tsx, multi-select.tsx,
 * which the registry does not ship) are read. config/reference.php is written by Symfony
 * itself (« This file is auto-generated »), rewritten by a bundle update: Foundry 2.15
 * changed one of its lines and the English-comment rule refused the update.
 */
const SKIPPED = '#^(docs/gap-analysis\.md$|config/reference\.php$|data/|assets/components/ui/(?!(?:data-table|multi-select)\.tsx$)[a-z0-9-]+\.tsx$|assets/lib/api/|public/build/|vendor/|node_modules/)|(^|/)(composer\.lock|symfony\.lock|pnpm-lock\.yaml|package-lock\.json|yarn\.lock|openapi\.yaml)$#';

/** An exception within these first lines of a file covers the whole file. */
const FILE_HEADER_LINES = 3;

const DIRECTIVE = 'check-mechanical-ignore';

/** A hyphen between spaces, a word or a number on each side: it stands for a dash. */
const HYPHEN_AS_DASH = '[\p{L}\d][\p{L}\d.,)»%€]* - [\p{L}\d«(]';

/*
 * Une espace ordinaire avant « % » : après un nombre écrit, une expression qui se ferme
 * (`{{ taux }} %`, `${taux} %`) ou en tête d'une chaîne (`~ ' %'`). Le nombre ne termine pas
 * un identifiant (`x1 % 2`), et un `%}` ferme une balise Twig (`{% if a > 5 %}`) : le modulo
 * d'un littéral (`1000 % 60`), absent des projets, porterait son exception.
 */
const PERCENT_AFTER_SPACE = '/(?:(?<![\w$.])\d+(?:[.,]\d+)?|\}\}?|[\'"`]) %(?![}\w])/u';

/*
 * An emoji, as the rules about emojis read it. Not the marks of a legal text (©, ®, ™),
 * nor the ballot boxes ☐ ☑ ☒ that a PDF form draws as its checkboxes: those are symbols.
 * The ballot boxes are excluded by name because the PCRE versions disagree on them: the
 * 10.49 of PHP 8.5 leaves ☐ and ☒ out of Extended_Pictographic, the one of the GitHub
 * runners puts them in, and a rule must give the same answer on every machine.
 */
const EMOJI = '/(?![\x{00A9}\x{00AE}\x{2122}\x{2610}-\x{2612}])\p{Extended_Pictographic}/u';

$argv0 = array_shift($argv);
$mode = 'staged';
$root = null;
$base = 'origin/main';
$files = [];
while ([] !== $argv) {
    $arg = array_shift($argv);
    match ($arg) {
        '--all' => $mode = 'all',
        '--base' => $base = (string) array_shift($argv),
        '--root' => $root = (string) array_shift($argv),
        '--rules' => listRules(),
        '-h', '--help' => usage(0),
        default => str_starts_with($arg, '-') ? usage(2) : $files[] = $arg,
    };
}
if ([] !== $files) {
    $mode = 'files';
}

$root ??= trim((string) shell_exec('git rev-parse --show-toplevel 2>/dev/null'));
if ('' === $root || !is_dir($root)) {
    fwrite(\STDERR, "Pas dans un dépôt git.\n");
    exit(2);
}
chdir($root);

$rules = rules();
$extensions = array_unique(array_merge(...array_values(array_map(static fn (array $rule): array => $rule['ext'], $rules))));
$scanned = static fn (string $path): bool => !preg_match(SKIPPED, $path) && \in_array(extension($path), $extensions, true);

/** @var array<string, string> $contents path => content */
$contents = [];
/** @var array<string, array<int, true>>|null $added path => added lines; null: all of them */
$added = null;
/** @var array<string, true> $moved les fichiers renommés ou copiés, sous leur nouveau nom */
$moved = [];
$notice = null;

if ('files' === $mode) {
    foreach (array_filter($files, $scanned) as $path) {
        if (is_file($path)) {
            $contents[$path] = (string) file_get_contents($path);
        }
    }
} elseif ('staged' === $mode) {
    $paths = array_values(array_filter(explode("\0", git('diff --cached --name-only --diff-filter=ACMR -z')), $scanned));
    $contents = staged($paths);
    $added = addedLines(git('diff --cached -M -U0 --no-color --no-ext-diff --diff-filter=ACMR'));
    $moved = pathsOf(git('diff --cached -M --name-status -z --diff-filter=RC'));
    // A merge: a line is ours only if it is new to both parents, otherwise the lines
    // brought by the other branch would count as added here.
    $mergeHead = trim(git('rev-parse -q --verify MERGE_HEAD', quiet: true));
    if ('' !== $mergeHead) {
        $fromOther = addedLines(git('diff --cached -M -U0 --no-color --no-ext-diff --diff-filter=ACMR '.escapeshellarg($mergeHead)));
        foreach ($added as $path => $lines) {
            $added[$path] = array_intersect_key($lines, $fromOther[$path] ?? []);
        }
        // De même, un fichier n'est renommé par nous que s'il est neuf pour l'autre parent
        // aussi : celui que l'autre branche a renommé y porte déjà ce nom.
        $moved = array_intersect_key($moved, pathsOf(git('diff --cached -M --name-status -z --diff-filter=ACR '.escapeshellarg($mergeHead))));
    }
} else {
    foreach (array_filter(explode("\0", git('ls-files -z')), $scanned) as $path) {
        // A symbolic link (the guides in docs/ point outside the repository) is not ours.
        if (is_file($path) && !is_link($path)) {
            $contents[$path] = (string) file_get_contents($path);
        }
    }
    $mergeBase = trim(git('merge-base HEAD '.escapeshellarg($base), quiet: true));
    if ('' === $mergeBase) {
        $added = [];
        $notice = "Base $base introuvable : les règles « ajout » ne sont pas jouées.";
    } else {
        $added = addedLines(git('diff -M -U0 --no-color --no-ext-diff '.escapeshellarg($mergeBase)));
        $moved = pathsOf(git('diff -M --name-status -z --diff-filter=RC '.escapeshellarg($mergeBase)));
    }
}

$found = [];
foreach ($contents as $path => $content) {
    if (str_contains($content, "\0")) {
        continue;
    }
    [$directives, $malformed, $directiveLines] = directives($content, $path, $rules);
    foreach ($malformed as [$line, $problem]) {
        $found[] = \sprintf('%s:%d: [%s] %s', $path, $line, DIRECTIVE, $problem);
    }
    foreach ($rules as $id => $rule) {
        $hits = applies($rule, $path) ? $rule['find']($content, $path) : [];
        foreach ($hits as [$line, $excerpt]) {
            // The reason of an exception may name what it excuses.
            if (isset($directiveLines[$line])) {
                continue;
            }
            $covered = false;
            foreach ($directives as $i => $directive) {
                if ($directive['rule'] === $id && ($directive['file'] || $directive['target'] === $line)) {
                    $directives[$i]['used'] = $covered = true;
                }
            }
            // Un renommage pur n'ajoute aucune ligne : une règle de naissance juge alors le
            // fichier entier, que le nouveau nom fait naître.
            $born = isset($rule['wholeWhenMoved'], $moved[$path]);
            if ($covered || ('ajout' === kind($rule, $path) && null !== $added && !isset($added[$path][$line]) && !$born)) {
                continue;
            }
            $found[] = \sprintf("%s:%d: [%s] %s\n    %s", $path, $line, $id, $rule['message'], mb_strimwidth(trim($excerpt), 0, 160, '…'));
        }
    }
    foreach ($directives as $directive) {
        if (!$directive['used']) {
            $found[] = \sprintf(
                '%s:%d: [%s] exception périmée : la règle %s ne relève rien %s, retirer le commentaire',
                $path,
                $directive['line'],
                DIRECTIVE,
                $directive['rule'],
                $directive['file'] ? 'dans ce fichier' : 'sur la ligne suivante',
            );
        }
    }
}

if (null !== $notice) {
    fwrite(\STDERR, $notice."\n");
}
if ([] === $found) {
    exit(0);
}
fwrite(\STDERR, implode("\n", $found)."\n\n".\count($found)." écart(s) mécanique(s). Les règles : check-mechanical.php --rules ; une exception justifiée : `check-mechanical-ignore <règle>: <raison>` sur la ligne au-dessus.\n");
exit(1);

/** The header comment, its modes and output included. */
function usage(int $code): never
{
    $lines = [];
    foreach (array_slice(file(__FILE__) ?: [], 4) as $line) {
        if (str_starts_with($line, ' */')) {
            break;
        }
        $lines[] = preg_replace('/^ \* ?/', '', $line) ?? $line;
    }
    fwrite(0 === $code ? \STDOUT : \STDERR, implode('', $lines));
    exit($code);
}

function listRules(): never
{
    foreach (rules() as $id => $rule) {
        $partly = isset($rule['addedOnly']) ? ' (lignes ajoutées seules : '.$rule['addedOnlyLabel'].')' : '';
        $partly .= isset($rule['wholeWhenMoved']) ? ' (fichier renommé ou copié : jugé en entier)' : '';
        echo \sprintf("%-20s %-8s %s%s\n", $id, $rule['kind'], $rule['message'], $partly);
    }
    exit(0);
}

/** A non-ASCII path comes out as is, otherwise git quotes it, in octal. */
function git(string $args, bool $quiet = false): string
{
    return (string) shell_exec('git -c core.quotepath=off '.$args.($quiet ? ' 2>/dev/null' : ''));
}

/** The extension; a file without one (Makefile, .husky/pre-commit) gives '', a `.gitignore` 'gitignore'. */
function extension(string $path): string
{
    return pathinfo($path, \PATHINFO_EXTENSION);
}

/**
 * The kind of the rule for this file: a "file" rule may read only the added lines of some
 * paths (`addedOnly`), where projects still hold old occurrences.
 *
 * @param array{kind: string, addedOnly?: string} $rule
 */
function kind(array $rule, string $path): string
{
    return isset($rule['addedOnly']) && preg_match($rule['addedOnly'], $path) ? 'ajout' : $rule['kind'];
}

/** @param array{ext: list<string>, paths: string, except?: string} $rule */
function applies(array $rule, string $path): bool
{
    return \in_array(extension($path), $rule['ext'], true)
        && preg_match($rule['paths'], $path)
        && !(isset($rule['except']) && preg_match($rule['except'], $path));
}

/**
 * The exceptions written in the file. One in the first lines covers the whole file, and so
 * do the ones stacked right under it; any other covers the next line that is not itself
 * an exception, so several can stack above one line. In Markdown, an exception quoted in code is an example, not one.
 *
 * @param array<string, mixed> $rules
 *
 * @return array{list<array{rule: string, line: int, target: int, file: bool, used: bool}>, list<array{int, string}>, array<int, true>}
 */
function directives(string $content, string $path, array $rules): array
{
    if (!str_contains($content, DIRECTIVE)) {
        return [[], [], []];
    }
    $searched = 'md' === extension($path) ? withoutCode($content, $path) : $content;
    $lines = explode("\n", $searched);
    $directives = [];
    $malformed = [];
    $pending = [];
    $directiveLines = [];
    // The header: the exceptions from the first lines on, one per line, until a line that is not one.
    $header = true;
    foreach ($lines as $i => $line) {
        $number = $i + 1;
        if (!preg_match('/'.DIRECTIVE.'(?![\w-])(.*)$/', $line, $m)) {
            foreach ($pending as $directive) {
                $directives[] = ['target' => $number] + $directive;
            }
            $pending = [];
            $header = $header && $number < FILE_HEADER_LINES;
            continue;
        }
        $directiveLines[$number] = true;
        // The reason runs to the end of the comment, its closing marks left out.
        $rest = trim((string) preg_replace('/\s*(?:\*\/\s*\}?|#\}|-->|%\})\s*$/', '', $m[1]));
        if (!preg_match('/^([a-z][a-z0-9-]*)\s*(?::\s*(.*))?$/', $rest, $parts)) {
            $malformed[] = [$number, 'exception illisible : `'.DIRECTIVE.' <règle>: <raison>`'];
            continue;
        }
        $rule = $parts[1];
        if (!isset($rules[$rule])) {
            $malformed[] = [$number, "exception d'une règle inconnue : $rule (check-mechanical.php --rules)"];
            continue;
        }
        if ('' === trim($parts[2] ?? '')) {
            $malformed[] = [$number, "exception sans raison : `".DIRECTIVE." $rule: <raison>`"];
            continue;
        }
        $directive = ['rule' => $rule, 'line' => $number, 'target' => 0, 'file' => $header, 'used' => false];
        if ($directive['file']) {
            $directives[] = $directive;
        } else {
            $pending[] = $directive;
        }
    }
    foreach ($pending as $directive) {
        $malformed[] = [$directive['line'], 'exception en fin de fichier : elle ne couvre aucune ligne'];
    }

    return [$directives, $malformed, $directiveLines];
}

/**
 * The staged version of each file, in a single git call.
 *
 * @param list<string> $paths
 *
 * @return array<string, string>
 */
function staged(array $paths): array
{
    if ([] === $paths) {
        return [];
    }
    $process = proc_open(['git', 'cat-file', '--batch'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
    if (!\is_resource($process)) {
        fwrite(\STDERR, "git cat-file impossible.\n");
        exit(2);
    }
    // One request, then its answer: writing every request first fills git's output
    // pipe, git stops reading, and the hook waits forever (a merge of 3,000 files).
    $out = [];
    foreach ($paths as $path) {
        fwrite($pipes[0], ':'.$path."\n");
        fflush($pipes[0]);
        $header = (string) fgets($pipes[1]);
        if (!preg_match('/ blob (\d+)$/', trim($header), $m)) {
            continue;
        }
        $size = (int) $m[1];
        $data = '';
        while (\strlen($data) < $size && !feof($pipes[1])) {
            $data .= (string) fread($pipes[1], $size - \strlen($data));
        }
        fgets($pipes[1]);
        $out[$path] = $data;
    }
    fclose($pipes[0]);
    fclose($pipes[1]);
    proc_close($process);

    return $out;
}

/**
 * Les chemins d'un `git diff --name-status -z`, sous leur nouveau nom : un renommage ou une
 * copie donne l'ancien puis le nouveau.
 *
 * @return array<string, true>
 */
function pathsOf(string $nameStatus): array
{
    $fields = explode("\0", rtrim($nameStatus, "\0"));
    $paths = [];
    for ($i = 0, $n = \count($fields); $i < $n && '' !== $fields[$i]; ++$i) {
        if (\in_array($fields[$i][0], ['R', 'C'], true)) {
            ++$i;
        }
        if (isset($fields[$i + 1])) {
            $paths[$fields[++$i]] = true;
        }
    }

    return $paths;
}

/** @return array<string, array<int, true>> */
function addedLines(string $diff): array
{
    $added = [];
    $path = null;
    foreach (explode("\n", $diff) as $line) {
        if (str_starts_with($line, '+++ ')) {
            $path = str_starts_with($line, '+++ b/') ? substr($line, 6) : null;
        } elseif (null !== $path && preg_match('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/', $line, $m)) {
            $start = (int) $m[1];
            for ($i = 0, $n = (int) ($m[2] ?? 1); $i < $n; ++$i) {
                $added[$path][$start + $i] = true;
            }
        }
    }

    return $added;
}

/**
 * Each match of the pattern, with its line number and the whole line. `$searched` is the
 * content with the ignored zones blanked, of the same length: the offsets agree.
 *
 * @return list<array{int, string}>
 */
function matches(string $pattern, string $content, ?string $searched = null): array
{
    $searched ??= $content;
    if (!preg_match_all($pattern, $searched, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $lines = explode("\n", $content);
    $out = [];
    $seen = [];
    foreach ($all[0] as [, $offset]) {
        $line = substr_count($searched, "\n", 0, $offset) + 1;
        if (!isset($seen[$line])) {
            $seen[$line] = true;
            $out[] = [$line, $lines[$line - 1] ?? ''];
        }
    }

    return $out;
}

/** The content, each byte of the given zones replaced by a space (line breaks kept). */
function blank(string $content, string $pattern, string $path = 'un contenu sans chemin'): string
{
    return regexResult(preg_replace_callback($pattern, static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]), $content), $path);
}

/**
 * Le résultat d'une regex, ou l'arrêt du script : un `null` (pile du JIT, limite de
 * retour arrière) rendrait sinon le fichier vide, donc invisible à toutes les règles.
 */
function regexResult(?string $result, string $path): string
{
    if (null === $result) {
        fwrite(\STDERR, sprintf("Regex en échec sur %s : %s.\n", $path, preg_last_error_msg()));
        exit(2);
    }

    return $result;
}

/**
 * The content without its comments, by language. PHP is read by its tokenizer; a script
 * in one pass from left to right, so a `//` that contains `/*` and a string that contains
 * `/*` (a glob, an XPath) open no block. `//` counts only at the start of a line or after
 * a space: the one of a URL (`https://`) follows a colon.
 */
function withoutComments(string $content, string $path): string
{
    return match (extension($path)) {
        'php' => withoutPhpComments($content),
        // Quantificateurs possessifs : une chaîne longue ne remplit plus la pile du JIT.
        'ts', 'tsx', 'js', 'mjs' => regexResult(preg_replace_callback(
            '#(?<s>\'(?:[^\'\\\\\n]++|\\\\.)*+\'|"(?:[^"\\\\\n]++|\\\\.)*+"|`(?:[^`\\\\]++|\\\\.)*+`)|(?<c>/\*.*?\*/|(?<=^|\s)//[^\n]*)#ms',
            static fn (array $m): string => '' !== ($m['c'] ?? '') ? (string) preg_replace('/[^\n]/', ' ', $m[0]) : $m[0],
            $content,
        ), $path),
        'twig' => blank($content, '/\{#.*?#\}|<!--.*?-->/s', $path),
        'yaml', 'yml', 'neon', 'sh', '' => blank($content, '/(?<=^|\s)#.*$/m', $path),
        'md' => withoutCode($content, $path),
        default => $content,
    };
}

/** PHP without its comments, read by the tokenizer: a string never opens one. */
function withoutPhpComments(string $content): string
{
    $out = '';
    foreach (token_get_all($content) as $token) {
        if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
            // A `//` or `#` comment holds its newline: kept, so the lines stay in place.
            $out .= (string) preg_replace('/[^\n]/', ' ', $token[1]);
        } else {
            $out .= \is_array($token) ? $token[1] : $token;
        }
    }

    return $out;
}

/** A Markdown file without its code blocks and spans, where a name from the code is written as is. */
function withoutCode(string $content, string $path = 'un contenu sans chemin'): string
{
    return blank($content, '/^[ \t]*(```|~~~).*?^[ \t]*\1|`[^`\n]+`/ms', $path);
}

/**
 * A `target` to `_blank` without a `rel` holding `noreferrer` (which implies `noopener`)
 * in the same tag (HTML, Twig, JSX, a string holding HTML) or the same object
 * (`{ target: '_blank', rel: 'noreferrer' }`).
 *
 * @return list<array{int, string}>
 */
function blankWithoutRel(string $content): array
{
    $lines = explode("\n", $content);
    $out = [];
    if (!preg_match_all('/\btarget\s*(=|:)\s*\{?\s*\\\\?["\'`]?_blank|\btarget=\{[^}]*\'_blank\'/', $content, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    foreach ($all[0] as $i => [, $offset]) {
        $isObject = ':' === ($all[1][$i][0] ?? '');
        $scope = $isObject ? enclosing($content, $offset, '{', '}') : tag($content, $offset);
        if (null === $scope || preg_match('/\brel\s*[=:]\s*\{?\s*\\\\?["\'`][^"\'`]*\bnoreferrer\b/', $scope)) {
            continue;
        }
        $line = substr_count($content, "\n", 0, $offset) + 1;
        $out[] = [$line, $lines[$line - 1] ?? ''];
    }

    return $out;
}

/** The opening tag holding the offset: from the `<` before it to the `>` closing it, braces included. */
function tag(string $content, int $offset): ?string
{
    $start = $offset;
    while ($start > 0 && !('<' === $content[$start] && ctype_alpha($content[$start + 1] ?? ''))) {
        --$start;
    }
    if ('<' !== $content[$start]) {
        return null;
    }
    $depth = 0;
    for ($i = $start + 1, $n = \strlen($content); $i < $n; ++$i) {
        $c = $content[$i];
        if ('{' === $c) {
            ++$depth;
        } elseif ('}' === $c) {
            --$depth;
        } elseif ('>' === $c && $depth <= 0 && '=' !== $content[$i - 1]) {
            return substr($content, $start, $i - $start + 1);
        }
    }

    return substr($content, $start);
}

/**
 * `outline-none` or `outline-hidden` whose element shows no focus ring. The element is the
 * opening tag holding the class, read whole: its ring often sits on another line of the
 * same `cn()`. An element in `tabIndex={-1}` (or a condition that yields -1), focused only
 * by a script, passes. Outside a tag, the object that holds the class is read (props
 * spread on the element, `tabIndex: ref ? -1 : undefined`), else the line.
 *
 * @return list<array{int, string}>
 */
function outlineWithoutRing(string $content, string $searched): array
{
    if (!preg_match_all('/(?<![\w-])outline-(?:none|hidden)(?![\w-])/', $searched, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $lines = explode("\n", $content);
    $out = [];
    $seen = [];
    foreach ($all[0] as [, $offset]) {
        $line = substr_count($searched, "\n", 0, $offset) + 1;
        $start = $offset;
        while ($start > 0 && !('<' === $searched[$start] && ctype_alpha($searched[$start + 1] ?? ''))) {
            --$start;
        }
        $scope = tag($searched, $offset);
        if (null === $scope || $start + \strlen($scope) <= $offset) {
            $scope = enclosing($searched, $offset, '{', '}') ?? ($lines[$line - 1] ?? '');
        }
        if (isset($seen[$line]) || preg_match('/ring|\btabIndex(?:=\{|:)[^},]*-1\b|\btabindex=["\']-1["\']/i', $scope)) {
            continue;
        }
        $seen[$line] = true;
        $out[] = [$line, $lines[$line - 1] ?? ''];
    }

    return $out;
}

/** The delimited block holding the offset. */
function enclosing(string $content, int $offset, string $open, string $close): ?string
{
    $depth = 0;
    for ($start = $offset; $start >= 0; --$start) {
        if ($close === $content[$start]) {
            ++$depth;
        } elseif ($open === $content[$start] && 0 === $depth--) {
            break;
        }
    }
    if ($start < 0) {
        return null;
    }
    $depth = 0;
    for ($end = $start, $n = \strlen($content); $end < $n; ++$end) {
        if ($open === $content[$end]) {
            ++$depth;
        } elseif ($close === $content[$end] && 0 === --$depth) {
            break;
        }
    }

    return substr($content, $start, $end - $start + 1);
}

/**
 * A hyphen between spaces in displayed text: it stands for a dash. A range takes the en
 * dash without spaces (« 10–20 ha »), a page title the en dash between spaces
 * (« Annonces – Le site »), an aside commas.
 *
 * The displayed text that can be isolated without false positives: Twig template text,
 * outside tags, scripts, styles and expressions; JSX text between two tags on one line;
 * JSX text attributes. A PHP or TypeScript string cannot be judged without context (a
 * sheet tab name, a form name, a CMS category): out of the rule.
 *
 * @return list<array{int, string}>
 */
function hyphenAsDash(string $content, string $path): array
{
    $dash = HYPHEN_AS_DASH;
    if (str_ends_with($path, '.php')) {
        return hyphenAsDashInPhpStrings($content);
    }
    if (str_ends_with($path, '.twig')) {
        // The text of a `<code>` is a name, quoted as it is written elsewhere.
        $text = blank($content, '/\{#.*?#\}|\{\{.*?\}\}|\{%.*?%\}|<script\b.*?<\/script>|<style\b.*?<\/style>|<code\b.*?<\/code>|<[a-zA-Z!\/][^>]*>/s', $path);

        return matches('/'.$dash.'/u', $content, $text);
    }

    // A JSX text line on its own: no code punctuation, after a line ending a tag (a
    // subtraction split by Prettier follows a `(`, a `=`, a `,`, an arrow's `=>` or a
    // comparison's ` >`).
    $textLines = [];
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        $previous = $i > 0 ? rtrim($lines[$i - 1]) : '';
        $endsATag = str_ends_with($previous, '>') && !str_ends_with($previous, '=>')
            && ('>' === trim($previous) || !preg_match('/\s>$/', $previous));
        if ($endsATag && preg_match('/^\s*[^\s<>{}=;\'"`()\/*][^<>{}=;\'"`]*'.$dash.'[^<>{}=;\'"`]*$/u', $line)) {
            $textLines[] = [$i + 1, $line];
        }
    }

    return [
        ...matches('/>[^<>{}`\n]*'.$dash.'[^<>{}`\n]*</u', $content),
        ...matches('/\b(?:title|label|placeholder|aria-label|alt|description)=(["\'])[^"\'\n]*'.$dash.'[^"\'\n]*\1/u', $content),
        ...$textLines,
    ];
}


/**
 * An emoji a screen reader spells in the middle of the sentence: outside a hidden
 * `<span>` (`aria-hidden="true"`) or a named one (`role="img"` and `aria-label`). The
 * `{% %}` tags and Twig comments are left out: a variable is judged where it shows.
 * The ©, ® and ™ signs, pictographic for Unicode, read as words.
 *
 * @return list<array{int, string}>
 */
function visibleEmoji(string $content): array
{
    $searched = blank($content, '/\{#.*?#\}|\{%.*?%\}/s');
    $searched = blank($searched, '/<span\b[^>]*\b(?:aria-hidden="true"|role="img"[^>]*aria-label="[^"]+")[^>]*>[^<]*<\/span>/');

    return matches(EMOJI, $content, $searched);
}

/**
 * Each named class declaration that is neither `final` nor `abstract`. Under tests/, a base
 * that the tests extend (`…TestCase`) and a Foundry factory stay open.
 *
 * @return list<array{int, string}>
 */
function openClasses(string $content, bool $inTests = false): array
{
    $code = withoutComments($content, 'a.php');
    $out = [];
    foreach (matches('/^[ \t]*((?:(?:final|abstract|readonly)\s+)*)class\s+\w+/m', $content, $code) as [$line, $excerpt]) {
        if ($inTests && preg_match('/\bclass\s+\w*TestCase\b|\bextends\s+\\\\?(?:\w+\\\\)*\w*(?:ObjectFactory|ModelFactory)\b/', $excerpt)) {
            continue;
        }
        if (!preg_match('/\b(final|abstract)\b/', explode('class', $excerpt)[0])) {
            $out[] = [$line, $excerpt];
        }
    }

    return $out;
}

/**
 * A docblock right after another one: the first documents nothing.
 *
 * @return list<array{int, string}>
 */
function doubleDocblock(string $content): array
{
    $lines = explode("\n", $content);
    $out = [];
    // The first comment must be a docblock too: a `/* === … === */` banner above a docblock
    // documents nothing, it splits the file into sections.
    if (preg_match_all('#/\*\*(?:(?!\*/).)*\*/(?=[ \t]*\n[ \t]*(/\*\*))#s', $content, $all, \PREG_OFFSET_CAPTURE)) {
        foreach ($all[1] as [, $offset]) {
            $line = substr_count($content, "\n", 0, $offset) + 1;
            $out[] = [$line, $lines[$line - 1] ?? ''];
        }
    }

    return $out;
}

/**
 * The comment lines written in English. The comment is what withoutComments() blanks,
 * read line by line: a line counts as English when it holds at least two English
 * function words, no French one and no accented letter, among four words or more. Code
 * spans, URLs, `@tags`, `$variables` and the directives of the tools are left out first,
 * so that a French comment quoting code passes, and an attribute (`#[OA\Property(description:
 * '…')]`) is code, not a comment.
 *
 * @return list<array{int, string}>
 */
function englishComments(string $content, string $path): array
{
    $blanked = withoutComments($content, $path);
    if ($blanked === $content) {
        return [];
    }
    $english = array_flip(['the', 'and', 'is', 'are', 'of', 'to', 'with', 'from', 'this', 'that', 'when', 'for', 'it', 'be', 'not', 'only', 'which', 'if', 'we', 'should', 'must', 'will', 'was', 'were', 'has', 'have', 'does', 'by', 'at', 'its', 'into', 'than', 'then', 'they', 'there', 'these', 'those', 'would', 'could', 'can', 'but', 'because', 'while', 'after', 'before', 'our', 'you', 'your']);
    $french = array_flip(['le', 'la', 'les', 'des', 'du', 'un', 'une', 'est', 'et', 'pour', 'qui', 'que', 'dans', 'sur', 'pas', 'ne', 'au', 'aux', 'ce', 'cette', 'ces', 'se', 'il', 'elle', 'nous', 'sont', 'ont', 'plus', 'sans', 'avec', 'ou', 'quand', 'si', 'son', 'sa', 'ses', 'leur', 'par', 'en', 'de', 'y']);
    $lines = explode("\n", $content);
    $blankedLines = explode("\n", $blanked);
    $out = [];
    foreach ($lines as $i => $line) {
        $kept = $blankedLines[$i] ?? $line;
        if ($kept === $line) {
            continue;
        }
        $first = strspn($line ^ $kept, "\0");
        $text = substr($line, $first);
        // A comment opens with its mark, or a line inside a block with its star: a line
        // that does not was blanked by a `/*` inside a string (a glob, a selector).
        // A line inside a block without a star is not judged.
        // `#` opens a comment when a space or the end follows it: `'#%d'` in a string does not.
        if (!preg_match('~^\s*(//|#(?=\s|$)|\*|/\*|\{#|<!--)~', $text)) {
            continue;
        }
        if (preg_match('/check-mechanical-ignore|eslint-|@ts-|@phpstan-|@psalm-|phpcs:|prettier-ignore|@noinspection|@codeCoverage/', $text)) {
            continue;
        }
        // A text quoted between double quotes is cited as it is (a field name, a message).
        // The types of a phpdoc tag (`@return array{before: string}`) are code.
        do {
            $text = (string) preg_replace('/(?<=\w)(?:\{[^{}]*\}|<[^<>]*>)/', ' ', $text, -1, $replaced);
        } while ($replaced > 0);
        $text = (string) preg_replace(['/`[^`]*`/', '/"[^"]*"|“[^”]*”/u', '#\bhttps?://\S+#', '/@[\w-]+\s+[^\s$]+/', '/[@$][\w\\\\\[\]|,:.-]+/'], ' ', $text);
        if (preg_match('/[àâçéèêëîïôûùüÿœÀÂÇÉÈÊÎÔÛ«»]/u', $text)) {
            continue;
        }
        preg_match_all('/[a-z]+(?:\'[a-z]+)?/', strtolower($text), $words);
        $words = $words[0];
        if (\count($words) < 4) {
            continue;
        }
        $englishCount = \count(array_filter($words, static fn (string $w): bool => isset($english[$w])));
        $frenchCount = \count(array_filter($words, static fn (string $w): bool => isset($french[$w]) || str_contains($w, "'")));
        if ($englishCount >= 2 && 0 === $frenchCount) {
            $out[] = [$i + 1, $line];
        }
    }

    return $out;
}

/**
 * A closing inline tag alone on its line and followed by punctuation (`</a>.`): the line
 * break and the indentation before it render as a space inside the element, underlined
 * before the dot. A Twig whitespace control ending the line above (`-}}`, `-#}`, `-%}`)
 * removes that space, and so does Prettier's own layout of a JSX file, which is not read.
 *
 * @return list<array{int, string}>
 */
function detachedPunctuation(string $content): array
{
    $lines = explode("\n", $content);
    $out = [];
    foreach ($lines as $i => $line) {
        if (!preg_match('#^\s*</(?:a|abbr|b|button|code|em|i|span|strong)>[.,;:!?)]#', $line)) {
            continue;
        }
        $previous = '';
        for ($j = $i - 1; $j >= 0 && '' === $previous; --$j) {
            $previous = trim($lines[$j]);
        }
        if (!preg_match('/-(?:\}\}|#\}|%\})$/', $previous)) {
            $out[] = [$i + 1, $line];
        }
    }

    return $out;
}

/**
 * A route id or a DTO field accepting any number of digits. The digits may be written
 * `\d` (`\\d` in a double-quoted PHP string or written twice by habit), `[0-9]`, after an
 * optional leading `[1-9]`, and then `+` or `*`: `[1-9]\d{0,8}` is bounded and passes.
 * Read in a route attribute or a PHP route file (`'id' => '\d+'`, `{id<\d+>}`), a YAML route
 * file (`requirements: { id: '\d+' }`, one per line too), and in src/Dto/ a `Regex` that
 * accepts nothing but digits (`pattern: '/^\d+$/'`), which lets `99999999999` through to
 * the same `find()`.
 *
 * @return list<array{int, string}>
 */
function unboundedIds(string $content, string $path): array
{
    $code = withoutComments($content, $path);
    // Open (`\d+`), or bounded past nine digits (`\d{1,10}`, `[1-9]\d{0,9}`): the int4 stops
    // at 2147483647, so ten digits pass the pattern and still fail in PostgreSQL.
    $digit = '(?:\\\\{1,2}d|\[0-9\])';
    $digits = '(?:(?:\[1-9\])?'.$digit.'[+*]|'.$digit.'\{\d*,?\d{2,}\}|\[1-9\]'.$digit.'\{\d*,?9\})';
    $patterns = ['/\{\w+<'.$digits.'>/'];
    if ('php' === extension($path)) {
        $patterns[] = '/Requirement::(?:DIGITS|POSITIVE_INT)\b/';
        $patterns[] = '/=>\s*([\'"])'.$digits.'\1/';
        if (str_starts_with($path, 'src/Dto/')) {
            $patterns[] = '/\bRegex\(\s*(?:pattern\s*:\s*)?([\'"])([\/#~])\^'.$digits.'\$\2[a-zA-Z]*\1/';
        }
    } else {
        $patterns[] = '/^[ \t]*[\w-]+[ \t]*:[ \t]*([\'"]?)'.$digits.'\1[ \t]*$/m';
        $patterns[] = '/[{,][ \t]*[\w-]+[ \t]*:[ \t]*([\'"]?)'.$digits.'\1[ \t]*(?=[,}])/';
    }

    return sortedByLine(array_merge(...array_map(static fn (string $p): array => matches($p, $content, $code), $patterns)));
}

/**
 * @param list<array{int, string}> $hits
 *
 * @return list<array{int, string}> one per line, in order
 */
function sortedByLine(array $hits): array
{
    $byLine = [];
    foreach ($hits as [$line, $excerpt]) {
        $byLine[$line] ??= [$line, $excerpt];
    }
    ksort($byLine);

    return array_values($byLine);
}

/**
 * The content with everything outside the `ux_icon(…)` calls blanked: the root of a hand
 * written `<svg>`, or a `<path class="fill-…">`, takes its fill as it is.
 */
function iconCalls(string $content): string
{
    $kept = (string) preg_replace('/[^\n]/', ' ', $content);
    if (!preg_match_all('/\bux_icon\s*\(/', $content, $all, \PREG_OFFSET_CAPTURE)) {
        return $kept;
    }
    $n = \strlen($content);
    foreach ($all[0] as [$call, $offset]) {
        $depth = 0;
        for ($end = $offset + \strlen($call) - 1; $end < $n; ++$end) {
            if ('(' === $content[$end]) {
                ++$depth;
            } elseif (')' === $content[$end] && 0 === --$depth) {
                break;
            }
        }
        $kept = substr_replace($kept, substr($content, $offset, $end - $offset + 1), $offset, $end - $offset + 1);
    }

    return $kept;
}

/**
 * A hyphen between spaces in a PHP string that reads as text: on one line, opening with a
 * capital letter, holding no code (no dot between two words, no bracket, `$`, `<`, `=`,
 * no SQL keyword) and not passed to a logger, which nobody reads on screen. A choice
 * label (`'V1 - Salaire animateur'`), a field label, a flash message.
 *
 * @return list<array{int, string}>
 */
function hyphenAsDashInPhpStrings(string $content): array
{
    $code = withoutComments($content, 'a.php');
    $lines = explode("\n", $content);
    $out = [];
    if (!preg_match_all('/\'(?:[^\'\\\\\n]|\\\\.)*\'|"(?:[^"\\\\\n]|\\\\.)*"/u', $code, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    foreach ($all[0] as [$string, $offset]) {
        $text = substr($string, 1, -1);
        if (!preg_match('/^\p{Lu}/u', $text) || !preg_match('/'.HYPHEN_AS_DASH.'/u', $text)
            || preg_match('/\w\.\w|[(){}\[\]$<>=]|\b(?:SELECT|FROM|WHERE|JOIN)\b/', $text)) {
            continue;
        }
        $line = substr_count($code, "\n", 0, $offset) + 1;
        if (!preg_match('/\blogger->\w+\(/i', $lines[$line - 1] ?? '')) {
            $out[] = [$line, $lines[$line - 1] ?? ''];
        }
    }

    return sortedByLine($out);
}

/**
 * Des guillemets français avec une espace ordinaire à l'intérieur (pas `\s`, que `/u`
 * étend à l'insécable), dans le texte qu'un lecteur voit : ni un commentaire, ni la feuille
 * ou le script d'un gabarit, ni le commentaire d'une requête SQL écrite dans une chaîne
 * (`-- Étape « identifié »`). Un retour à la ligne après « ou avant » se rend lui aussi en
 * espace dans un gabarit.
 *
 * @return list<array{int, string}>
 */
function frenchQuotesWithSpace(string $content, string $path): array
{
    $searched = withoutComments($content, $path);
    if ('twig' === extension($path)) {
        $searched = blank($searched, '/<(script|style)\b.*?<\/\1>/s', $path);
    }

    return array_values(array_filter(
        matches('/\x{00AB}[ \t\r\n]|[ \t\r\n]\x{00BB}/u', $content, $searched),
        static fn (array $hit): bool => !preg_match('/\blogger->\w+\(|^\s*--\s/i', $hit[1]),
    ));
}

/**
 * The matches of the pattern in the text a reader sees: comments left out, and neither a
 * test (its text follows the screen's) nor a line written to a logger.
 *
 * @return list<array{int, string}>
 */
function inVisibleText(string $pattern, string $content, string $path): array
{
    return array_values(array_filter(
        matches($pattern, $content, withoutComments($content, $path)),
        static fn (array $hit): bool => !preg_match('/\blogger->\w+\(/i', $hit[1]),
    ));
}

/**
 * A `<Label>` or `<label>` that names no control: no `htmlFor` (nor `for`), no `id` that an
 * `aria-labelledby` points to, no spread that could carry either, and no control nested in it. A screen reader then reads the field
 * without its name, and a click on the text focuses nothing.
 *
 * @return list<array{int, string}>
 */
function orphanLabels(string $content, string $path): array
{
    $code = withoutComments($content, $path);
    $lines = explode("\n", $content);
    $out = [];
    if (!preg_match_all('/(?<![\w.$])<(Label|label)(?=[\s>\/])/', $code, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $control = '/<(?:input|select|textarea|Input|InputGroupInput|Textarea|Select|SelectTrigger|NativeSelect|Checkbox|Switch|Radio|RadioGroup|RadioGroupItem|Slider|Combobox|MultiSelect|DatePicker|InputOTP)\b/';
    foreach ($all[0] as $i => [, $offset]) {
        $opening = tag($code, $offset + 1) ?? '';
        // An `id` names a group through `aria-labelledby`.
        if (preg_match('/\s(?:htmlFor|for|id)\s*=|\{\s*\.\.\./', $opening)) {
            continue;
        }
        $name = $all[1][$i][0];
        if (!str_ends_with(rtrim($opening), '/>')) {
            $close = strpos($code, '</'.$name.'>', $offset);
            $inner = false === $close ? '' : substr($code, $offset + \strlen($opening), $close - $offset - \strlen($opening));
            if (preg_match($control, $inner)) {
                continue;
            }
        }
        $line = substr_count($code, "\n", 0, $offset) + 1;
        $out[] = [$line, $lines[$line - 1] ?? ''];
    }

    return $out;
}

/**
 * The matches of the pattern whose whole line does not match `$unless`: a class that removes
 * the outline is fine when its line also draws a ring.
 *
 * @return list<array{int, string}>
 */
function matchesUnless(string $pattern, string $unless, string $content, ?string $searched = null): array
{
    return array_values(array_filter(
        matches($pattern, $content, $searched),
        static fn (array $hit): bool => !preg_match($unless, $hit[1]),
    ));
}

/**
 * A document cited by its path from the root (`docs/….md`) or by a capitalised name
 * (`AGENTS.md`) that the repository does not hold: a comment that sends the reader to it
 * sends them nowhere. A path outside the repository (`../dev-standards/…`) is not read.
 *
 * @return list<array{int, string}>
 */
function missingDocuments(string $content): array
{
    $lines = explode("\n", $content);
    $out = [];
    if (!preg_match_all('#(?<![\w/.-])(docs/[\w./-]*\w\.md|[A-Z][A-Z0-9_-]*\.md)\b#', $content, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    foreach ($all[1] as [$document, $offset]) {
        // A guide linked from another repository (`docs/x.md`, a symbolic link) is the
        // repository's own, even when its target is not checked out next to it.
        if (!file_exists($document) && !is_link($document)) {
            $line = substr_count($content, "\n", 0, $offset) + 1;
            $out[] = [$line, $lines[$line - 1] ?? ''];
        }
    }

    return sortedByLine($out);
}

/**
 * The arguments of the call that opens at the offset (on its `(`), strings skipped so a
 * parenthesis inside a message does not close it, or null when it does not close.
 */
function callArguments(string $content, int $open): ?string
{
    $depth = 0;
    $n = \strlen($content);
    for ($i = $open; $i < $n; ++$i) {
        $char = $content[$i];
        if ('\'' === $char || '"' === $char) {
            for (++$i; $i < $n && $content[$i] !== $char; ++$i) {
                if ('\\' === $content[$i]) {
                    ++$i;
                }
            }
        } elseif ('(' === $char) {
            ++$depth;
        } elseif (')' === $char && 0 === --$depth) {
            return substr($content, $open, $i - $open + 1);
        }
    }

    return null;
}

/**
 * The calls opened by the pattern (which ends on their `(`) that the judge refuses, given
 * their arguments and the offset where they end.
 *
 * @param callable(string, int): bool $refused
 *
 * @return list<array{int, string}>
 */
function refusedCalls(string $pattern, string $content, string $searched, callable $refused): array
{
    if (!preg_match_all($pattern, $searched, $all, \PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $lines = explode("\n", $content);
    $out = [];
    foreach ($all[0] as [$text, $offset]) {
        $open = $offset + \strlen($text) - 1;
        $arguments = callArguments($searched, $open);
        if (null !== $arguments && $refused($arguments, $open + \strlen($arguments))) {
            $line = substr_count($searched, "\n", 0, $offset) + 1;
            $out[] = [$line, $lines[$line - 1] ?? ''];
        }
    }

    return $out;
}

/**
 * A `Length` with a `max` counted in characters, on a password: the hashers of Symfony
 * bound it in bytes, so a password of accented letters passed the validation and threw
 * in the hasher. A password is named by the constraint itself (`MAX_PASSWORD_LENGTH`), by
 * the property it is set on or by the form field that holds it. A `min` alone is the
 * user's measure, in characters.
 *
 * @return list<array{int, string}>
 */
function passwordLengthInCharacters(string $content): array
{
    $searched = withoutPhpComments($content);

    return refusedCalls(
        '/(?:\bnew\s+|#\[\s*|[\[,]\s*)(?:Assert\\\\)?Length\(/',
        $content,
        $searched,
        static function (string $arguments, int $end) use ($searched): bool {
            if (!preg_match('/\bmax\s*:|[\'"]max[\'"]\s*=>/', $arguments) || str_contains($arguments, 'COUNT_BYTES')) {
                return false;
            }
            if (preg_match('/password/i', $arguments)) {
                return true;
            }
            // The property under the attributes, past the other attributes and the comments.
            foreach (\array_slice(explode("\n", substr($searched, $end)), 1, 8) as $line) {
                $line = ltrim($line);
                if ('' === $line || preg_match('~^(?:#\[|//|/?\*|[)\]])~', $line)) {
                    continue;
                }
                if (preg_match('/\$(\w+)/', $line, $property)) {
                    return (bool) preg_match('/password/i', $property[1]);
                }
                break;
            }
            // The form field that holds it: the last `->add('…'` before the constraint.
            if (preg_match_all('/->add\(\s*[\'"](\w+)[\'"]/', substr($searched, 0, $end), $fields)) {
                return (bool) preg_match('/password/i', end($fields[1]));
            }

            return false;
        },
    );
}

/**
 * A colour written as a value instead of a token. Everywhere: a Tailwind arbitrary value
 * (`bg-[#68C85D]`) and a colour function with literal numbers (`hsl(142 76% 36%)`,
 * `oklch(0.7 0.1 140)`). In a template, a colour attribute (`stroke="#e5e7eb"`) and a
 * `class` or `style` holding one. In a script, a string that opens on one (`'#fff'`,
 * `'1px solid #ccc'`). rgb() and rgba() have their own rule.
 */
function hardcodedColour(string $path): string
{
    $hex = '#[0-9a-fA-F]{3,8}\b';
    $everywhere = '-\['.$hex.'\]|(?<![\w-])(?:hsla?|oklch|oklab|lch|lab)\(\s*[\d.]';
    if ('twig' === extension($path)) {
        return '/'.$everywhere.'|\b(?:class|style|fill|stroke|color|stop-color|flood-color|bgcolor)=[^>]*?'.$hex.'|\s[\w:-]+=["\']'.$hex.'["\']/';
    }

    return '/'.$everywhere.'|(?<=[\'"`\s:,(])'.$hex.'/';
}

/**
 * Each rule: its message, the extensions and paths it reads, its kind ("fichier": every
 * line; "ajout": added lines only), and the function that finds its occurrences.
 *
 * @return array<string, array{
 *     message: string,
 *     ext: list<string>,
 *     paths: string,
 *     except?: string,
 *     kind: 'fichier'|'ajout',
 *     find: callable(string, string): list<array{int, string}>,
 * }>
 */
function rules(): array
{
    // '': a file without an extension (Makefile, .husky/pre-commit, bin/console).
    $prose = ['php', 'ts', 'tsx', 'js', 'mjs', 'cjs', 'css', 'twig', 'yaml', 'yml', 'md', 'html', 'sh', 'neon', 'xml', 'dist', 'json', 'txt', ''];
    $front = ['ts', 'tsx', 'css', 'twig'];
    $markup = ['ts', 'tsx', 'js', 'mjs', 'twig'];
    $tests = '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$#';
    $everywhere = '#.#';
    $ui = '#^(assets|templates)/#';
    $palette = 'red|green|blue|amber|emerald|gray|zinc|neutral|slate|orange|yellow|rose|purple|indigo|teal|sky|lime|pink|violet|fuchsia|cyan|stone';
    $utility = 'text|bg|border(?:-[trblxy])?|fill|stroke|ring|ring-offset|from|to|via|outline|decoration|divide|placeholder|accent|caret|shadow';

    return [
        // Writing, everywhere text is written.
        'tiret-long' => [
            'message' => 'tiret long : une incise prend des virgules, une explication les deux-points, une case vide le tiret demi-cadratin « – »',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            // The entities and the escape are written here without their whole name,
            // otherwise this file would refuse itself. An empty cell takes the en dash.
            'find' => static fn (string $c): array => matches('/\x{2014}|&(?:mdash|#0*8212|#x0*2014);|\\\\u2014/ui', $c),
        ],
        'point-median' => [
            'message' => 'point médian employé comme séparateur : une virgule, deux lignes ou une liste (l\'écriture inclusive, « porteur·se », passe)',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            // Inclusive writing glues the dot between two letters: it stays allowed.
            'find' => static fn (string $c): array => matches('/(?<!\p{L})\x{00B7}|\x{00B7}(?!\p{L})|&(?:middot|#0*183|#x0*B7);|\\\\u00b7/ui', $c),
        ],
        'faute' => [
            'message' => 'faute déjà relevée par un audit : réessayez, configurée, vous allez, raisonnable, répétez, expliquez-nous, À très vite, majuscule accentuée (État, Équipe, Écrivez, Êtes)',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            // Written in pieces: this file holds none of the misspellings it looks for.
            'find' => static fn (string $c, string $p): array => matches(
                '/(?i:r[ée]é{1}ssay|\bconfigur(?:ee)s?\b|\bvous\s(?:aller)\b|\brai(?:sonable)|\br(?:é)petez\b|\bexpliquez\s(?:nous)\b)|\bA\s(?:très\svite)\b'
                // A type name (`interface Etape {`, `Etape[]`, and in code `(e: Etape)`,
                // `type Etape,`, `from './Etapes'`) is not text.
                .'|\bE(?:tats?|quipes?|crivez|crire|tes|tapes?|chéances?|vénements?|vènements?|crans?|léments?|galement|tablissements?|ligibilité|pargne|tudes?|leveurs?|conomies?|lectricité)\b(?![\w\[\]<>({}=;]|\s+[{=(<]'
                .(\in_array(extension($p), ['php', 'ts', 'tsx', 'js', 'mjs', 'cjs'], true) ? '|[,)|?:\'"\/]|\s+\}' : '').')/u',
                $c,
                'md' === extension($p) ? withoutCode($c, $p) : $c,
            ),
        ],
        'faute-recente' => [
            'message' => 'faute déjà relevée par un audit : Ce champ, Êtes-vous sûr, régional, dérivé, prises en charge, tous projets confondus, œuvre',
            'ext' => $prose,
            'paths' => $everywhere,
            // Jugée sur les lignes ajoutées tant qu'un projet en garde : elle rejoint `faute`
            // une fois chaque stock nettoyé.
            'kind' => 'fichier',
            // Written in pieces: this file holds none of the misspellings it looks for.
            'find' => static fn (string $c, string $p): array => matches(
                '/(?i:\bce\schamp(?:s)\b|\b[Êê]tes-vous\ss(?:ur)\b|\br[ée]gion(?:n)al|\bdrif(?:f)[ée]|\bprises\sen\scharge(?:s)\b|\bprojet\sc(?:onfondus)\b|\bo(?:e)uvre)/u',
                $c,
                'md' === extension($p) ? withoutCode($c, $p) : $c,
            ),
        ],
        'anglicisme' => [
            'message' => '« upload » dans un texte affiché : téléverser, envoyer, déposer, joindre (« Documents envoyés », « Déposez votre fichier »)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml'],
            'paths' => $everywhere,
            'except' => $tests,
            'kind' => 'ajout',
            // `uploader` alone is an English name in the code (`$uploader`, `VichUploader`):
            // it counts after a French word only.
            'find' => static fn (string $c, string $p): array => inVisibleText(
                '/uploadé(?:e|s|es)?(?!\p{L})|(?i:\bupload(?:ez|ons)\b)|(?:\b[ldLD][\'\x{2019}]|\b(?:à|pour|de|un|des|les|cet|votre|vos|du|chaque)\s+)(?i:upload(?:er|s)?)\b/u',
                $c,
                $p,
            ),
        ],
        'points-suspension' => [
            'message' => 'trois points dans un texte affiché : le caractère « … » (« Rechercher… », « Chargement… »)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml'],
            'paths' => $everywhere,
            'except' => $tests,
            'kind' => 'ajout',
            // After a letter and before the end of the text: the spread (`...props`,
            // `[...a]`) and the first-class callable (`strlen(...)`) follow a bracket or a
            // space, and an escape (`"\n..."`) is not a word.
            'find' => static fn (string $c, string $p): array => inVisibleText('/(?<=(?<!\\\\)\p{L})\.\.\.(?=[\s"\'`<)\]}]|$)/um', $c, $p),
        ],
        'trait-union' => [
            'message' => 'trait d\'union entouré d\'espaces dans un texte affiché : tiret moyen (« 10–20 ha », « Annonces – Site ») ou virgules',
            'ext' => ['tsx', 'twig', 'php'],
            'paths' => '#^(assets|templates|src)/#',
            'except' => $tests,
            'kind' => 'fichier',
            // A PHP string read as text: the projects still hold some (a sheet tab, a
            // third-party label quoted as is), so only the new ones are judged.
            'addedOnly' => '#\.php$#',
            'addedOnlyLabel' => 'PHP',
            'find' => static fn (string $c, string $p): array => hyphenAsDash($c, $p),
        ],
        'entite-html' => [
            'message' => 'entité HTML dans un fichier UTF-8 : le caractère lui-même (`&nbsp;` reste permis, comme `&apos;` et `&quot;` en JSX)',
            'ext' => ['tsx', 'twig'],
            'paths' => $ui,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/&(?:[a-zA-Z]{1,2}(?:grave|acute|circ|uml|cedil|lig)|[lr]squo|[lr]dquo|[lr]aquo|hellip|ndash|euro|[lrud]arr|times|#\d{3,5}|#x[0-9a-fA-F]{2,4});/', $c),
        ],
        'diacritique-invisible' => [
            'message' => 'diacritique combinant (U+0300 à U+036F) écrit tel quel : invisible à la lecture comme dans un diff, l\'échappement (`\\u0301`) ou la lettre précomposée',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/[\x{0300}-\x{036F}]/u', $c),
        ],
        'guillemets-anglais' => [
            'message' => 'guillemets anglais dans un texte affiché : « » avec leurs espaces insécables (« quotidien »)',
            'ext' => ['tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/[\x{201C}\x{201D}]/u', $c),
        ],
        'guillemets-droits' => [
            'message' => 'guillemets droits autour d\'un texte affiché : « » avec leurs espaces insécables (`«\\u00a0${nom}\\u00a0»`)',
            'ext' => ['tsx'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            // A quoted text sits inside a sentence: the pair of double quotes follows a word and
            // one space (« le bouton "Valider" »), around a word or an interpolation, and is
            // followed by a space or a punctuation mark. Code never puts a word right before a
            // string, except a keyword (`from "react"`, `return "x"`, `case "a"`), so a project
            // whose Prettier writes code strings between double quotes (`=== "all"`,
            // `unit: "kWh"`, `fn("x")`) passes, as does a JSX attribute after its `=`. A string
            // holding an apostrophe keeps its double quotes: it passes.
            'find' => static fn (string $c, string $p): array => matches(
                '/(?<=\p{L} )(?<!\bfrom )(?<!\breturn )(?<!\bcase )(?<!\bin )(?<!\bof )(?<!\btypeof )(?<!\bawait )(?<!\byield )(?<!\bimport )(?<!\bexport )(?<!\bas )(?<!\belse )(?<!\bthrow )(?<!\bnew )"(?:\$\{[^}\n]+\}|\p{L})[^"\'\x{2019}\n]*"(?=[\s.,;:!?)]|$)/um',
                $c,
                withoutComments($c, $p),
            ),
        ],
        'capitale-sans-accent' => [
            'message' => 'capitale privée de son accent en tête de phrase : « À noter », « À toi », « À titre », « Élaborer », « Étalons »',
            'ext' => ['tsx', 'twig', 'json'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/\bA\s(?:qui|noter|toi|titre|cette|quel|quelle|partir\sde)\b|\bE(?:labor\w*|talons?)\b/u', $c),
        ],
        'ponctuation-detachee' => [
            'message' => 'balise fermante seule sur sa ligne et suivie de la ponctuation : le retour à la ligne se rend en espace soulignée (« lien . ») ; `{#- … -#}` en fin de ligne au-dessus, ou `-}}`',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => detachedPunctuation($c),
        ],
        'identifiant-constat' => [
            'message' => 'identifiant de constat d\'audit : la réécriture de docs/gap-analysis.md l\'efface, dire la cause en clair ou citer le commit',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/\b(?:z\d{2}|t-[a-z0-9]+(?:-[a-z0-9]+)*)#[A-Z]{0,2}\d+\b/', $c),
        ],
        'identifiant-audit' => [
            'message' => 'numéro de passe, de constat ou date d\'audit : la réécriture de docs/gap-analysis.md l\'efface, dire la cause en clair ou citer le commit',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'ajout',
            // « gap analysis 2026-07 » : chaque audit réécrit le fichier, la date ne
            // désigne plus rien dès le suivant. Le chemin `docs/gap-analysis.md` passe.
            // Then a finding named by its number (`gap H5`, `gap-analysis M12bis`), a section
            // of the file (`gap-analysis §9`), a numbered ruling (`arbitrage 21c`), and in the
            // code a pull request (`(PR #101)`): the document that cites it says why itself.
            'find' => static fn (string $c, string $p): array => matches(
                '/\b\d{1,2}(?:e|\x{1D49}|ème|th)\s+(?:passe|gap-analysis\spass)\b|\bpasses?\s+\d{1,2}\b|\bfindings?\s+#?\d|\bconstats?\s+(?:n°\s*)?#?\d'
                .'|(?i:\bgap[\s-]analysis)\s+(?:du\s+|de\s+|d\x{2019}|d\')?\(?(?:\d{4}-\d{2}|\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}|(?:\d{1,2}(?:er)?\s+)?(?:janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\s+\d{4})'
                .'|(?i:\bgap(?:[\s-]analysis)?)\s+[HMB]\d+|(?i:\bgap[\s-]analysis)\s+§\s*\d+|\barbitrage\s+\d+[a-z]\b'
                .('md' === extension($p) ? '' : '|\(PR\s+#\d+\)').'/u',
                $c,
            ),
        ],

        // The design system and the kit.
        'couleur-brute' => [
            'message' => 'couleur Tailwind de la palette brute : un jeton du design system',
            'ext' => [...$front, 'php'],
            'paths' => '#^(assets|templates|src/Twig)/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/(?<![-\w])(?:[\w-]+:)*(?:'.$utility.')-(?:'.$palette.')-\d{2,3}\b/', $c),
        ],
        'rgba' => [
            'message' => 'couleur rgb()/rgba() en littéral : un jeton, avec son opacité (`bg-primary/20`, `color-mix`)',
            // An extension of src/Twig/ writes its markup too.
            'ext' => [...$front, 'php'],
            'paths' => '#^(assets|templates|src/Twig)/#',
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/(?<![a-zA-Z])rgba?\(\s*(?:[\d.]|var\()/', $c),
        ],
        'blanc-noir' => [
            'message' => '`white`/`black` posé hors jetons, en classe ou dans un style : `bg-card`, `text-foreground`, `text-primary-foreground`, `var(--primary-foreground)`…',
            'ext' => [...$front, 'php'],
            'paths' => '#^(assets|templates|src/Twig)/#',
            'kind' => 'fichier',
            // The Tailwind classes, then a colour property written in an inline style, a CSS
            // string or a React `style` object (`color: white`, `backgroundColor: 'black'`).
            'find' => static fn (string $c): array => matches(
                '/(?<![-\w\[])(?:[\w-]+:)*(?:bg|text|fill|stroke|ring|border(?:-[trblxy])?|from|to|via|divide|outline|decoration)-(?:white|black)\b'
                .'|(?<![-\w])(?:color|background(?:-color)?|backgroundColor|fill|stroke|border-color|borderColor|outline-color|outlineColor)\s*:\s*[\'"]?(?:white|black)\b/',
                $c,
            ),
        ],
        // A locked lucide icon carries `fill="none"` and its stroke on its `<g>`, which beat
        // any fill or stroke set on the `<svg>` root: a `fill-*` or `stroke-*` class, or a
        // `fill:` option there, paints nothing.
        'icone-racine' => [
            'message' => 'remplissage ou trait posé sur la racine d\'une icône `ux_icon` : l\'icône verrouillée porte `fill="none"` et son trait sur son `<g>`, qui l\'emportent ; viser les enfants (`[&>*]:fill-success`), le trait suit `currentColor`',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            // Read inside a `ux_icon(…)` call only: a `<svg>` written by hand takes its fill.
            'find' => static fn (string $c): array => matches('/(?<![-\w\]:&>*_\/])(?:[\w-]+:)*(?:fill|stroke)-(?!none\b)[a-z][\w\/-]*+(?!\s*=)|(?<![-\w])fill\s*:\s*[\'"]/', $c, iconCalls($c)),
        ],
        // React islands (reactony, principle 6): a one-click action posts through its `/api`
        // route and the SDK, not a form the browser submits with a hidden CSRF token.
        'formulaire-twig' => [
            'message' => 'formulaire POST écrit à la main dans un gabarit : l\'action passe par sa route `/api` et le SDK dans un îlot (reactony, principe 6) ; l\'authentification et le back-office du bundle portent leur exception',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/<form\b[^>]*\bmethod\s*=\s*["\']post["\']|\bname\s*=\s*["\']_token["\']/i', $c),
        ],
        'classe-inexistante' => [
            'message' => 'classe Bootstrap ou qu\'aucune feuille ne définit : son équivalent Tailwind (`animate-spin`, `sr-only`, `text-base`, `hidden`)',
            'ext' => ['ts', 'tsx', 'js', 'twig', 'css'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/(?<![\w:\/-])(?:spinner-(?:border|grow)|visually-hidden(?:-focusable)?|text-md|d-(?:none|flex|block|inline-block|grid)|form-(?:control|group|select)|text-muted|font-weight-\w+)(?![\w-])/', $c),
        ],
        'libelle-anglais' => [
            'message' => 'libellé anglais du kit shadcn lu par un lecteur d\'écran : en français (« Fermer », « Autres pages », « Afficher »)',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches(
                '/(?:aria-label=\{?\s*|\bsr-only\b[^>]*>\s*)[\'"]?(?:Close|More(?: pages)?|Toggle\b[\w ]*|Previous|Next|Go to (?:previous|next) page|Loading|Open menu|Search|Remove|Select all|Scroll (?:left|right))[\'"<]/',
                $c,
            ),
        ],
        'label-natif' => [
            'message' => '`<label>` natif : le `Label` du kit (`components/ui/label`)',
            'ext' => ['tsx'],
            'paths' => '#^assets/#',
            // The kit's own Label wraps the native one.
            'except' => '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$|^assets/components/ui/label\.tsx$#',
            'kind' => 'fichier',
            // The composites of assets/components/ui/ used to be skipped with the registry: a
            // project may still hold a native label there.
            'addedOnly' => '#^assets/components/ui/#',
            'addedOnlyLabel' => 'assets/components/ui/',
            'find' => static fn (string $c, string $p): array => matches('/<label\b/', $c, withoutComments($c, $p)),
        ],
        'label-orphelin' => [
            'message' => '`<Label>` ou `<label>` qui ne nomme aucun champ : `htmlFor` (`for` dans un gabarit) vers l\'`id` du contrôle, ou le contrôle dans le libellé ; sinon un texte (`<p>`, `<dt>`, `<span>`), un titre de groupe (`<legend>`, `aria-labelledby`)',
            'ext' => ['tsx', 'twig'],
            'paths' => '#^(assets|templates)/#',
            'except' => '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$|^assets/components/ui/label\.tsx$#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => orphanLabels($c, $p),
        ],
        'case-native' => [
            'message' => 'case à cocher native : la `Checkbox` du kit, qui reçoit le focus sous Safari',
            'ext' => ['tsx'],
            'paths' => '#^assets/#',
            'except' => $tests,
            'kind' => 'fichier',
            // `[type="checkbox"]` is a selector, not an input.
            'find' => static fn (string $c, string $p): array => matches('/(?<!\[)type=[\'"{]+checkbox/', $c, withoutComments($c, $p)),
        ],
        'main-ilot' => [
            'message' => '`<main>` dans un îlot React : le gabarit de base pose le seul de la page, un îlot pose un `<div>` ou une `<section>`',
            'ext' => ['tsx'],
            'paths' => '#^assets/#',
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/<main\b|role=[\'"{]+main\b/', $c, withoutComments($c, $p)),
        ],
        'main-gabarit' => [
            'message' => '`<main>` hors de templates/base.html.twig : le gabarit de base pose le seul de la page, que vise le lien d\'évitement, un gabarit enfant pose un `<div>` ou une `<section>` (une page autonome, la mise en page d\'un back-office, porte son exception)',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'except' => '#^templates/base\.html\.twig$#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/<main\b/', $c, withoutComments($c, $p)),
        ],
        'boite-native' => [
            'message' => 'boîte native `confirm()` ou `alert()` : un `Dialog` du kit, stylé et atteignable au clavier, ou un `toast` (reactony §10) ; dans un gabarit, un contrôleur qui ouvre ce `Dialog`',
            'ext' => ['ts', 'tsx', 'js', 'mjs', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            // Only `window.` or `globalThis.` may stand before the name: the method of
            // another object (`dialog.confirm(`, `$this->confirm(`) is not the browser's box.
            'find' => static fn (string $c, string $p): array => matches(
                '/(?:(?<![\w.>$:])|(?<=\bwindow\.)|(?<=\bglobalThis\.))(?:confirm|alert)\(/',
                $c,
                withoutComments($c, $p),
            ),
        ],
        'contour-sans-anneau' => [
            'message' => '`outline-none` ou `outline-hidden` sans anneau de focus sur l\'élément : `focus-visible:ring-2 focus-visible:ring-ring` (WCAG 2.4.7), sinon le clavier s\'arrête sur un élément qu\'on ne voit pas ; un élément en `tabIndex={-1}`, que seul un script focalise, passe',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => outlineWithoutRing($c, withoutComments($c, $p)),
        ],
        'couleur-litterale' => [
            'message' => 'couleur écrite en dur (hexadécimal, `hsl()`, `oklch()`…) dans une classe, un attribut ou un littéral : un jeton du design system (`bg-success`, `text-link`, `fill-border`), `var(--color-…)` dans un style ; une carte ou un graphique qui ne lit pas la feuille porte son exception',
            // A PHP enum or extension that hands a colour to a template, too.
            'ext' => ['ts', 'tsx', 'twig', 'php'],
            'paths' => '#^(assets|templates|src/(?:Domain|Twig))/#',
            // A mail and a PDF are rendered outside the browser, without the application's
            // stylesheet: their inline styles carry their colours.
            'except' => '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$|^templates/(?:.*/)?(?:emails?|pdf)/#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => matches(hardcodedColour($p), $c, withoutComments($c, $p)),
        ],
        'contenu-attenue' => [
            'message' => 'opacité posée sur un contenu au repos (`opacity-50`) : son texte tombe sous 4,5:1 ; `text-muted-foreground` ou un fond, l\'opacité restant aux états (`disabled:`, `hover:`, `group-hover:`)',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$|^templates/(?:.*/)?(?:emails?|pdf)/#',
            'kind' => 'ajout',
            // A variant before it (`disabled:opacity-50`) is a state; `opacity-0` hides,
            // `opacity-100` shows. So is a disabled state written by a condition, which the
            // line says by `cursor-not-allowed` (`disabled && 'cursor-not-allowed opacity-50'`).
            'find' => static fn (string $c, string $p): array => matchesUnless('/(?<![\w:\/-])opacity-(?:[1-9]\d?(?![\w-])|\[)/', '/cursor-not-allowed/', $c, withoutComments($c, $p)),
        ],

        // Twig templates.
        'script-en-ligne' => [
            'message' => 'script en ligne, attribut `on…=` ou lien `javascript:` dans un gabarit : le comportement vit dans un îlot React testé, Stimulus ne servant qu\'à le monter (reactony §6) ; un `<script src>` et un bloc de données JSON passent',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches(
                '/(?i:<script\b(?![^>]*\bsrc=)(?![^>]*\btype=["\'](?:application\/(?:ld\+)?json|text\/template)["\']))|\son[a-z]+=\s*["\']|(?i:\b(?:href|src|action|formaction)\s*=\s*["\']\s*javascript:)/',
                $c,
                withoutComments($c, $p),
            ),
        ],
        'hauteur-vh' => [
            'message' => 'hauteur arbitraire calée sur la fenêtre en `vh` (`h-[calc(100vh-56px)]`) : sur mobile, `vh` compte la barre d\'adresse et le bas passe dessous ; `dvh`, qui suit la partie visible',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => matches('/(?<![\w-])(?:[a-z0-9-]+:)*(?:min-|max-)?h-\[(?:calc\()?\d+vh\b/', $c, withoutComments($c, $p)),
        ],
        'taille-maison' => [
            'message' => 'taille de texte maison au-dessus de 12 px (`text-[1.75rem]`, `lg:text-[3.25rem]`) : un pas de l\'échelle (`text-2xl`, `lg:text-5xl`) ; seule une étiquette de 12 px ou moins sort de l\'échelle, qui s\'arrête à `text-xs`',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => '#(^|/)(tests|e2e|__tests__)/|\.(test|spec)\.[cm]?[jt]sx?$|^assets/components/ui/#',
            'kind' => 'ajout',
            'find' => static function (string $c, string $p): array {
                $searched = withoutComments($c, $p);
                preg_match_all('/(?<![\w-])(?:[\w-]+:)*text-\[(\d+(?:\.\d+)?)(px|rem)\]/', $searched, $sizes, \PREG_SET_ORDER);
                $above = array_unique(array_map(
                    static fn (array $size): string => $size[1].$size[2],
                    array_filter($sizes, static fn (array $size): bool => (float) $size[1] * ('rem' === $size[2] ? 16 : 1) > 12),
                ));

                return [] === $above ? [] : matches('/(?<![\w-])(?:[\w-]+:)*text-\[(?:'.implode('|', array_map(static fn (string $size): string => preg_quote($size, '/'), $above)).')\]/', $c, $searched);
            },
        ],
        'chart-gabarit' => [
            'message' => 'jeton `chart-*` dans un gabarit : il colore les séries des graphiques et changera le jour où un graphique en demandera d\'autres ; le jeton d\'interface qui porte le sens',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => matches('/(?<![\w-])(?:[\w-]+:)*(?:text|bg|border|ring|fill|stroke|from|via|to|outline|decoration)-chart-[1-5]\b/', $c, withoutComments($c, $p)),
        ],
        'style-en-ligne' => [
            'message' => 'style en ligne dans un gabarit : une classe Tailwind et les jetons du design system ; un mail et un PDF, rendus sans la feuille de l\'application, passent',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'except' => '#^templates/(?:.*/)?(?:emails?|pdf)/#',
            'kind' => 'ajout',
            // The attribute, or its key in a Twig hash (`{ style: '…' }`).
            'find' => static fn (string $c, string $p): array => matches('/\sstyle="|(?<![\w-])[\'"]?style[\'"]?\s*:\s*[\'"]/', $c, withoutComments($c, $p)),
        ],
        'classe-en-morceaux' => [
            'message' => 'classe composée en morceaux dans un attribut `class` : Tailwind ne génère que les classes écrites entières, `{{ cond ? \'md:grid-cols-3\' : \'md:grid-cols-2\' }}`',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/\bclass="[^"]*[a-z0-9:]-(?:\{%|\{\{)/', $c, withoutComments($c, $p)),
        ],
        'balise-en-chaine' => [
            'message' => 'balise Twig dans une chaîne concaténée : Twig ne l\'interprète pas, elle part telle quelle au navigateur ; l\'écrire en expression, `~ (condition ? \' hidden\' : \'\') ~`',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/~\s*\'(?:[^\'\\\\\n]|\\\\.)*\{[%{]|\'(?:[^\'\\\\\n]|\\\\.)*\{[%{](?:[^\'\\\\\n]|\\\\.)*\'\s*~/', $c),
        ],
        'pourcentage-colle' => [
            'message' => 'pourcentage collé à sa valeur : une espace insécable avant « % » (`{{ taux }}\u{a0}%`) ; dans une déclaration CSS (`width: {{ … }}%`), l\'unité reste collée',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matchesUnless('/\}\}%(?![0-9A-Fa-f]{2})/', '/[a-z-]+:\s*\{\{[^}]*\}\}%/', $c),
        ],
        'pourcentage-espace' => [
            'message' => 'pourcentage séparé de sa valeur par une espace ordinaire : la ligne peut se couper avant « % » ; l\'espace insécable (`12\u{a0}%`, `{{ taux }}&nbsp;%`, `${taux}\\u00a0%`)',
            'ext' => ['twig', 'ts', 'tsx', 'js', 'mjs'],
            'paths' => '#^(assets|templates)/#',
            'except' => $tests,
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => inVisibleText(PERCENT_AFTER_SPACE, $c, $p),
        ],

        // What the page loads and opens.
        'target-blank' => [
            'message' => '`target="_blank"` sans `rel="noopener noreferrer"` dans la même balise ou le même objet : la page ouverte garderait une prise sur celle-ci et saurait d\'où elle vient',
            'ext' => [...$markup, 'php'],
            'paths' => '#^(assets|templates|src)/#',
            'except' => $tests,
            'kind' => 'fichier',
            // PHP assembles its tags piece by piece: there, `noreferrer` is looked for on the
            // line (`'target' => '_blank'` of an EasyAdmin field, `target="_blank"` in a
            // string), and the projects still hold some, so only the new lines are judged.
            'addedOnly' => '#\.php$#',
            'addedOnlyLabel' => 'PHP',
            'find' => static fn (string $c, string $p): array => 'php' === extension($p)
                ? array_values(array_filter(
                    matches('/[\'"]target[\'"]\s*=>\s*[\'"]_blank|\btarget\s*=\s*\\\\?[\'"]_blank/', $c, withoutComments($c, $p)),
                    static fn (array $hit): bool => !str_contains($hit[1], 'noreferrer'),
                ))
                : blankWithoutRel($c),
        ],
        'hote-tiers' => [
            'message' => 'image ou emoji chargé d\'un hôte tiers : il reçoit l\'IP du visiteur et disparaît avec son fichier ; le fichier dans le dépôt, une icône lucide ou le caractère',
            'ext' => ['ts', 'tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches(
                '#em-content\.zobj\.net|raw\.githubusercontent\.com|https?://[^\s\'"()]+\.(?:png|jpe?g|webp|gif|svg|avif|ico)\b|<img\b[^>]*\ssrc=["\'{]+https?://#i',
                $c,
            ),
        ],
        'cdn-webflow' => [
            'message' => 'image sur le CDN Webflow ou chez Unsplash : retirée là-bas, elle casse la page ; le fichier va dans le dépôt',
            'ext' => ['php', 'ts', 'tsx', 'css', 'twig', 'yaml'],
            'paths' => '#^(assets|templates|src|config)/#',
            'kind' => 'fichier',
            // A PDF dropped on Webflow (a programme, a partner's FAQ) is not an image.
            'find' => static fn (string $c): array => matches('#\b(?:uploads-ssl\.webflow\.com|cdn\.prod\.website-files\.com|assets(?:-global)?\.website-files\.com|global-uploads\.webflow\.com|images\.unsplash\.com)/(?![^\s"\'<>]*\.pdf\b)#i', $c),
        ],
        'emoji-gabarit' => [
            'message' => 'emoji lu par un lecteur d\'écran : seul dans un `<span aria-hidden="true">`, ou nommé (`role="img"` et `aria-label`) s\'il porte un sens',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => visibleEmoji($c),
        ],
        'emoji-stimulus' => [
            'message' => 'emoji écrit par un contrôleur Stimulus : il pose un texte qu\'une zone de statut lit, sans emoji',
            'ext' => ['ts'],
            'paths' => '#^assets/controllers/[^/]+\.ts$#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches(EMOJI, $c, withoutComments($c, $p)),
        ],

        // The organisation's addresses, in one place.
        'url-feve' => [
            'message' => 'URL de feve.co en dur : la constante qui les rassemble (une classe du domaine, exposée à Twig)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml'],
            'paths' => '#^(src|assets|templates|config)/#',
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('#\bhttps?://(?:[\w-]+\.)*feve\.co\b|\bwww\.feve\.co/#i', $c, withoutComments($c, $p)),
        ],
        'contact-feve' => [
            'message' => 'adresse @feve.co en dur : la constante qui les rassemble (une classe du domaine, exposée à Twig, `!php/const` en YAML)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml'],
            'paths' => '#^(src|assets|templates|config)/#',
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/[\w.+-]+@feve\.co\b/i', $c, withoutComments($c, $p)),
        ],

        // PHP and the repository.
        'chemin-relatif' => [
            'message' => 'chemin relatif au dossier courant (`\'var/`, `\'data/`, `getcwd()`) : il change avec le cron ou le worker qui lance la commande, partir de `%kernel.project_dir%`',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/([\'"])(?:var|data)\/|\bgetcwd\(/', $c, withoutComments($c, $p)),
        ],
        'choice-litteral' => [
            'message' => '`Choice` qui recopie une liste : `callback` vers les `cases()` de l\'enum, la liste suit alors le code',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/\bChoice\(\s*\[|\bchoices:\s*\[/', $c, withoutComments($c, $p)),
        ],
        'id-sans-borne' => [
            'message' => 'identifiant sans borne (`Requirement::DIGITS`, `\d+`, `[0-9]+`, dans une route PHP ou YAML, ou une `Regex` de DTO) : `99999999999` atteint PostgreSQL, qui refuse l\'int4 et répond 500, borner à neuf chiffres (`[1-9]\d{0,8}`, une constante `Int4::ID`)',
            'ext' => ['php', 'yaml', 'yml'],
            'paths' => '#^(src/|config/routes(/|\.ya?ml$|\.php$))#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => unboundedIds($c, $p),
        ],
        'docblock-double' => [
            'message' => 'docblock suivi d\'un autre docblock : le premier ne documente rien, le fondre dans le second ou le retirer',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs'],
            'paths' => $everywhere,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => doubleDocblock($c),
        ],
        'commentaire-anglais' => [
            'message' => 'commentaire en anglais sur un projet français : en français, comme le veut dev-standards/agent/writing.md (un texte tiers cité tel quel garde son exception)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml', 'sh'],
            'paths' => $everywhere,
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => englishComments($c, $p),
        ],
        'strict-types' => [
            'message' => 'fichier PHP ajouté ou renommé sans `declare(strict_types=1);`',
            'ext' => ['php'],
            'paths' => '#^(src|tests)/#',
            // Reported on the first line, which only a new file adds.
            'kind' => 'ajout',
            'wholeWhenMoved' => true,
            'find' => static fn (string $c): array => preg_match('/^declare\(strict_types=1\);/m', $c) ? [] : [[1, '<?php']],
        ],
        'classe-finale' => [
            'message' => 'classe ajoutée ou renommée ni `final` ni `abstract` : une classe se ferme, l\'héritage se décide (une base de tests s\'appelle `…TestCase`, ou se déclare `abstract`)',
            'ext' => ['php'],
            'paths' => '#^(src|tests)/#',
            // Doctrine extends the entities for its lazy proxies; Foundry's factories follow
            // their maker.
            'except' => '#^src/Entity/|^tests/Factory/#',
            'kind' => 'ajout',
            'wholeWhenMoved' => true,
            'find' => static fn (string $c, string $p): array => openClasses($c, str_starts_with($p, 'tests/')),
        ],
        'map-query-statut' => [
            'message' => '`#[MapQueryString]` ou `#[MapQueryParameter]` sans `validationFailedStatusCode` : un paramètre invalide répondrait le 404 de Symfony, sans `violations` ; `Response::HTTP_UNPROCESSABLE_ENTITY`, ou, pour une page qui lit une valeur absurde comme absente, `flags: \\FILTER_NULL_ON_FAILURE` sur un argument nullable',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/#\[MapQuery(?:String|Parameter)(?!.*(?:validationFailedStatusCode|FILTER_NULL_ON_FAILURE))/', $c, withoutComments($c, $p)),
        ],
        'relance-a-la-main' => [
            'message' => '`new RetryableHttpClient` dans le code : la relance se règle une fois, sur le client nommé (`scoped_clients` et `retry_failed` de `framework.http_client`) ; la règle ne voit pas une boucle de relance écrite autour de `->request()`, refusée aussi',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/\bnew\s+(?:[\w\\\\]*\\\\)?RetryableHttpClient\(/', $c, withoutComments($c, $p)),
        ],
        'image-sans-mime' => [
            'message' => '`Image` sans `mimeTypes` : elle accepte tout `image/*`, SVG compris, qui porte du script ; la liste des types admis (`mimeTypes: [\'image/jpeg\', \'image/png\', \'image/webp\']`, ou la constante du projet)',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => refusedCalls(
                '/(?:\bnew\s+|#\[\s*|[\[,(]\s*)(?:Assert\\\\)?Image\(/',
                $c,
                withoutComments($c, $p),
                static fn (string $arguments): bool => !str_contains($arguments, 'mimeTypes'),
            ),
        ],
        'mot-de-passe-en-caracteres' => [
            'message' => 'mot de passe borné par `Length(max:)` en caractères : le hacheur compte en octets, et un mot de passe accentué passerait la validation pour lever au hachage ; `countUnit: Assert\\Length::COUNT_BYTES`',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => passwordLengthInCharacters($c),
        ],
        'succes-sans-contenu' => [
            'message' => 'succès répondu par un 200 `{ok: true}` (ou `success`, `status: ok`) : un succès sans contenu répond 204, `new Response(null, Response::HTTP_NO_CONTENT)` ; un tiers qui exige un 200 porte son exception',
            'ext' => ['php'],
            'paths' => '#^src/(?:Controller|Webhook)/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/[\'"](?:ok|success)[\'"]\s*=>\s*true\b|[\'"]status[\'"]\s*=>\s*[\'"]ok[\'"]/', $c, withoutComments($c, $p)),
        ],
        'requete-a-la-main' => [
            'message' => 'paramètre de requête ou fichier lu à la main (`$request->query->get()`, `$request->files->get()`) : un DTO et `#[MapQueryString]`, `#[MapQueryParameter]`, `#[MapUploadedFile]` ou un `UploadedFile` du `#[MapRequestPayload]` ; un CRUD d\'EasyAdmin passe',
            'ext' => ['php'],
            'paths' => '#^src/Controller/#',
            'except' => '#^src/Controller/Admin/(?:.*/)?\w*CrudController\.php$#',
            'kind' => 'ajout',
            // The reads only: `remove()` takes a parameter out before a redirect.
            'find' => static fn (string $c, string $p): array => matches('/\$request->(?:query|files)->(?:get|getString|getInt|getBoolean|getEnum|all|has)\(/', $c, withoutComments($c, $p)),
        ],
        'ancre-sans-d' => [
            'message' => 'motif `/^…$/` (ou `#^…$#`, `~^…$~`) sans le modificateur `D` (ni `m`, où `D` ne joue pas) : `$` y accepte un saut de ligne final, et « 12.50\\n » passe la validation ; `/^…$/D`, ou `\\z`',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/\'([\/#~])\^[^\']*\$\1(?![a-zA-Z]*[Dm])[a-zA-Z]*\'/', $c),
        ],
        'find-all' => [
            'message' => '`->findAll()` dans un contrôleur ou un service : sans `ORDER BY`, l\'ordre des lignes change après une simple mise à jour ; `findBy([], [...])` avec un ordre, ou une méthode de dépôt qui porte le sien (symfony-guidelines, « A query without ORDER BY has no order »)',
            'ext' => ['php'],
            'paths' => '#^src/(?:Controller|Service)/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/->findAll\(\)/', $c, withoutComments($c, $p)),
        ],
        'accord-parenthese' => [
            'message' => '« (s) » ou « (x) » collé à un mot : accorder selon le nombre, singulier à 0 et 1, pluriel au-delà (« 1 justificatif supprimé », « 3 justificatifs supprimés »)',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/\p{L}\([sx]\)/u', $c, withoutComments($c, $p)),
        ],
        'guillemets-message' => [
            'message' => 'guillemets droits autour de `%s` dans un message : « » avec leurs espaces insécables (`«\\u{a0}%s\\u{a0}»`) ; un attribut HTML (`href="%s"`) passe, comme la sortie d\'une commande, lue en console',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'except' => '#^src/Command/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/(?<![=\w])"%[sd]"/', $c, withoutComments($c, $p)),
        ],
        'guillemets-espace' => [
            'message' => 'guillemets français avec une espace ordinaire à l\'intérieur : la ligne peut se couper entre « et le mot ; l\'espace insécable (`«\\u{a0}%s\\u{a0}»` en PHP, `«&nbsp;` ou le caractère dans un gabarit)',
            'ext' => ['php', 'twig'],
            'paths' => '#^(src|templates)/#',
            'except' => '#^src/Command/#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => frenchQuotesWithSpace($c, $p),
        ],
        'milliers-espace' => [
            'message' => '`number_format()` dont le séparateur de milliers est une espace ordinaire : le nombre se coupe en fin de ligne (« 10 » puis « 000 € ») ; l\'espace fine insécable `"\\u{202f}"` ou l\'insécable `"\\u{a0}"` ; la sortie d\'une commande, lue en console, passe',
            'ext' => ['php', 'twig'],
            'paths' => '#^(src|templates)/#',
            'except' => '#^src/Command/#',
            'kind' => 'ajout',
            // Le dernier argument, en PHP comme dans le filtre Twig : `number_format(2, \',\', \' \')`.
            'find' => static fn (string $c, string $p): array => matches(
                '/\bnumber_format\s*\((?:[^()\n]|\((?:[^()\n]|\([^()\n]*\))*\))*?,\s*([\'"]) \1\s*\)/',
                $c,
                withoutComments($c, $p),
            ),
        ],
        'non-applicable' => [
            'message' => '« N/A » écrit en dur : de l\'anglais, et un texte de repli servi comme une valeur se trie et se filtre avec les vraies ; `null` côté serveur, « – » à l\'affichage',
            'ext' => ['php', 'twig'],
            'paths' => '#^(src|templates)/#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => matches('/(?:[\'"]|>\s*)N\/A(?:[\'"]|\s*<)/', $c, withoutComments($c, $p)),
        ],
        'methode-depot' => [
            'message' => '`@method` qui redit `find`, `findOneBy`, `findAll` ou `findBy` sur un dépôt : `@extends ServiceEntityRepository<Entite>` les donne déjà à PHPStan, et le `Entite[] findAll()` du générateur affaiblit le `list<Entite>` générique',
            'ext' => ['php'],
            'paths' => '#^src/Repository/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => matches('/@method\s.*\b(?:find|findOneBy|findAll|findBy)\(/', $c),
        ],
        'balise-console' => [
            'message' => 'balise de style que la console Symfony ne connaît pas : elle s\'imprime telle quelle ; `<info>`, `<comment>`, `<question>`, `<error>`, ou `<fg=green>`',
            'ext' => ['php'],
            'paths' => '#^src/Command/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('#<(?!(?:info|comment|question|error)>)([a-z]+)>[^<\n]*</\1>#', $c, withoutComments($c, $p)),
        ],
        'sommeil-test' => [
            'message' => '`sleep()` ou `usleep()` dans un test : l\'horloge simulée (`MockClock::sleep`) avance sans attendre, un client HTTP simulé répond tout de suite',
            'ext' => ['php'],
            'paths' => '#^tests/#',
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/(?<![\w>:$])u?sleep\(/', $c, withoutComments($c, $p)),
        ],
        'document-introuvable' => [
            'message' => 'document cité introuvable : corriger le chemin, écrit depuis la racine du dépôt (`docs/…`, `AGENTS.md`)',
            'ext' => ['php', 'ts', 'tsx', 'js', 'mjs', 'twig', 'yaml', 'yml', 'sh', 'neon', 'json', ''],
            'paths' => '#^(src|templates|assets|tests|e2e|config|translations|scripts|bin|\.github|\.husky)/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => missingDocuments($c),
        ],
        'gitignore-src' => [
            'message' => '`.gitignore` sous src/ : le dépôt n\'a qu\'un .gitignore de code, à la racine',
            'ext' => ['gitignore'],
            'paths' => '#^src/#',
            'kind' => 'fichier',
            'find' => static fn (string $c): array => [[1, '.gitignore']],
        ],
    ];
}
