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
 * holds old ones. An exception lives in the code, on the line above the occurrence or
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
 * (prefectoral orders, official texts), not the project's own prose.
 */
const SKIPPED = '#^(docs/gap-analysis\.md$|data/|assets/components/ui/|assets/lib/api/|public/build/|vendor/|node_modules/)|(^|/)(composer\.lock|symfony\.lock|pnpm-lock\.yaml|package-lock\.json|yarn\.lock|openapi\.yaml)$#';

/** An exception within these first lines of a file covers the whole file. */
const FILE_HEADER_LINES = 3;

const DIRECTIVE = 'check-mechanical-ignore';

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
    $added = addedLines(git('diff --cached -U0 --no-color --no-ext-diff --diff-filter=ACMR'));
    // A merge: a line is ours only if it is new to both parents, otherwise the lines
    // brought by the other branch would count as added here.
    $mergeHead = trim(git('rev-parse -q --verify MERGE_HEAD', quiet: true));
    if ('' !== $mergeHead) {
        $fromOther = addedLines(git('diff --cached -U0 --no-color --no-ext-diff --diff-filter=ACMR '.escapeshellarg($mergeHead)));
        foreach ($added as $path => $lines) {
            $added[$path] = array_intersect_key($lines, $fromOther[$path] ?? []);
        }
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
        $added = addedLines(git('diff -U0 --no-color --no-ext-diff '.escapeshellarg($mergeBase)));
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
            if ($covered || ('ajout' === $rule['kind'] && null !== $added && !isset($added[$path][$line]))) {
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
        echo \sprintf("%-20s %-8s %s\n", $id, $rule['kind'], $rule['message']);
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
    $searched = 'md' === extension($path) ? withoutCode($content) : $content;
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
function blank(string $content, string $pattern): string
{
    return (string) preg_replace_callback($pattern, static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]), $content);
}

/**
 * The content without its comments, by language. `//` counts only at the start of a line
 * or after a space: the one of a URL (`https://`) follows a colon.
 */
function withoutComments(string $content, string $path): string
{
    return match (extension($path)) {
        'php' => blank(blank($content, '#/\*.*?\*/#s'), '#(?<=^|\s)(?://|\#(?!\[)).*$#m'),
        'ts', 'tsx', 'js', 'mjs' => blank(blank($content, '#/\*.*?\*/#s'), '#(?<=^|\s)//.*$#m'),
        'twig' => blank($content, '/\{#.*?#\}|<!--.*?-->/s'),
        'yaml', 'yml', 'neon', 'sh', '' => blank($content, '/(?<=^|\s)#.*$/m'),
        'md' => withoutCode($content),
        default => $content,
    };
}

/** A Markdown file without its code blocks and spans, where a name from the code is written as is. */
function withoutCode(string $content): string
{
    return blank($content, '/^[ \t]*(```|~~~).*?^[ \t]*\1|`[^`\n]+`/ms');
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
 * (« Annonces – La Grange »), an aside commas.
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
    $dash = '[\p{L}\d][\p{L}\d.,)»%€]* - [\p{L}\d«(]';
    if (str_ends_with($path, '.twig')) {
        // The text of a `<code>` is a name, quoted as it is written elsewhere.
        $text = blank($content, '/\{#.*?#\}|\{\{.*?\}\}|\{%.*?%\}|<script\b.*?<\/script>|<style\b.*?<\/style>|<code\b.*?<\/code>|<[a-zA-Z!\/][^>]*>/s');

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
 * Each named class declaration that is neither `final` nor `abstract`.
 *
 * @return list<array{int, string}>
 */
function openClasses(string $content): array
{
    $code = withoutComments($content, 'a.php');
    $out = [];
    foreach (matches('/^[ \t]*((?:(?:final|abstract|readonly)\s+)*)class\s+\w+/m', $content, $code) as [$line, $excerpt]) {
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
            'message' => 'tiret long : une incise prend des virgules, une explication les deux-points (seul, entre guillemets ou dans une cellule, il dit une case vide et passe)',
            'ext' => $prose,
            'paths' => $everywhere,
            'kind' => 'fichier',
            // The entities and the escape are written here without their whole name,
            // otherwise this file would refuse itself. The dash alone (`'\x{2014}'`,
            // `>\x{2014}<`, a JSX line holding only it) is the fallback of an empty cell.
            'find' => static fn (string $c): array => matches(
                '/\x{2014}|&(?:mdash|#0*8212|#x0*2014);|\\\\u2014/ui',
                $c,
                blank($c, '/([\'"`])(?:\x{2014}|\\\\u2014)\1|>\s*\x{2014}\s*<|^[ \t]*\x{2014}[ \t]*$/um'),
            ),
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
                'md' === extension($p) ? withoutCode($c) : $c,
            ),
        ],
        'trait-union' => [
            'message' => 'trait d\'union entouré d\'espaces dans un texte affiché : tiret moyen (« 10–20 ha », « Annonces – Site ») ou virgules',
            'ext' => ['tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
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
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/[\x{0300}-\x{036F}]/u', $c),
        ],
        'guillemets-anglais' => [
            'message' => 'guillemets anglais dans un texte affiché : « » avec leurs espaces insécables (« quotidien »)',
            'ext' => ['tsx', 'twig'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/[\x{201C}\x{201D}]/u', $c),
        ],
        'guillemets-droits' => [
            'message' => 'guillemets droits autour d\'un texte affiché : « » avec leurs espaces insécables (`«\\u00a0${nom}\\u00a0»`)',
            'ext' => ['tsx'],
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'ajout',
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
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/\bA\s(?:qui|noter|toi|titre|cette|quel|quelle|partir\sde)\b|\bE(?:labor\w*|talons?)\b/u', $c),
        ],
        'ponctuation-detachee' => [
            'message' => 'balise fermante seule sur sa ligne et suivie de la ponctuation : le retour à la ligne se rend en espace soulignée (« lien . ») ; `{#- … -#}` en fin de ligne au-dessus, ou `-}}`',
            'ext' => ['twig'],
            'paths' => '#^templates/#',
            'kind' => 'ajout',
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
            'find' => static fn (string $c): array => matches(
                '/\b\d{1,2}(?:e|\x{1D49}|ème|th)\s+(?:passe|gap-analysis\spass)\b|\bpasses?\s+\d{1,2}\b|\bfindings?\s+#?\d|\bconstats?\s+(?:n°\s*)?#?\d'
                .'|(?i:\bgap[\s-]analysis)\s+(?:du\s+|de\s+|d\x{2019}|d\')?\(?(?:\d{4}-\d{2}|\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}|(?:\d{1,2}(?:er)?\s+)?(?:janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\s+\d{4})/u',
                $c,
            ),
        ],

        // The design system and the kit.
        'couleur-brute' => [
            'message' => 'couleur Tailwind de la palette brute : un jeton du design system',
            'ext' => $front,
            'paths' => $ui,
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/(?<![-\w])(?:[\w-]+:)*(?:'.$utility.')-(?:'.$palette.')-\d{2,3}\b/', $c),
        ],
        'rgba' => [
            'message' => 'couleur rgb()/rgba() en littéral : un jeton, avec son opacité (`bg-primary/20`, `color-mix`)',
            'ext' => $front,
            'paths' => $ui,
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/(?<![a-zA-Z])rgba?\(\s*\d/', $c),
        ],
        'blanc-noir' => [
            'message' => '`white`/`black` posé hors jetons, en classe ou dans un style : `bg-card`, `text-foreground`, `text-primary-foreground`, `var(--primary-foreground)`…',
            'ext' => $front,
            'paths' => $ui,
            'kind' => 'ajout',
            // The Tailwind classes, then a colour property written in an inline style, a CSS
            // string or a React `style` object (`color: white`, `backgroundColor: 'black'`).
            'find' => static fn (string $c): array => matches(
                '/(?<![-\w\[])(?:[\w-]+:)*(?:bg|text|fill|stroke|ring|border|from|to|via|divide|outline|decoration)-(?:white|black)\b'
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
            'kind' => 'ajout',
            'find' => static fn (string $c): array => matches('/(?<![-\w\]:&>*_\/])(?:[\w-]+:)*(?:fill|stroke)-(?!none\b)[a-z][\w\/-]*+(?!\s*=)|(?<![-\w])fill\s*:\s*[\'"]/', $c),
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
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c, string $p): array => matches('/<label\b/', $c, withoutComments($c, $p)),
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

        // What the page loads and opens.
        'target-blank' => [
            'message' => '`target="_blank"` sans `rel="noopener noreferrer"` dans la même balise ou le même objet : la page ouverte garderait une prise sur celle-ci et saurait d\'où elle vient',
            // Not PHP, which assembles its tags piece by piece.
            'ext' => $markup,
            'paths' => $ui,
            'except' => $tests,
            'kind' => 'fichier',
            'find' => static fn (string $c): array => blankWithoutRel($c),
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
            'message' => 'identifiant de route sans borne (`Requirement::DIGITS`, `\d+`, `[0-9]+`) : `99999999999` atteint PostgreSQL, qui refuse l\'int4 et répond 500, borner à neuf chiffres (`[1-9][0-9]{0,8}`)',
            'ext' => ['php'],
            'paths' => '#^src/#',
            'kind' => 'ajout',
            'find' => static fn (string $c, string $p): array => matches('/Requirement::DIGITS|=>\s*\'(?:\\\\d|\[0-9\])\+\'/', $c, withoutComments($c, $p)),
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
            'message' => 'fichier PHP ajouté sans `declare(strict_types=1);`',
            'ext' => ['php'],
            'paths' => '#^src/#',
            // Reported on the first line, which only a new file adds.
            'kind' => 'ajout',
            'find' => static fn (string $c): array => preg_match('/^declare\(strict_types=1\);/m', $c) ? [] : [[1, '<?php']],
        ],
        'classe-finale' => [
            'message' => 'classe ajoutée ni `final` ni `abstract` : une classe se ferme, l\'héritage se décide',
            'ext' => ['php'],
            'paths' => '#^src/#',
            // Doctrine extends the entities for its lazy proxies.
            'except' => '#^src/Entity/#',
            'kind' => 'ajout',
            'find' => static fn (string $c): array => openClasses($c),
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
