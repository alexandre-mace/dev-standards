#!/usr/bin/env bash
# The tests of deploy, on throwaway repositories (a bare origin, a clone, a worktree) and
# a fake clever that answers from files the tests write. Nothing leaves the temporary
# folder. About twenty seconds.
#
#   symfony-react/deploy/tests/run.sh

set -u

DEPLOY=$(cd "$(dirname "$0")/.." && pwd)/deploy
TMP=$(cd "$(mktemp -d)" && pwd -P)
trap 'rm -rf "$TMP"' EXIT

export GIT_CONFIG_GLOBAL=$TMP/gitconfig GIT_CONFIG_NOSYSTEM=1
git config --global user.name Test
git config --global user.email test@example.invalid
git config --global init.defaultBranch main
export DEPLOY_POLL_INTERVAL=1

# The fake clever: `clever activity --app <id> --format json`. For each app, the state
# file says what the next deployment of its branch's tip looks like (none, WIP, OK, FAIL,
# error); a fixed file, when present, is printed as is.
mkdir -p "$TMP/bin"
cat > "$TMP/bin/clever" << 'EOF'
#!/usr/bin/env bash
app=$3
echo "$app" >> "$FAKE/calls"
[ -f "$FAKE/fixed-$app" ] && { cat "$FAKE/fixed-$app"; exit 0; }
state=$(cat "$FAKE/state-$app" 2> /dev/null || echo none)
[ "$state" = error ] && { echo "not logged in" >&2; exit 1; }
[ "$state" = none ] && { echo '[]'; exit 0; }
branch=main; [ "$app" = app_preprod ] && branch=preprod
commit=$(git -C "$FAKE/origin.git" rev-parse "$branch")
printf '[{"date":%s000,"state":"%s","action":"DEPLOY","commit":"%s","cause":"github"}]\n' "$(date +%s)" "$state" "$commit"
EOF
chmod +x "$TMP/bin/clever"
export PATH=$TMP/bin:$PATH

failures=0
ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; failures=$((failures + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 : attendu « $3 », obtenu « $2 »"; fi; }
contains() { case "$2" in *"$3"*) ok "$1" ;; *) ko "$1 : « $3 » absent de la sortie"; echo "$2" | sed 's/^/     /' ;; esac; }

# A fresh origin with main and preprod, a clone on a feature branch one commit ahead.
fresh() {
    export FAKE=$TMP/case$((++CASE))
    mkdir -p "$FAKE"
    git init -q --bare "$FAKE/origin.git"
    git clone -q "$FAKE/origin.git" "$FAKE/project" 2> /dev/null
    cd "$FAKE/project" || exit 2
    cat > .clever.json << 'EOF'
{"apps": [
  {"app_id": "app_prod", "alias": "PROD-site"},
  {"app_id": "app_preprod", "alias": "preprod-site"}
]}
EOF
    printf 'var/\n' > .gitignore
    git add . && git commit -qm init && git push -q origin main 2> /dev/null
    git push -q origin main:preprod 2> /dev/null
    git checkout -qb feature
    echo change > change.txt && git add change.txt && git commit -qm change
}
CASE=0
origin_main() { git -C "$FAKE/origin.git" rev-parse main; }

# --- Refusals before any push -----------------------------------------------------------

fresh
git checkout -q main
out=$("$DEPLOY" --timeout 10 2>&1); code=$?
check 'sur main, refusé' $code 2
git checkout -q feature

echo dirty >> change.txt
before=$(origin_main)
out=$("$DEPLOY" --timeout 10 2>&1); code=$?
check 'des changements non commités, refusé' $code 2
check '  et rien n'"'"'est poussé' "$(origin_main)" "$before"
git checkout -q change.txt

git clone -q "$FAKE/origin.git" "$FAKE/other" 2> /dev/null
(cd "$FAKE/other" && echo other > other.txt && git add other.txt && git commit -qm "commit d'ailleurs" && git push -q origin main 2> /dev/null)
before=$(origin_main)
out=$("$DEPLOY" --timeout 10 2>&1); code=$?
check 'origin/main en avance sur la branche, refusé' $code 2
contains '  et le commit absent est nommé' "$out" "commit d'ailleurs"
check '  et rien n'"'"'est poussé' "$(origin_main)" "$before"

# --- The deployment followed to its verdict ---------------------------------------------

fresh
echo OK > "$FAKE/state-app_prod"
out=$("$DEPLOY" --timeout 10 2>&1); code=$?
check 'déploiement OK, code 0' $code 0
check '  main porte la branche' "$(origin_main)" "$(git rev-parse HEAD)"
check "  l'application suivie est celle dont l'alias commence par prod, sans casse" "$(sort -u "$FAKE/calls")" app_prod
contains '  le rappel Sentry est donné' "$out" 'Sentry'

fresh
echo FAIL > "$FAKE/state-app_prod"
"$DEPLOY" --timeout 10 > /dev/null 2>&1
check 'déploiement en échec, code 1' $? 1

fresh
echo WIP > "$FAKE/state-app_prod"
"$DEPLOY" --timeout 2 > /dev/null 2>&1
check 'pas de verdict dans le délai, code 3' $? 3

fresh
printf '[{"date":%s000,"state":"OK","action":"DEPLOY","commit":"%s"}]\n' $(($(date +%s) - 3600)) "$(git rev-parse HEAD)" > "$FAKE/fixed-app_prod"
"$DEPLOY" --timeout 2 > /dev/null 2>&1
check "un déploiement de ce commit antérieur au push ne compte pas" $? 3

fresh
echo error > "$FAKE/state-app_prod"
out=$("$DEPLOY" --timeout 30 2>&1); code=$?
check 'clever en erreur trois fois, code 3 sans attendre le délai' $code 3
contains '  et il le dit' "$out" 'clever login'

fresh
printf '[{"date":%s000,"state":"OK","action":"DEPLOY","commit":"%s"}]\n' "$(date +%s)" "$(git rev-parse HEAD)" > "$FAKE/fixed-app_preprod"
mkdir -p sub && cd sub
"$DEPLOY" --app preprod-site --timeout 10 > /dev/null 2>&1
check "--app choisit l'application par son alias, lancé depuis un sous-dossier" "$?:$(sort -u "$FAKE/calls")" 0:app_preprod
cd ..

# --- preprod brought back in line ------------------------------------------------------

fresh
echo OK > "$FAKE/state-app_prod"
echo OK > "$FAKE/state-app_preprod"
# preprod holds the feature through a merge, as /preprod leaves it.
git clone -q -b preprod "$FAKE/origin.git" "$FAKE/pp" 2> /dev/null
git push -q origin feature 2> /dev/null
(cd "$FAKE/pp" && git fetch -q origin feature && git merge -q --no-ff -m "Merge feature into preprod" FETCH_HEAD && git push -q origin preprod 2> /dev/null)
old_preprod=$(git -C "$FAKE/origin.git" rev-parse preprod)
out=$("$DEPLOY" --preprod --timeout 10 2>&1); code=$?
check 'preprod sans rien d’unique, alignée, code 0' $code 0
check '  son contenu est celui de main' "$(git -C "$FAKE/origin.git" rev-parse preprod^{tree})" "$(git -C "$FAKE/origin.git" rev-parse main^{tree})"
git -C "$FAKE/origin.git" merge-base --is-ancestor "$old_preprod" preprod
check '  sans forcer : l’ancienne preprod reste dans son historique' $? 0

fresh
echo OK > "$FAKE/state-app_prod"
git clone -q -b preprod "$FAKE/origin.git" "$FAKE/pp" 2> /dev/null
(cd "$FAKE/pp" && echo test > recette.txt && git add recette.txt && git commit -qm "recette en cours" && git push -q origin preprod 2> /dev/null)
old_preprod=$(git -C "$FAKE/origin.git" rev-parse preprod)
out=$("$DEPLOY" --preprod --timeout 10 2>&1); code=$?
check 'preprod porte un commit unique : déployé mais code 4' $code 4
contains '  le commit est nommé' "$out" 'recette en cours'
check '  preprod est intacte' "$(git -C "$FAKE/origin.git" rev-parse preprod)" "$old_preprod"
check '  main est bien déployée' "$(origin_main)" "$(git rev-parse HEAD)"

# --- The worktree removed once deployed -------------------------------------------------

fresh
echo OK > "$FAKE/state-app_prod"
git checkout -q main
git worktree add -q "$FAKE/project/.claude/worktrees/lot" -b lot 2> /dev/null
cd "$FAKE/project/.claude/worktrees/lot" || exit 2
echo lot > lot.txt && git add lot.txt && git commit -qm lot
mkdir -p var && echo cache > var/cache.txt
out=$("$DEPLOY" --remove-worktree --timeout 10 2>&1); code=$?
cd "$FAKE/project" || exit 2
check 'worktree retiré une fois déployé, code 0' $code 0
check '  son dossier a disparu, fichiers ignorés compris' "$([ -e .claude/worktrees/lot ] && echo présent || echo absent)" absent
check '  sa branche locale aussi' "$(git branch --list lot)" ''

fresh
echo OK > "$FAKE/state-app_prod"
out=$("$DEPLOY" --remove-worktree --timeout 10 2>&1); code=$?
check 'depuis le checkout principal, rien à retirer, code 0' $code 0
check '  la branche reste' "$(git branch --show-current)" feature

echo
if [ "$failures" -gt 0 ]; then
    echo "$failures test(s) en échec."
    exit 1
fi
echo "Tous les tests passent."
