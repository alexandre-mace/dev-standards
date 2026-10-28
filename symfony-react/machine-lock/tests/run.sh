#!/usr/bin/env bash
# The tests of machine-lock, on a lock of their own (MACHINE_LOCK_DIR in a temporary
# folder), so they never wait on nor block a real run. About thirty seconds.
#
#   symfony-react/machine-lock/tests/run.sh

set -u

ML=$(cd "$(dirname "$0")/.." && pwd)/machine-lock
TMP=$(mktemp -d)
export MACHINE_LOCK_DIR=$TMP/lock
unset MACHINE_LOCK_HELD MACHINE_LOCK_TIMEOUT GITHUB_ACTIONS
trap 'pkill -P $$ 2> /dev/null; rm -rf "$TMP"' EXIT
cd "$TMP" || exit 2

failures=0
ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; failures=$((failures + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 : attendu « $3 », obtenu « $2 »"; fi; }

# Waits until the lock is held, so a test never races its own holder's start.
wait_held() {
    local i
    for i in $(seq 50); do
        case "$("$ML" status)" in "tenu par"*) return 0 ;; esac
        sleep 0.1
    done
    ko "le détenteur n'a jamais pris le verrou"
}

# --- The command's status comes back ---------------------------------------------------

"$ML" run -- true
check 'une commande réussie rend 0' $? 0
"$ML" run -- sh -c 'exit 7'
check "une commande en échec rend son code" $? 7
"$ML" run sh -c 'exit 4'
check 'le -- est facultatif' $? 4
check 'le verrou est libre après coup' "$("$ML" status)" libre

# --- Three concurrent holders follow one another ----------------------------------------

log=$TMP/concurrent.log
for name in a b c; do
    "$ML" run -- sh -c "echo début $name >> '$log'; sleep 1; echo fin $name >> '$log'" 2> /dev/null &
done
wait
sequence=$(awk 'NR % 2 == 1 { id = $2; next } { printf "%s", ($2 == id ? "." : "x") }' "$log")
check 'trois détenteurs concurrents se suivent, sans chevauchement' "$sequence" '...'
check 'chacun a tourné' "$(grep -c '^fin' "$log")" 3

# --- A dead holder does not keep the lock -----------------------------------------------

"$ML" run -- sleep 30 &
holder=$!
wait_held
orphan=$(pgrep -P "$holder")
kill -9 "$holder"
wait "$holder" 2> /dev/null
MACHINE_LOCK_TIMEOUT=2 "$ML" run -- true 2> /dev/null
check "un détenteur tué (kill -9) rend le verrou, sa commande orpheline n'en héritant pas" $? 0
check "sa fiche restée sur le disque ne le fait pas croire tenu" "$("$ML" status)" libre
kill "$orphan" 2> /dev/null

# --- Ctrl-C: the lock comes back and the status is the command's -----------------------

set -m
"$ML" run -- sleep 30 &
holder=$!
set +m
wait_held
kill -INT -- "-$holder"
wait "$holder"
check 'Ctrl-C sur le groupe rend 130' $? 130
check 'et rend le verrou' "$("$ML" status)" libre

# --- Waiting, bounded -------------------------------------------------------------------

"$ML" run -- sleep 4 &
holder=$!
wait_held
start=$(date +%s)
err=$(MACHINE_LOCK_TIMEOUT=1 "$ML" run -- true 2>&1)
code=$?
check "l'attente bornée sort en 75" "$code" 75
case "$err" in
    *"verrou toujours tenu après"*"hors dépôt ($TMP)"*"sleep 4"*) ok "le message nomme le détenteur, son dossier et sa commande" ;;
    *) ko "message de délai inattendu : $err" ;;
esac
[ $(($(date +%s) - start)) -le 3 ] && ok "le délai est tenu" || ko "le délai n'est pas tenu"
lines=$(MACHINE_LOCK_TIMEOUT=3 "$ML" run -- true 2>&1 | grep -c 'en attente')
check "hors terminal, une seule ligne d'attente tant que le détenteur ne change pas" "$lines" 1

# --- A nested run in the same chain does not wait on itself -----------------------------

wait "$holder"
start=$(date +%s)
MACHINE_LOCK_TIMEOUT=2 "$ML" run -- "$ML" run -- sh -c 'exit 3'
check "un appel imbriqué passe et rend le code de la commande" $? 3
MACHINE_LOCK_TIMEOUT=2 "$ML" run -- "$ML" held
check '« held » répond oui sous le verrou' $? 0
"$ML" held
check '« held » répond non hors du verrou' $? 1

"$ML" run -- sleep 4 &
holder=$!
wait_held
MACHINE_LOCK_HELD="$MACHINE_LOCK_DIR/lock:$holder" MACHINE_LOCK_TIMEOUT=1 "$ML" run -- true 2> /dev/null
check "une variable MACHINE_LOCK_HELD héritée d'ailleurs ne passe pas le verrou" $? 75

# --- No lock on a hosted runner ---------------------------------------------------------

GITHUB_ACTIONS=true MACHINE_LOCK_TIMEOUT=1 "$ML" run -- sh -c 'exit 5'
check "sur GitHub Actions, la commande tourne sans attendre le verrou" $? 5
CI=true MACHINE_LOCK_TIMEOUT=1 "$ML" run -- true 2> /dev/null
check "CI=true seul (bin/ci-local le pose) ne lève pas le verrou" $? 75
wait "$holder"

# --- Waiters are served in order of arrival ---------------------------------------------

# Each waiter retries every second from its own start: a holder ending half a second into
# that cycle lets a later arrival retry first, which is what the queue must not reward.
order=$TMP/order.log
"$ML" run -- sleep 2.5 &
holder=$!
wait_held
for name in a b c d; do
    "$ML" run -- sh -c "echo $name >> '$order'" 2> /dev/null &
    sleep 0.3
done
wait
check "les attentes passent dans l'ordre d'arrivée" "$(tr -d '\n' < "$order")" abcd

# --- The status lists the queue, and a waiter knows its place ---------------------------

"$ML" run -- sleep 4 &
holder=$!
wait_held
"$ML" run -- sleep 0 2> /dev/null &
first=$!
sleep 0.5
second_err=$TMP/second.err
MACHINE_LOCK_TIMEOUT=2 "$ML" run -- true 2> "$second_err"
status_now=$("$ML" status)
case "$status_now" in
    *"en attente, dans l'ordre :"*"1. hors dépôt"*"sleep 0"*) ok "« status » liste la file d'attente" ;;
    *) ko "« status » sans la file : $status_now" ;;
esac
case "$(cat "$second_err")" in
    *"(position 2 sur 2)"*) ok "une attente dit sa place dans la file" ;;
    *) ko "place absente du message : $(cat "$second_err")" ;;
esac
check "une attente bornée quitte la file en partant" "$(ls "$MACHINE_LOCK_DIR/queue" | grep -c '^[0-9]*$')" 1
wait "$holder" "$first"

# --- A dead waiter does not hold up the queue -------------------------------------------

"$ML" run -- sleep 2 &
holder=$!
wait_held
"$ML" run -- true 2> /dev/null &
dead=$!
sleep 0.5
kill -9 "$dead"
wait "$dead" 2> /dev/null
MACHINE_LOCK_TIMEOUT=6 "$ML" run -- true 2> /dev/null
check "le ticket d'une attente tuée (kill -9) ne bloque pas la suivante" $? 0
wait "$holder"

# --- Ctrl-C while waiting takes the ticket back ------------------------------------------

"$ML" run -- sleep 3 &
holder=$!
wait_held
set -m
"$ML" run -- true 2> /dev/null &
waiter=$!
set +m
sleep 0.5
kill -INT -- "-$waiter"
wait "$waiter"
check "Ctrl-C pendant l'attente rend 130" $? 130
check "et retire le ticket de la file" "$(ls "$MACHINE_LOCK_DIR/queue" | grep -c '^[0-9]*$')" 0
wait "$holder"

echo
if [ "$failures" -gt 0 ]; then
    echo "$failures test(s) en échec."
    exit 1
fi
echo "Tous les tests passent."
