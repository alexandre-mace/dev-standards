#!/usr/bin/env bash
# The tests of sync-main-checkout, on throwaway repositories: a bare origin, the main
# checkout cloned from it, a worktree of that checkout, and a second clone that pushes.
# Nothing leaves the temporary folder. A few seconds.
#
#   symfony-react/sync-main-checkout/tests/run.sh

set -u

SYNC=$(cd "$(dirname "$0")/.." && pwd)/sync-main-checkout
TMP=$(cd "$(mktemp -d)" && pwd -P)
trap 'rm -rf "$TMP"' EXIT

export GIT_CONFIG_GLOBAL=$TMP/gitconfig GIT_CONFIG_NOSYSTEM=1
git config --global user.name Test
git config --global user.email test@example.invalid
git config --global init.defaultBranch main

failures=0
ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; failures=$((failures + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 : attendu « $3 », obtenu « $2 »"; fi; }
contains() { case "$2" in *"$3"*) ok "$1" ;; *) ko "$1 : « $3 » absent de la sortie"; echo "$2" | sed 's/^/     /' ;; esac; }

# A fresh origin, the main checkout on main with a worktree, and origin one commit ahead
# (touching the file the second argument names, change.txt by default).
CASE=0
fresh() {
    C=$TMP/case$((++CASE))
    mkdir -p "$C"
    git init -q --bare "$C/origin.git"
    git clone -q "$C/origin.git" "$C/main" 2> /dev/null
    (cd "$C/main" && echo init > README && git add README && git commit -qm init && git push -q origin main 2> /dev/null)
    git -C "$C/main" remote set-head origin main > /dev/null
    git -C "$C/main" worktree add -q "$C/main/.claude/worktrees/lot" -b lot 2> /dev/null
    git clone -q "$C/origin.git" "$C/other" 2> /dev/null
    (cd "$C/other" && echo new > "${1:-change.txt}" && git add . && git commit -qm suivant && git push -q origin main 2> /dev/null)
}
origin_main() { git -C "$C/origin.git" rev-parse main; }
head_of() { git -C "$1" rev-parse HEAD; }

fresh
out=$(cd "$C/main/.claude/worktrees/lot" && "$SYNC" 2>&1); code=$?
check 'depuis un worktree : le dossier principal avance, code 0' "$code:$(head_of "$C/main")" "0:$(origin_main)"
contains '  le nombre de commits est dit' "$out" 'avancé de 1 commit'
check '  le worktree, lui, ne bouge pas' "$(git -C "$C/main/.claude/worktrees/lot" branch --show-current)" lot

fresh
out=$(cd "$C/main" && "$SYNC" --hook 2>&1)
check 'depuis le dossier principal lui-même, --hook' "$(head_of "$C/main")" "$(origin_main)"
out=$(cd "$C/main" && "$SYNC" --hook 2>&1)
check '  à jour ensuite, --hook ne dit rien' "$out" ''

fresh AGENTS.md
out=$(cd "$C/main" && "$SYNC" 2>&1)
contains 'AGENTS.md changé : la relecture est demandée' "$out" 'Instructions changées (AGENTS.md )'

fresh composer.lock
out=$(cd "$C/main" && "$SYNC" 2>&1)
contains 'composer.lock changé : la réinstallation est demandée' "$out" 'Dépendances changées (composer.lock )'

fresh
before=$(head_of "$C/main")
echo modifié > "$C/main/README"
out=$(cd "$C/main/.claude/worktrees/lot" && "$SYNC" 2>&1); code=$?
check 'modification non commitée : pas avancé, code 0' "$code:$(head_of "$C/main")" "0:$before"
contains '  la raison est dite' "$out" 'modifications non commitées'
check '  la modification est intacte' "$(cat "$C/main/README")" modifié

fresh
before=$(head_of "$C/main")
echo à-moi > "$C/main/change.txt"
out=$(cd "$C/main" && "$SYNC" 2>&1)
check 'un fichier non suivi que l’avance écraserait : pas avancé' "$(head_of "$C/main")" "$before"
check '  le fichier est intact' "$(cat "$C/main/change.txt")" à-moi
contains '  la raison est dite' "$out" 'non suivis'

fresh
git -C "$C/main" checkout -qb ailleurs
out=$(cd "$C/main" && "$SYNC" 2>&1)
check 'sur une autre branche : main n’avance pas' "$(git -C "$C/main" rev-parse main)" "$(git -C "$C/main" rev-parse ailleurs)"
contains '  la raison est dite' "$out" 'il est sur ailleurs'

fresh
(cd "$C/main" && echo local > local.txt && git add local.txt && git commit -qm local)
before=$(head_of "$C/main")
out=$(cd "$C/main" && "$SYNC" 2>&1)
check 'main local porte un commit absent d’origin : pas avancé' "$(head_of "$C/main")" "$before"
contains '  la raison est dite' "$out" '1 commit(s) absent(s)'

fresh
git -C "$C/main" remote set-url origin "$TMP/nulle-part.git"
out=$(cd "$C/main" && "$SYNC" --hook 2>&1); code=$?
check 'fetch impossible : code 0, une ligne' "$code" 0
contains '  la raison est dite' "$out" 'git fetch origin a échoué'

fresh
before=$(head_of "$C/main")
git -C "$C/main" fetch -q origin
out=$(cd "$C/main" && "$SYNC" --no-fetch 2>&1)
check '--no-fetch avance sur ce qui est déjà récupéré' "$(head_of "$C/main")" "$(origin_main)"

mkdir -p "$TMP/hors-depot"
out=$(cd "$TMP/hors-depot" && "$SYNC" --hook 2>&1); code=$?
check 'hors dépôt, --hook : code 0, silencieux' "$code:$out" '0:'
(cd "$TMP/hors-depot" && "$SYNC" > /dev/null 2>&1)
check 'hors dépôt sans --hook : code 2' $? 2

echo
if [ "$failures" -eq 0 ]; then echo "Tous les tests passent."; else echo "$failures test(s) en échec."; exit 1; fi
