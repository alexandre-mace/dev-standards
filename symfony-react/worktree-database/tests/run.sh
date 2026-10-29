#!/usr/bin/env bash
# The tests of worktree-database, on a repository of their own in a temporary folder.
# About a second.
#
#   symfony-react/worktree-database/tests/run.sh

set -u

WD=$(cd "$(dirname "$0")/.." && pwd)/worktree-database
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
TMP=$(cd "$TMP" && pwd -P)

failures=0
ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; failures=$((failures + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 : attendu « $3 », obtenu « $2 »"; fi; }

git init -q "$TMP/main"
git -C "$TMP/main" -c user.name=t -c user.email=t@t commit -q --allow-empty -m init
git -C "$TMP/main" worktree add -q "$TMP/main/.claude/worktrees/Fix-Thing" -b a
git -C "$TMP/main" worktree add -q "$TMP/other" -b b
mkdir -p "$TMP/main/.claude/worktrees/Fix-Thing/sub"

check 'le checkout principal garde son nom' "$("$WD" suffix "$TMP/main")" ''
check 'hors de git, rien' "$("$WD" suffix "$TMP")" ''

hash=$(printf '%s' "$TMP/main/.claude/worktrees/Fix-Thing" | shasum | cut -c1-8)
check 'un worktree lié prend son dossier et son hachage' \
    "$("$WD" suffix "$TMP/main/.claude/worktrees/Fix-Thing")" "_wt_fix_thing_$hash"
check 'le même suffixe depuis un sous-dossier' \
    "$("$WD" suffix "$TMP/main/.claude/worktrees/Fix-Thing/sub")" "_wt_fix_thing_$hash"
check 'le dossier courant par défaut' \
    "$(cd "$TMP/main/.claude/worktrees/Fix-Thing" && "$WD" suffix)" "_wt_fix_thing_$hash"
ln -s "$TMP/other" "$TMP/link"
check 'un chemin par lien symbolique donne le même hachage' \
    "$("$WD" suffix "$TMP/link")" "$("$WD" suffix "$TMP/other")"

long=$("$WD" suffix "$TMP/other")
other_db="app_test${long}"
names="app_test
app_test_wt_fix_thing_${hash}
app_test_wt_fix_thing_${hash}3
${other_db}
app_test_wt_gone_0123abcd
app_test_wt_gone_0123abcd12
autre_test_wt_gone_0123abcd
app_test_ci_branche_abc123"
check 'aucun orphelin tant que les worktrees existent' \
    "$(printf '%s\n' "$names" | (cd "$TMP/main" && "$WD" orphans app_test) | tr '\n' ' ')" \
    'app_test_wt_gone_0123abcd app_test_wt_gone_0123abcd12 '

rm -rf "$TMP/other"
check 'un worktree supprimé sans git worktree remove devient orphelin' \
    "$(printf '%s\n' "$names" | (cd "$TMP/main/.claude/worktrees/Fix-Thing" && "$WD" orphans app_test) | tr '\n' ' ')" \
    "${other_db} app_test_wt_gone_0123abcd app_test_wt_gone_0123abcd12 "

git -C "$TMP/main" worktree remove --force "$TMP/main/.claude/worktrees/Fix-Thing"
check 'un worktree retiré devient orphelin, processus ParaTest compris' \
    "$(printf '%s\n' "$names" | (cd "$TMP/main" && "$WD" orphans app_test autre_test) | tr '\n' ' ')" \
    "app_test_wt_fix_thing_${hash} app_test_wt_fix_thing_${hash}3 ${other_db} app_test_wt_gone_0123abcd app_test_wt_gone_0123abcd12 autre_test_wt_gone_0123abcd "

"$WD" orphans < /dev/null 2> /dev/null
check 'orphans sans préfixe refuse' "$?" 2
"$WD" 2> /dev/null
check 'sans commande refuse' "$?" 2

[ "$failures" -eq 0 ] && echo 'Tous les tests passent.' || { echo "$failures échec(s)."; exit 1; }
