#!/usr/bin/env bash
# The tests of ci-local-lib.sh, on a throwaway project: a git repository whose bin/ci-local
# sources the library and declares groups of fake steps, a lock of its own
# (MACHINE_LOCK_DIR), and dropdb, createdb, psql and npx replaced by stubs that write
# down their calls. No database, no PHP. About twenty seconds.
#
#   symfony-react/ci-local/tests/run.sh

set -u

LIB=$(cd "$(dirname "$0")/.." && pwd)/ci-local-lib.sh
TMP=$(mktemp -d)
export MACHINE_LOCK_DIR=$TMP/lock
unset MACHINE_LOCK_HELD MACHINE_LOCK_TIMEOUT GITHUB_ACTIONS CI_LOCAL_PHPUNIT_PROCESSES
trap 'pkill -P $$ 2> /dev/null; rm -rf "$TMP"' EXIT

failures=0
ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; failures=$((failures + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 : attendu « $3 », obtenu « $2 »"; fi; }
contains() { case "$2" in *"$3"*) ok "$1" ;; *) ko "$1 : « $3 » absent de :"; printf '%s\n' "$2" | sed 's/^/     /' ;; esac; }
lacks() { case "$2" in *"$3"*) ko "$1 : « $3 » présent" ;; *) ok "$1" ;; esac; }

# --- The throwaway project --------------------------------------------------------------

mkdir -p "$TMP/stubs"
for tool in dropdb createdb; do
    printf '#!/bin/sh\necho "%s $*" >> "%s/db.calls"\n' "$tool" "$TMP" > "$TMP/stubs/$tool"
done
printf '#!/bin/sh\necho 1\n' > "$TMP/stubs/psql"
printf '#!/bin/sh\necho "$1 $2" >> "%s/npx.calls"\necho v22.99.0\n' "$TMP" > "$TMP/stubs/npx"
chmod +x "$TMP/stubs"/*
export PATH="$TMP/stubs:$PATH"

git init -q --bare "$TMP/origin.git"
git init -q -b main "$TMP/project"
cd "$TMP/project" || exit 2
git config user.email test@example.com
git config user.name test
mkdir -p bin e2e
touch e2e/a.spec.ts

# Four groups: a php family of two, a front group, the e2e family. FAIL_AT names a step
# that fails; SLEEP_IN a step that sleeps 30 s, to interrupt the run while it sleeps.
cat > bin/ci-local <<EOF
#!/usr/bin/env bash
# A fake project.
#
#   bin/ci-local --only php   the php family
#
# Not part of the help.
#
# Nor this.
. "$LIB"
ci_group analyse 'Analyse' run_analyse php 10
ci_group unit 'Unit' run_unit php 10
ci_group front 'Front' run_front front
ci_group e2e 'E2E' run_e2e e2e
ci_only_alias unit unit
ci_parse_args "\$@"
ci_phpunit_processes auto
ci_start
on_cleanup() { echo cleanup-hook >> "$TMP/cleanup.calls"; }
fake() { # name
    [ "\${FAIL_AT:-}" != "\$1" ] || { echo "sortie de \$1 avant l'échec"; return 3; }
    [ "\${SLEEP_IN:-}" != "\$1" ] || sleep 30
    echo "\$1 at \$(date +%s.%N 2> /dev/null || date +%s)" >> "$TMP/order"
    echo "journal de \$1"
}
first_fails() { false; echo "après false"; }
run_analyse() { step 'Analyse A' fake a; signal analysed; step 'Analyse B' fake b; }
run_unit() { await analysed analyse; step 'Unit' fake unit; }
vitest() { sleep 4.5; }
run_front() {
    step 'Front' fake front
    [ -z "\${SET_E:-}" ] || step 'Deux commandes' first_fails
    [ -z "\${WATCH:-}" ] || vitest_step 'Vitest'
}
run_e2e() { step 'E2E' fake e2e; echo "specs: \${E2E_SPECS[*]-}" >> "$TMP/specs"; }
ci_context 'de travail, et rien d’autre.'
echo Préparation
step 'Dépendances' fake deps
pg_create "\$(ci_db_base demo 10)_e2e" > /dev/null
pg_reserve_copies "\$(ci_db_base demo 10)_e2e" 3
[ -z "\${NOTE:-}" ] || note "\$NOTE"
echo "processes \$PHPUNIT_PROCESSES" >> "$TMP/processes"
ci_run
EOF
chmod +x bin/ci-local
git add -A
git commit -q -m init
git remote add origin "$TMP/origin.git"
git push -q origin main

run() { : > "$TMP/order"; rm -f "$TMP/db.calls" "$TMP/cleanup.calls" "$TMP/specs"; out=$(bin/ci-local "$@" 2>&1); code=$?; }

# --- The command line -------------------------------------------------------------------

run --help
check '--help sort en 0' "$code" 0
contains "--help affiche l'en-tête du script" "$out" '--only php   the php family'
lacks "--help s'arrête au premier # nu" "$out" 'Not part of the help'
run --nope
check 'une option inconnue sort en 2' "$code" 2
run --spec e2e/absent.spec.ts
check '--spec sur un fichier absent sort en 2' "$code" 2
run --spec e2e/a.spec.ts --fast
check '--spec avec --fast est refusé' "$code" 2
run --spec e2e/a.spec.ts --only php
check '--spec avec --only est refusé' "$code" 2
run --only php --only front
check 'deux --only sont refusés' "$code" 2
run --fast --only e2e
check '--fast avec --only e2e est refusé' "$code" 2
contains '… en disant pourquoi' "$out" 'aucun groupe ne tournerait'
run --only nope
check 'une famille inconnue est refusée' "$code" 2
contains '… en citant les familles déclarées' "$out" '--only attend php, front ou e2e.'
CI_LOCAL_PHPUNIT_PROCESSES=abc run
check 'CI_LOCAL_PHPUNIT_PROCESSES non numérique sort en 2' "$code" 2
CI_LOCAL_PHPUNIT_PROCESSES=3 run --only front
check 'CI_LOCAL_PHPUNIT_PROCESSES donne le nombre de processus' "$(cat "$TMP/processes")" 'processes 3'
rm -f "$TMP/processes"

# --- A full pass ------------------------------------------------------------------------

run
check 'une passe verte sort en 0' "$code" 0
contains 'elle conclut OK' "$out" 'CI locale : OK'
contains 'elle nomme la branche et le commit' "$out" "CI locale sur main ($(git rev-parse --short HEAD))"
lacks "origin/main relu, aucun avertissement" "$out" 'Attention'
for title in Analyse Unit Front E2E; do
    contains "le groupe $title s'affiche avec sa durée" "$out" "$title ("
done
check 'chaque étape a sa ligne OK' "$(printf '%s\n' "$out" | grep -c '^  OK ')" 6
check 'la chronologie a une ligne par étape' "$(wc -l < var/ci-local/timeline | tr -d ' ')" 6
check 'la chronologie est triée par début' "$(cut -f1 var/ci-local/timeline | sort -c 2>&1 && echo triée)" 'triée'
[ -f var/ci-local/analyse-02-fake.log ] && ok 'les journaux sont nommés <groupe>-<numéro>-<fonction>.log' || ko 'journal analyse-02-fake.log absent'
check "les fichiers de travail sont retirés à la fin" "$(ls var/ci-local | grep -cE '\.(done|current|start|title|out|pid)$')" 0
before=$(grep -n '^a at' "$TMP/order" | cut -d: -f1)
after=$(grep -n '^unit at' "$TMP/order" | cut -d: -f1)
[ "$before" -lt "$after" ] && ok "un rendez-vous attend le repère de l'autre groupe" || ko "unit a tourné avant le repère d'analyse"
calls=$(cat "$TMP/db.calls")
db=$(cd "$TMP/project" && bash -c ". '$LIB'; BRANCH=main; ci_db_base demo 10")
check 'ci_db_base nomme <préfixe>_ci_<branche>_<hachage>' "$(printf '%s' "$db" | sed 's/_[0-9a-f]\{6\}$/_hash/')" 'demo_ci_main_hash'
contains 'le nettoyage supprime la base créée' "$calls" "dropdb -h 127.0.0.1 -p 5432 -U $(whoami) --if-exists --force ${db}_e2e"
contains '… et les copies réservées pour les workers' "$calls" "${db}_e2e_w2"
check "le crochet on_cleanup du projet est appelé" "$(cat "$TMP/cleanup.calls")" cleanup-hook

# --- Selection --------------------------------------------------------------------------

run --fast
contains '--fast garde les autres groupes' "$out" 'Front ('
lacks '--fast écarte la famille e2e' "$out" 'E2E ('
run --only php
contains '--only php lance la famille php' "$out" 'Unit ('
lacks '… et rien d’autre' "$out" 'Front ('
run --only unit
contains 'un ancien nom lance encore son seul groupe' "$out" 'Unit ('
lacks '… sans le reste de sa famille' "$out" 'Analyse ('
contains '… et le début du passage nomme la famille qui le remplace' "$out" '« --only unit » est un ancien nom, gardé pour l'"'"'habitude : --only php, commun aux projets, le remplace (et lance aussi : Analyse).'
run --spec e2e/a.spec.ts --spec e2e/a.spec.ts
lacks '--spec ne lance que la famille e2e' "$out" 'Front ('
check '--spec passe des chemins absolus, répétables' "$(cat "$TMP/specs")" "specs: $TMP/project/e2e/a.spec.ts $TMP/project/e2e/a.spec.ts"

# --- Vitest, sampled -------------------------------------------------------------------

WATCH=1 run --only front
contains 'Vitest porte la version de Node de la CI' "$out" '(Node v22.99.0)'
contains 'une ligne dit ce qui tournait à côté de Vitest' "$out" 'À côté de Vitest (%CPU moyen sur'
[ -s var/ci-local/vitest-cpu.tsv ] && ok 'les relevés sont gardés dans var/ci-local/vitest-cpu.tsv' || ko 'vitest-cpu.tsv vide'
check 'sans .nvmrc, la majeure de Node est 22' "$(tail -n 1 "$TMP/npx.calls")" '-y node@22'
echo 24 > .nvmrc
WATCH=1 run --only front
check 'la majeure de Node est celle du .nvmrc du projet' "$(tail -n 1 "$TMP/npx.calls")" '-y node@24'
WATCH=1 CI_NODE_MAJOR=20 run --only front
check 'CI_NODE_MAJOR l'"'"'emporte sur .nvmrc' "$(tail -n 1 "$TMP/npx.calls")" '-y node@20'
rm .nvmrc

# --- Failures ---------------------------------------------------------------------------

FAIL_AT=a run
check 'une étape rouge fait sortir en 1' "$code" 1
contains 'elle conclut ÉCHEC' "$out" 'CI locale : ÉCHEC'
contains "la ligne ÉCHEC nomme l'étape" "$out" 'ÉCHEC'
contains 'la fin de son journal suit' "$out" "sortie de a avant l'échec"
lacks 'son groupe s’arrête' "$out" 'Analyse B'
contains "le rendez-vous sur un groupe arrêté ne bloque pas" "$out" 'Unit ('
contains 'les autres groupes vont au bout' "$out" 'E2E ('
SET_E=1 run --only front
check "une étape de plusieurs commandes échoue à la première (set -e)" "$code" 1
lacks '… sans exécuter la suite' "$(cat var/ci-local/front-02-first_fails.log)" 'après false'
FAIL_AT=deps run
check 'un échec de la préparation sort en 1' "$code" 1
lacks '… sans lancer de groupe' "$out" 'Front ('
NOTE='rien de tel ici' run --only front
contains 'les notes vont au résumé' "$out" "Non couvert en local :
  - rien de tel ici"

# --- Interrupted ------------------------------------------------------------------------

: > "$TMP/order"
rm -f "$TMP/db.calls"
SLEEP_IN=front bin/ci-local --only front > "$TMP/interrupted.log" 2>&1 &
runner=$!
for i in $(seq 50); do
    pgrep -f 'sleep 30' > /dev/null && break
    sleep 0.2
done
pkill -TERM -o -f "bash bin/ci-local --only front"
wait "$runner"
check 'interrompu, le passage sort en 130' "$?" 130
sleep 0.5
pgrep -f 'sleep 30' > /dev/null && ko "l'étape interrompue tourne encore" || ok "les processus du groupe sont arrêtés"
contains '… et ses bases supprimées' "$(cat "$TMP/db.calls" 2> /dev/null)" 'dropdb'

# --- The machine lock -------------------------------------------------------------------

ML=$(dirname "$LIB")/../machine-lock/machine-lock
"$ML" run -- sleep 3 &
holder=$!
sleep 0.5
start=$(date +%s)
run --only front
waited=$(($(date +%s) - start))
[ "$waited" -ge 2 ] && ok "le passage attend la machine tenue par un autre" || ko "le passage n'a pas attendu le verrou ($waited s)"
check '… puis tourne' "$code" 0
wait "$holder"
GITHUB_ACTIONS=true run --only front
check "sur un runner GitHub, pas de verrou" "$code" 0

echo
if [ "$failures" -gt 0 ]; then
    echo "$failures test(s) en échec."
    exit 1
fi
echo "Tous les tests passent."
