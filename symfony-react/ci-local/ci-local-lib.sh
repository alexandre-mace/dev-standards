# The shared core of a Symfony + React project's bin/ci-local (README.md, next to it).
# Sourced, never run: the project's script declares its groups, then calls the functions
# below in order, and keeps only its own steps.
#
#   ci_group php 'PHP' run_php php 10      a group: id, title, function, family, niceness
#   ci_only_alias quality quality          a former --only value, kept with a warning
#   ci_parse_args "$@"                     --fast, --only <family>, --spec <file>, --help
#   ci_phpunit_processes                   PHPUNIT_PROCESSES and PHPUNIT_NOTE
#   ci_start VAR...                        machine lock, var/ci-local/, CI=true, traps
#   ci_context "de travail, …"             the warnings at start
#   step 'Name' function                   one step, in the preparation or in a group
#   ci_run                                 the selected groups in parallel, the summary
#
# Bash 3.2 (the one macOS ships). Comments in English, messages in French, like the other
# shared scripts.

set -o pipefail

# dev-standards is the folder two levels above this file: the project's bin/ci-local found
# it once, next to its main checkout, to source this library, and everything else
# (machine-lock, performance-cores) is read from here.
CI_LIB_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
DEV_STANDARDS_DIR=$(cd "$CI_LIB_DIR/../.." && pwd)

# --- Groups and the command line ------------------------------------------------------
#
# A group belongs to a family: php, front or e2e. --only takes a family, so the same three
# names select the same kind of work in every project, whatever its groups are called.
# --fast drops the e2e family, --spec keeps it alone.

CI_GROUPS=''
CI_FAMILIES=''
CI_ALIASES=''
CI_DEPRECATED=''
E2E_SPECS=()

ci_upper() { printf '%s' "$1" | tr '[:lower:]' '[:upper:]'; }

ci_group() { # id, title, function, family, niceness (0 by default)
    CI_GROUPS="$CI_GROUPS $1"
    printf -v "CI_TITLE_$1" '%s' "$2"
    printf -v "CI_FN_$1" '%s' "$3"
    printf -v "CI_FAMILY_$1" '%s' "$4"
    printf -v "CI_NICE_$1" '%s' "${5:-0}"
    case " $CI_FAMILIES " in *" $4 "*) ;; *) CI_FAMILIES="$CI_FAMILIES $4" ;; esac
}

# A value --only used to take before the families, still accepted: it selects the same
# groups as before, and the start of the run says which family replaces it.
ci_only_alias() { # name, group ids
    local name=$1
    shift
    CI_ALIASES="$CI_ALIASES $name"
    printf -v "CI_ALIAS_$name" '%s' "$*"
}

ci_field() { # field, id
    local var="CI_$1_$2"
    printf '%s' "${!var}"
}

# The header of the project's script, from its second line to its second bare `#`: what it
# does, then how to call it.
ci_usage() {
    awk 'NR > 1 { if ($0 == "#" && ++bare == 2) exit; sub(/^# ?/, ""); print }' "$0"
    exit "${1:-0}"
}

ci_usage_error() {
    echo "$1" >&2
    ci_usage 2 >&2
}

# "php, front ou e2e"
ci_families_list() {
    local list='' f n=0 i=0
    for f in $CI_FAMILIES; do n=$((n + 1)); done
    for f in $CI_FAMILIES; do
        i=$((i + 1))
        if [ "$i" -eq 1 ]; then
            list=$f
        elif [ "$i" -eq "$n" ]; then
            list="$list ou $f"
        else
            list="$list, $f"
        fi
    done
    printf '%s' "$list"
}

ci_runs() { # id: 0 when the group runs in this pass
    local var
    var="RUN_$(ci_upper "$1")"
    [ "${!var:-0}" -eq 1 ]
}

ci_parse_args() {
    CI_ARGS=("$@")
    local fast=0 only='' id selected=''
    # Options used to reset one another, the last one winning: `--spec x --fast` ran no
    # group and concluded « OK », and a second --only replaced the first without a word.
    # Those mixes are refused.
    while [ $# -gt 0 ]; do
        case "$1" in
            --fast) fast=1 ;;
            --spec)
                shift
                [ -f "${1:-}" ] || ci_usage_error "--spec attend un fichier de spec existant."
                # Absolute: the script moves to the root of the repository afterwards.
                E2E_SPECS+=("$(cd "$(dirname "$1")" && pwd)/$(basename "$1")")
                ;;
            --only)
                shift
                [ -z "$only" ] || ci_usage_error "--only ne se répète pas : un seul groupe par passage."
                only=${1:-?}
                ;;
            -h | --help) ci_usage 0 ;;
            *) ci_usage_error "Option inconnue : $1" ;;
        esac
        shift
    done
    if [ "${#E2E_SPECS[@]}" -gt 0 ] && { [ "$fast" -eq 1 ] || [ -n "$only" ]; }; then
        ci_usage_error "--spec ne se combine ni avec --fast ni avec --only : il lance l'E2E seul, sur ces specs."
    fi

    if [ "${#E2E_SPECS[@]}" -gt 0 ]; then
        selected=$(ci_family_groups e2e)
        [ -n "$selected" ] || ci_usage_error "--spec attend un groupe de la famille e2e, ce dépôt n'en a pas."
    elif [ -n "$only" ]; then
        case " $CI_FAMILIES " in
            *" $only "*) selected=$(ci_family_groups "$only") ;;
            *)
                case " $CI_ALIASES " in
                    *" $only "*)
                        selected=$(ci_field ALIAS "$only")
                        CI_DEPRECATED=$(ci_alias_warning "$only" "$selected")
                        ;;
                    *) ci_usage_error "--only attend $(ci_families_list)." ;;
                esac
                ;;
        esac
    else
        selected=$CI_GROUPS
    fi
    if [ "$fast" -eq 1 ]; then
        local kept=''
        for id in $selected; do
            [ "$(ci_field FAMILY "$id")" = e2e ] || kept="$kept $id"
        done
        [ -n "$kept" ] || ci_usage_error "--fast écarte l'E2E que --only $only demande : aucun groupe ne tournerait."
        selected=$kept
    fi

    for id in $CI_GROUPS; do
        case " $selected " in
            *" $id "*) printf -v "RUN_$(ci_upper "$id")" '%s' 1 ;;
            *) printf -v "RUN_$(ci_upper "$id")" '%s' 0 ;;
        esac
    done
}

ci_family_groups() { # family
    local id out=''
    for id in $CI_GROUPS; do
        [ "$(ci_field FAMILY "$id")" = "$1" ] && out="$out $id"
    done
    printf '%s' "$out"
}

ci_alias_warning() { # alias, its groups
    local family id others=''
    family=$(ci_field FAMILY "${2%% *}")
    for id in $(ci_family_groups "$family"); do
        case " $2 " in *" $id "*) ;; *) others="$others${others:+, }$(ci_field TITLE "$id")" ;; esac
    done
    printf "« --only %s » est un ancien nom, gardé pour l'habitude : --only %s, commun aux projets, le remplace%s." \
        "$1" "$family" "${others:+ (et lance aussi : $others)}"
}

# --- ParaTest's process count ---------------------------------------------------------
#
# The machine's performance cores (performance-cores, next door), CI_LOCAL_PHPUNIT_PROCESSES
# overriding them; one process when the script is missing, and PHPUNIT_NOTE says why, for
# the PHPUnit line: a project once fell to one process without a word. Read before the
# lock, so that a wrong value does not wait for the machine.

ci_phpunit_processes() { # "auto" when the project also accepts ParaTest's own `auto`
    local cores=$DEV_STANDARDS_DIR/symfony-react/performance-cores/performance-cores
    PHPUNIT_NOTE=''
    if [ -n "${CI_LOCAL_PHPUNIT_PROCESSES:-}" ]; then
        PHPUNIT_PROCESSES=$CI_LOCAL_PHPUNIT_PROCESSES
    elif [ -x "$cores" ]; then
        PHPUNIT_PROCESSES=$("$cores")
    else
        PHPUNIT_PROCESSES=1
        PHPUNIT_NOTE="performance-cores introuvable : $cores"
    fi
    case "$PHPUNIT_PROCESSES" in
        auto) [ "${1:-}" = auto ] && return 0 ;;
        '' | 0* | *[!0-9]*) ;;
        *) return 0 ;;
    esac
    echo "CI_LOCAL_PHPUNIT_PROCESSES attend un nombre de processus, 1 ou plus${1:+, ou auto}." >&2
    exit 2
}

# --- Start ----------------------------------------------------------------------------

# A full suite takes the whole machine: two at once, from two sessions or two projects,
# make unrelated tests miss their timeouts. The script reruns itself under machine-lock,
# which makes the second run wait and says who holds the machine, and keeps a Mac awake
# while the run lasts. Nothing of the sort on a GitHub runner.
ci_machine_lock() {
    [ "${GITHUB_ACTIONS:-}" != true ] || return 0
    local lock=$DEV_STANDARDS_DIR/symfony-react/machine-lock/machine-lock
    if [ ! -x "$lock" ]; then
        echo "machine-lock introuvable : $lock" >&2
        exit 2
    fi
    "$lock" held || exec "$lock" run -- "$0" ${CI_ARGS[@]+"${CI_ARGS[@]}"}
}

ci_start() { # the project's own variables to unset, on top of the common ones
    ci_machine_lock
    ROOT=$(git rev-parse --show-toplevel 2> /dev/null) || {
        echo "Pas dans un dépôt git." >&2
        exit 2
    }
    cd "$ROOT" || exit 2
    LOG_DIR="$ROOT/var/ci-local"
    rm -rf "$LOG_DIR"
    mkdir -p "$LOG_DIR"
    # What GitHub sets on every step: Vitest refuses a `.only` there, Playwright retries a
    # red test twice and refuses a `.only` too.
    export CI=true
    # Nothing from the caller's session may change how a step behaves.
    unset APP_DEBUG PLAYWRIGHT_BASE_URL TEST_TOKEN SYMFONY_DOTENV_VARS APP_RUNTIME "$@"
    TOTAL_START=$(date +%s)
    DBS_CREATED=''
    GROUPS_STARTED=''
    PG_VERSION=''
    BRANCH=$(git rev-parse --abbrev-ref HEAD)
    [ "$BRANCH" = HEAD ] && BRANCH="detache-$(git rev-parse --short HEAD)"
    trap ci_cleanup EXIT
    trap 'echo; echo "Interrompu."; exit 130' INT TERM
}

# The warnings at start. Argument: the second line of the uncommitted-changes warning,
# which says what the project's checks compare to.
ci_context() {
    echo "CI locale sur $BRANCH ($(git rev-parse --short HEAD)), $ROOT"
    [ -z "$CI_DEPRECATED" ] || echo "  Attention : $CI_DEPRECATED"
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "  Attention : des modifications ne sont pas commitées. Les étapes lisent l'arbre"
        echo "  ${1:-de travail.}"
    fi
    if [ "$(git config core.hooksPath)" = .husky/_ ] && [ ! -d .husky/_ ]; then
        echo "  Attention : .husky/_ manque, git ne lance aucun hook dans ce worktree : bin/worktree-setup, ou pnpm exec husky."
    fi
    # Fetched first: in a worktree whose origin/main dates from several days ago, a branch
    # that is behind passed for up to date.
    # shellcheck disable=SC2086
    if ! git fetch -q origin ${CI_FETCH_REFS:-main} 2> /dev/null; then
        echo "  Attention : git fetch origin ${CI_FETCH_REFS:-main} a échoué (réseau ?), les comparaisons portent sur la dernière copie."
    fi
    if git rev-parse --verify -q origin/main > /dev/null && ! git merge-base --is-ancestor origin/main HEAD; then
        echo "  Attention : la branche ne contient pas origin/main. Fusionne-le avant de conclure."
    fi
    echo
}

# --- Output ---------------------------------------------------------------------------

duration() {
    local s=$1
    if [ "$s" -ge 60 ]; then
        printf '%d min %02d s' $((s / 60)) $((s % 60))
    else
        printf '%d s' "$s"
    fi
}

# Notes come from the groups too, which are subshells: they go through a file.
note() {
    printf '  - %s\n' "$1" >> "$LOG_DIR/notes"
}

line() { # status, duration, name, detail
    printf '  %s  %-12s %s%s\n' "$1" "$2" "$3" "${4:+  ($4)}"
}

# The one-minute load average, as the machine gives it.
load1() {
    if [ -r /proc/loadavg ]; then
        cut -d' ' -f1 /proc/loadavg
    else
        sysctl -n vm.loadavg 2> /dev/null | awk '{print $2}'
    fi
}

summary() {
    local status=$1
    echo
    # Steps write to it as they end: sorted, the timeline reads by start.
    [ -f "$LOG_DIR/timeline" ] && sort -o "$LOG_DIR/timeline" "$LOG_DIR/timeline"
    echo "Durée totale : $(duration $(($(date +%s) - TOTAL_START)))  (chronologie : var/ci-local/timeline)"
    if [ -s "$LOG_DIR/notes" ]; then
        echo "${CI_NOTES_TITLE:-Non couvert en local :}"
        cat "$LOG_DIR/notes"
    fi
    if [ "$status" -eq 0 ]; then
        echo "CI locale : OK"
    else
        echo "CI locale : ÉCHEC"
    fi
}

# --- Steps and groups -----------------------------------------------------------------
#
# step "Name" function [arguments]: runs the function in a subshell under `set -e` (a step
# of several commands fails at the first one, not only at the last: a cache:warmup starved
# of memory passed for green otherwise), output in var/ci-local/<group>-<number>-<function>.log.
# The step's line goes to the group's output (the screen for the preparation, a file for
# the others). At the first failure the end of the log follows, and the group stops.
#
# Each step writes to var/ci-local/timeline its start, its end (to the second), its group,
# its name and the machine's load at both instants: a log's date is only its last write,
# and `ls -l` rounds it to the minute, so a step ended just before Vitest looked as if it
# had run during it.

GROUP_ID=prep
GROUP_OUT=''
STEP_NO=0
STEP_EXTRA=''

emit() {
    if [ -n "$GROUP_OUT" ]; then
        cat >> "$GROUP_OUT"
    else
        cat
    fi
}

step() {
    local name=$1
    shift
    STEP_NO=$((STEP_NO + 1))
    local log
    log="$LOG_DIR/$GROUP_ID-$(printf '%02d' "$STEP_NO")-$1.log"
    printf '%s' "$name" > "$LOG_DIR/$GROUP_ID.current"
    [ "$GROUP_ID" = prep ] && [ -t 1 ] && printf '  ..     %-12s %s' '' "$name"
    local start began load_start
    start=$(date +%s)
    began=$(date +%H:%M:%S)
    load_start=$(load1)
    (set -e; "$@") > "$log" 2>&1
    local code=$?
    printf '%s\t%s\t%s\t%s\tcharge %s -> %s\n' "$began" "$(date +%H:%M:%S)" "$GROUP_ID" "$name" \
        "$load_start" "$(load1)" >> "$LOG_DIR/timeline"
    local took
    took=$(duration $(($(date +%s) - start)))
    [ "$GROUP_ID" = prep ] && [ -t 1 ] && printf '\r\033[K'
    if [ "$code" -eq 0 ]; then
        line 'OK   ' "$took" "$name" "$STEP_EXTRA" | emit
        STEP_EXTRA=''
        return 0
    fi
    {
        line 'ÉCHEC' "$took" "$name"
        echo
        echo "--- fin de ${log#"$ROOT"/} ---"
        tail -n 60 "$log"
        echo "---"
    } | emit
    fail_group
}

# A step that cannot run here: one line, and a note in the summary.
skip() { # name, reason
    STEP_NO=$((STEP_NO + 1))
    line 'SAUTÉ' '' "$1" "$2" | emit
    note "$1 : $2"
}

# A failure outside a step (database unreachable, port taken): the same output as a step.
fail() { # name, reason
    STEP_NO=$((STEP_NO + 1))
    line 'ÉCHEC' '' "$1" "$2" | emit
    fail_group
}

fail_group() {
    # What a group still has to say before it stops (Vitest: what ran alongside).
    [ -z "${ON_GROUP_FAIL:-}" ] || "$ON_GROUP_FAIL"
    if [ "$GROUP_ID" = prep ]; then
        summary 1
        exit 1
    fi
    exit 1 # the group's subshell
}

# The group runs in the background; its output is shown in one block when it ends
# (wait_groups). `id.done` says it ended, whatever the outcome: a meeting point never waits
# forever for a group that stopped before. A group with no timeout to keep (the analyses,
# PHPUnit) yields the processor to the others through its niceness: Playwright waits a few
# seconds for each answer.
start_group() { # id, title, function, niceness
    local id=$1 title=$2 fn=$3 niceness=${4:-0}
    printf '%s' "$title" > "$LOG_DIR/$id.title"
    date +%s > "$LOG_DIR/$id.start"
    (
        # $$ stays the script's pid in a subshell: the group's is the parent of an sh
        # started here without substitution (which would add its own subshell). Its steps
        # inherit the niceness, and the processor sampling during Vitest ranks by this pid.
        sh -c 'echo $PPID' > "$LOG_DIR/$id.pid"
        if [ "$niceness" -ne 0 ]; then
            renice -n "$niceness" -p "$(cat "$LOG_DIR/$id.pid")" > /dev/null
        fi
        trap 'touch "$LOG_DIR/$id.done"' EXIT
        GROUP_ID=$id
        GROUP_OUT="$LOG_DIR/$id.out"
        STEP_NO=0
        : > "$GROUP_OUT"
        "$fn"
    ) &
    printf -v "GROUP_PID_$id" '%s' "$!"
    GROUPS_STARTED="$GROUPS_STARTED $id"
}

# Meeting point: waits for the marker file `marker.done`, or for the end of the group that
# was to set it. When that group is not in the pass (--only, --fast), nothing to wait for.
await() { # marker, group
    ci_runs "$2" || return 0
    printf '%s (attend %s)' "$(cat "$LOG_DIR/$GROUP_ID.current" 2> /dev/null)" "$1" > "$LOG_DIR/$GROUP_ID.current"
    until [ -f "$LOG_DIR/$1.done" ] || [ -f "$LOG_DIR/$2.done" ]; do
        sleep 0.5
    done
}

signal() { touch "$LOG_DIR/$1.done"; }

# The processes of a group and of its steps: a background job of a non-interactive shell
# ignores SIGINT, so a Ctrl-C would not stop them.
kill_tree() {
    local child
    for child in $(pgrep -P "$1" 2> /dev/null); do
        kill_tree "$child"
    done
    kill "$1" 2> /dev/null
}

wait_groups() {
    local status=0 remaining=$GROUPS_STARTED id pidvar pid code next took current
    while [ -n "$remaining" ]; do
        next=''
        for id in $remaining; do
            pidvar="GROUP_PID_$id"
            pid=${!pidvar}
            if kill -0 "$pid" 2> /dev/null; then
                next="$next $id"
                continue
            fi
            wait "$pid"
            code=$?
            [ "$code" -eq 0 ] || status=1
            took=$(duration $(($(date +%s) - $(cat "$LOG_DIR/$id.start"))))
            [ -t 1 ] && printf '\r\033[K'
            echo
            echo "$(cat "$LOG_DIR/$id.title") ($took)"
            cat "$LOG_DIR/$id.out"
        done
        remaining=$next
        if [ -n "$remaining" ] && [ -t 1 ]; then
            current=''
            for id in $remaining; do
                current="$current${current:+, }$(cat "$LOG_DIR/$id.title") : $(cat "$LOG_DIR/$id.current" 2> /dev/null)"
            done
            current="  ..  $current"
            printf '\r\033[K%s' "${current:0:$(($(tput cols 2> /dev/null || echo 120) - 1))}"
        fi
        [ -n "$remaining" ] && sleep 1
    done
    return "$status"
}

# The selected groups, in their order of declaration, then the summary and the exit.
ci_run() {
    local id status
    for id in $CI_GROUPS; do
        ci_runs "$id" || continue
        start_group "$id" "$(ci_field TITLE "$id")" "$(ci_field FN "$id")" "$(ci_field NICE "$id")"
    done
    wait_groups
    status=$?
    summary "$status"
    exit "$status"
}

# --- Vitest, alone, and what ran alongside it ------------------------------------------
#
# Under the latest Node of the CI's major (setup-node), whatever the machine's Node: a test
# once broke under 22.23 only. One pass: Vitest is on the critical path. `npx -y` resolves
# the version on the registry at each run. The major is CI_NODE_MAJOR, else the project's
# `.nvmrc`, which every workflow reads too (`node-version-file`), else 22.

ci_node_major() {
    local major=${CI_NODE_MAJOR:-$(tr -d '[:space:]v' < "${ROOT:-.}/.nvmrc" 2> /dev/null)}
    echo "${major:-22}"
}

vitest() { npx -y "node@$(ci_node_major)" node_modules/vitest/vitest.mjs run; }

# What runs next to Vitest: every two seconds, the %CPU of ps (a moving average) of each
# process, ranked in three: Vitest (its group), the rest of the run (the script's
# descendants), the rest of the machine. One line per active process: time, rank, name,
# %CPU. It is the « what ran alongside » that the report of a flaky test owes
# (agent/long-running-checks.md).
cpu_watch() { # pid of the script, pid of the Vitest group
    while :; do
        ps -Ao pid=,ppid=,pcpu=,comm= | awk -v root="$1" -v grp="$2" -v now="$(date +%H:%M:%S)" '
            {
                p = $1; ppid[p] = $2; cpu[p] = $3
                $1 = $2 = $3 = ""; sub(/^ +/, "")
                n = split($0, parts, "/"); name[p] = parts[n]
            }
            END {
                for (p in cpu) {
                    if (cpu[p] + 0 == 0) continue
                    rank = "machine"
                    for (q = p; q > 1; q = ppid[q]) {
                        if (q == grp) { rank = "vitest"; break }
                        if (q == root) { rank = "ci"; break }
                    }
                    total[rank "\t" name[p]] += cpu[p]
                }
                for (k in total) printf "%s\t%s\t%.0f\n", now, k, total[k]
            }'
        sleep 2
    done
}

# One line under the group: the biggest consumers outside Vitest during its run, averaged
# over the samples; « (passage) » marks another step of this local CI.
cpu_report() {
    if [ -n "${CPU_WATCH_PID:-}" ]; then
        kill "$CPU_WATCH_PID" 2> /dev/null
        wait "$CPU_WATCH_PID" 2> /dev/null
    fi
    CPU_WATCH_PID=''
    ON_GROUP_FAIL=''
    local file="$LOG_DIR/vitest-cpu.tsv" samples
    [ -s "$file" ] || return 0
    samples=$(cut -f1 "$file" | sort -u | wc -l | tr -d ' ')
    awk -F'\t' -v n="$samples" '
        $2 != "vitest" { total[$2 "\t" $3] += $4 }
        END {
            for (k in total) {
                split(k, part, "\t")
                avg = total[k] / n
                if (avg >= 10) printf "%d\t%s %d %%%s\n", avg, part[2], avg, (part[1] == "ci" ? " (passage)" : "")
            }
        }' "$file" | sort -rn | head -n 6 | cut -f2 | paste -sd ',' - | sed 's/,/, /g' > "$LOG_DIR/vitest-cpu.summary"
    printf '  ..   À côté de Vitest (%%CPU moyen sur %s relevés, var/ci-local/vitest-cpu.tsv) : %s\n' \
        "$samples" "$(grep . "$LOG_DIR/vitest-cpu.summary" || echo 'rien au-dessus de 10 %')" | emit
}

# The Vitest step, sampled. Called from the group that runs Vitest, once the groups that
# use several cores have reached their meeting point.
vitest_step() { # step name
    cpu_watch "$$" "$(cat "$LOG_DIR/$GROUP_ID.pid")" > "$LOG_DIR/vitest-cpu.tsv" 2> /dev/null &
    CPU_WATCH_PID=$!
    ON_GROUP_FAIL=cpu_report
    STEP_EXTRA="Node $(npx -y "node@$(ci_node_major)" -v 2> /dev/null)"
    step "$1" vitest
    cpu_report
}

# --- Environment: the variables the CI sees, with no local file ------------------------
#
# The CI has neither .env.local nor .env.*.local. No file is touched here: Dotenv composes
# the same files in a temporary folder, and the result is exported. A variable really
# present in the environment wins over any .env file, so the machine's own (real keys,
# development database) no longer apply. The project says which files a mode reads in a
# function `dotenv_files <mode> <folder>`; .env is always copied.

compute_env() { # mode, then the job's variables
    local mode=$1
    shift
    local dir="$LOG_DIR/dotenv-$mode"
    mkdir -p "$dir"
    cp .env "$dir/.env"
    dotenv_files "$mode" "$dir"
    env -i PATH="$PATH" HOME="$HOME" "$@" php -r '
        require "vendor/autoload.php";
        (new Symfony\Component\Dotenv\Dotenv())->loadEnv($argv[1]."/.env");
        foreach (explode(",", $_SERVER["SYMFONY_DOTENV_VARS"] ?? "") as $name) {
            if ("" !== $name) {
                echo $name, "=", $_SERVER[$name], "\0";
            }
        }
    ' "$dir" > "$LOG_DIR/env-$mode" || {
        echo "Composition de l'environnement $mode impossible (Dotenv)." >&2
        return 1
    }
    rm -rf "$dir"
}

load_env() { # mode
    local kv
    while IFS= read -r -d '' kv; do
        export "$kv"
    done < "$LOG_DIR/env-$1"
}

# --- Local PostgreSQL ------------------------------------------------------------------
#
# Throwaway databases, recorded in DBS_CREATED by the main shell before they exist, so the
# cleanup drops them even when their creation fails halfway. A group is a subshell: what it
# records never reaches the cleanup, so a database a group creates is reserved beforehand
# (pg_reserve).

PG_HOST=${PGHOST:-127.0.0.1}
case "$PG_HOST" in /*) PG_HOST=127.0.0.1 ;; esac
PG_PORT=${PGPORT:-5432}
PG_USER=${PGUSER:-$(whoami)}

# <prefix>_ci_<branch>_<hash>: two worktrees are always on two branches, hence on two
# databases, the prefix keeps them apart from other repositories', and the hash keeps a
# truncation from making two names meet.
ci_db_base() { # prefix, length kept of the branch slug
    local slug hash
    slug=$(printf '%s' "$BRANCH" | LC_ALL=C tr -cs 'A-Za-z0-9' '_' | tr 'A-Z' 'a-z' | cut -c1-"$2" | sed 's/_$//')
    hash=$(printf '%s' "$BRANCH" | shasum | cut -c1-6)
    printf '%s_ci_%s_%s' "$1" "$slug" "$hash"
}

# CI_PG_URL_EXTRA, appended to the query, is `&charset=utf8` unless the project sets it.
pg_url() { # database, server version
    local auth=$PG_USER
    [ -n "${PGPASSWORD:-}" ] && auth="$PG_USER:$PGPASSWORD"
    printf 'postgresql://%s@%s:%s/%s?serverVersion=%s%s' "$auth" "$PG_HOST" "$PG_PORT" "$1" "$2" "${CI_PG_URL_EXTRA-&charset=utf8}"
}

pg() { psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d postgres -XAtq "$@"; }

pg_ready() { command -v psql > /dev/null && pg -c 'select 1' > /dev/null 2>&1; }

pg_create() {
    DBS_CREATED="$DBS_CREATED $1"
    dropdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" --if-exists --force "$1" && \
        createdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" "$1"
}

# A database a group will create: dropped if a previous run left it, and recorded here.
pg_reserve() {
    dropdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" --if-exists --force "$1" > /dev/null 2>&1
    DBS_CREATED="$DBS_CREATED $1"
}

# The E2E workers' copies: <template>_w1 to _w<count - 1>, worker 0 keeping the template.
pg_reserve_copies() { # template, workers
    local worker
    for worker in $(seq 1 $(($2 - 1))); do
        pg_reserve "${1}_w$worker"
    done
}

# Made once the template's schema is in place, a few tenths of a second each.
pg_copy() { # template, workers
    local worker
    for worker in $(seq 1 $(($2 - 1))); do
        dropdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" --if-exists --force "${1}_w$worker" || return 1
        createdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -T "$1" "${1}_w$worker" || return 1
    done
}

pg_drop_all() {
    local db
    for db in $DBS_CREATED; do
        dropdb -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" --if-exists --force "$db" > /dev/null 2>&1
    done
    DBS_CREATED=''
}

# The local server's major version, for serverVersion, read once; a note when the CI runs
# another one.
pg_version() { # the CI's major version
    [ -n "$PG_VERSION" ] && return 0
    PG_VERSION=$(($(pg -c 'show server_version_num') / 10000))
    [ "$PG_VERSION" = "$1" ] || note "PostgreSQL $PG_VERSION en local, $1 en CI"
}

# --- The E2E server ---------------------------------------------------------------------

# What must hold before the E2E group starts, checked in the preparation.
ci_e2e_preflight() { # port, or nothing when the project picks a free one
    command -v symfony > /dev/null || fail 'E2E' 'Symfony CLI absente (symfony server:start)'
    # Symfony accepts one server per folder: with the development server running here, the
    # server step would wait for an answer that never comes, and the cleanup would stop
    # that development server. The output is read in one go: `| grep -q` under pipefail
    # fails as soon as grep closes the pipe before the end of the write (141), and the
    # server went unnoticed.
    local status
    status=$(symfony server:status --dir="$ROOT" --no-ansi 2> /dev/null)
    if grep -q 'Listening on' <<< "$status"; then
        fail 'E2E' "un serveur Symfony tourne déjà dans ce dossier, sur $(grep -o 'http[^ ]*' <<< "$status" | head -n 1), sans doute le serveur de dev : arrête-le (symfony server:stop), ou lance la CI locale depuis une worktree"
    fi
    if [ -n "${1:-}" ] && nc -z 127.0.0.1 "$1" > /dev/null 2>&1; then
        fail 'E2E' "le port $1 est occupé (lsof -nP -iTCP:$1 -sTCP:LISTEN)"
    fi
}

# OPcache of the E2E server, over the machine's php.ini. At the default 8 MB the interned
# strings buffer overflowed, and a PHP-FPM process once died in SIGSEGV while four workers
# connected together.
ci_server_opcache() {
    mkdir -p "$LOG_DIR/php-e2e.d"
    printf '%s\n' 'opcache.interned_strings_buffer=64' 'opcache.memory_consumption=256' \
        'opcache.max_accelerated_files=32531' > "$LOG_DIR/php-e2e.d/opcache.ini"
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$LOG_DIR/php-e2e.d"
}

# The Symfony server in the background, its log in var/ci-local/symfony-server.log, ready
# once the page given answers. The pid file lets the cleanup, in the main shell, stop it.
ci_serve() { # port, path that must answer
    nohup symfony server:start --no-tls --port="$1" --dir=. > "$LOG_DIR/symfony-server.log" 2>&1 &
    echo "$!" > "$LOG_DIR/symfony-server.pid"
    local i
    for i in $(seq 1 30); do
        if curl -fs "http://127.0.0.1:$1$2" > /dev/null; then
            echo "Serveur prêt après ${i} s, port $1"
            return 0
        fi
        sleep 1
    done
    echo "Le serveur n'a pas répondu en 30 s"
    echo "--- var/ci-local/symfony-server.log ---"
    cat "$LOG_DIR/symfony-server.log"
    return 1
}

ci_stop_server() {
    [ -f "$LOG_DIR/symfony-server.pid" ] || return 0
    symfony server:stop --dir="$ROOT" > /dev/null 2>&1
    kill "$(cat "$LOG_DIR/symfony-server.pid")" 2> /dev/null
    rm -f "$LOG_DIR/symfony-server.pid"
}

# After a red Playwright: where the report is, and the server's errors and 5xx answers.
ci_server_errors() {
    echo
    echo "Rapport : pnpm exec playwright show-report"
    echo "--- var/ci-local/symfony-server.log (erreurs et réponses 5xx) ---"
    grep -E 'ERROR|CRITICAL|\((5[0-9][0-9])\)' "$LOG_DIR/symfony-server.log" | tail -n 20
}

# --- Cleanup, whatever happens -----------------------------------------------------------
#
# The groups' process trees, then the project's own `on_cleanup` when it defines one (stop
# its server, remove its caches), then the throwaway databases and the run's transient
# files. The logs and the timeline stay.

ci_cleanup() {
    local id pidvar
    for id in $GROUPS_STARTED; do
        pidvar="GROUP_PID_$id"
        kill -0 "${!pidvar}" 2> /dev/null && kill_tree "${!pidvar}"
    done
    if declare -F on_cleanup > /dev/null; then
        on_cleanup
    fi
    pg_drop_all
    rm -f "$LOG_DIR"/env-* "$LOG_DIR"/*.done "$LOG_DIR"/*.current "$LOG_DIR"/*.start "$LOG_DIR"/*.title "$LOG_DIR"/*.out "$LOG_DIR"/*.pid
    rm -rf "$LOG_DIR"/dotenv-*
}
