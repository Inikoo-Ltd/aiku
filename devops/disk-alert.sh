#!/bin/bash
#
# Disk usage alert. Posts to Slack when a local filesystem crosses the threshold,
# and once more when it recovers.
#
# Exists because on 27 Aug 2026 helio's root disk reached 99% with nobody paged:
# Redis could not write its snapshot, refused every write, and the whole site
# returned 500 until a snapshot squeaked through. The disk had been filling for
# weeks. Nothing watched it.
#
# Deliberately standalone — no PHP, no Redis, no Horizon, no Laravel scheduler.
# This has to work in exactly the situation where those are broken. The only thing
# it borrows from the app is the Slack webhook, read straight out of the .env file
# so there is no second secret to rotate.
#
# Install: see devops/cron/disk-alert.
#
# Usage: disk-alert.sh [--dry-run] [--self-test]

set -uo pipefail

THRESHOLD=${DISK_ALERT_THRESHOLD:-85}
REALERT_HOURS=${DISK_ALERT_REALERT_HOURS:-6}
REALERT_POINTS=${DISK_ALERT_REALERT_POINTS:-3}
STATE_DIR=${DISK_ALERT_STATE_DIR:-/var/tmp/aiku-disk-alert}
ENV_FILE=${DISK_ALERT_ENV_FILE:-/home/aiku/aiku/current/.env}

DRY_RUN=0
[ "${1:-}" = '--dry-run' ] && DRY_RUN=1

raw_df() {
    if [ -n "${DISK_ALERT_DF_OVERRIDE:-}" ]; then
        printf '%s\n' "$DISK_ALERT_DF_OVERRIDE"
        return
    fi
    # Local filesystems only: a full NFS share or backup mount is somebody else's
    # problem and would drown the real signal.
    df -P -x tmpfs -x devtmpfs -x squashfs -x efivarfs -x nfs -x nfs4 -x cifs -x overlay -x fuse.sshfs 2>/dev/null
}

usage_report() {
    raw_df | awk 'NR > 1 && $5 ~ /%$/ { gsub(/%/, "", $5); print $6, $5, $4 }'
}

# Reads a variable out of the app's .env without sourcing it (that file holds plenty
# this script has no business evaluating).
env_value() {
    [ -r "$ENV_FILE" ] || return
    sed -n "s/^$1=//p" "$ENV_FILE" | head -1 | tr -d '\042\047\r'
}

json_string() {
    printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g; s/^/"/; s/$/"/'
}

# Last resort so an alert is never lost: syslog, plus stderr so cron mails root.
fallback() {
    logger -t aiku-disk-alert "$1 ($2)"
    printf 'aiku-disk-alert: %s (%s)\n' "$1" "$2" >&2
}

notify() {
    local text=$1 token channel response

    if [ "$DRY_RUN" = 1 ]; then
        printf 'WOULD SEND: %s\n' "$text"
        return 0
    fi

    # Reuses the bot token the application already authenticates with, rather than
    # introducing a second Slack credential with its own rotation to forget about.
    token=${DISK_ALERT_SLACK_TOKEN:-$(env_value SLACK_BOT_USER_OAUTH_TOKEN)}
    channel=${DISK_ALERT_SLACK_CHANNEL:-$(env_value SLACK_CHANNEL)}
    channel=${channel:-#general}

    if [ -z "$token" ]; then
        fallback "$text" 'no SLACK_BOT_USER_OAUTH_TOKEN available'
        return 1
    fi

    response=$(curl -sS -m 15 -X POST https://slack.com/api/chat.postMessage \
        -H "Authorization: Bearer $token" \
        -H 'Content-type: application/json; charset=utf-8' \
        --data "$(printf '{"channel": %s, "text": %s}' "$(json_string "$channel")" "$(json_string "$text")")" 2>&1)

    # chat.postMessage answers 200 with {"ok":false,...} for channel_not_found, an
    # expired token and the rest. Treating that as success is how alerting rots
    # silently, which is the exact failure this script exists to prevent.
    case $response in
        *'"ok":true'*) return 0 ;;
        *) fallback "$text" "Slack rejected it: ${response:0:200}"; return 1 ;;
    esac
}

human_kb() {
    awk -v kb="$1" 'BEGIN {
        split("K M G T", unit, " ")
        i = 1
        while (kb >= 1024 && i < 4) { kb /= 1024; i++ }
        printf "%.0f%s", kb, unit[i]
    }'
}

# Decides what to do about one filesystem, given its previous state.
# Echoes: alert | realert | recovered | quiet
decide() {
    local pct=$1 prev_pct=$2 prev_ts=$3 now=$4

    if [ "$pct" -lt "$THRESHOLD" ]; then
        [ -n "$prev_pct" ] && { echo recovered; return; }
        echo quiet
        return
    fi

    [ -z "$prev_pct" ] && { echo alert; return; }
    [ "$pct" -ge $((prev_pct + REALERT_POINTS)) ] && { echo realert; return; }
    [ "$((now - prev_ts))" -ge $((REALERT_HOURS * 3600)) ] && { echo realert; return; }

    echo quiet
}

run_checks() {
    local host now mount pct avail state_file prev_pct prev_ts verdict
    host=$(hostname -s 2>/dev/null || hostname)
    now=$(date +%s)
    mkdir -p "$STATE_DIR" 2>/dev/null

    while read -r mount pct avail; do
        [ -z "${mount:-}" ] && continue
        state_file="$STATE_DIR/$(printf '%s' "$mount" | tr '/' '_')"

        prev_pct=''
        prev_ts=0
        if [ -r "$state_file" ]; then
            read -r prev_pct prev_ts < "$state_file"
        fi

        verdict=$(decide "$pct" "$prev_pct" "$prev_ts" "$now")

        case $verdict in
            alert|realert)
                notify "🔴 ${host}: ${mount} is ${pct}% full, $(human_kb "$avail") free. Free space before it reaches 100% — a full disk stops Redis writes and takes the site down."
                printf '%s %s\n' "$pct" "$now" > "$state_file"
                ;;
            recovered)
                notify "🟢 ${host}: ${mount} is back to ${pct}%, $(human_kb "$avail") free."
                rm -f "$state_file"
                ;;
        esac
    done < <(usage_report)
}

self_test() {
    local failures=0
    check() {
        local got=$1 want=$2 what=$3
        if [ "$got" = "$want" ]; then
            printf '  ok    %s\n' "$what"
        else
            printf '  FAIL  %s (got %s, want %s)\n' "$what" "$got" "$want"
            failures=$((failures + 1))
        fi
    }

    THRESHOLD=85 REALERT_HOURS=6 REALERT_POINTS=3

    check "$(decide 42 '' 0 1000000)"           quiet     'quiet below threshold'
    check "$(decide 85 '' 0 1000000)"           alert     'alerts exactly at threshold'
    check "$(decide 91 '' 0 1000000)"           alert     'alerts above threshold'
    check "$(decide 91 91 1000000 1000060)"     quiet     'no repeat a minute later'
    check "$(decide 94 91 1000000 1000060)"     realert   're-alerts when 3 points worse'
    check "$(decide 93 91 1000000 1000060)"     quiet     'does not re-alert on 2 points'
    check "$(decide 91 91 1000000 1021700)"     realert   're-alerts after 6 hours'
    check "$(decide 70 91 1000000 1000060)"     recovered 'recovery when back under'
    check "$(decide 70 '' 0 1000000)"           quiet     'no recovery without a prior alert'

    # A mangled payload means Slack silently rejects the alert, so escaping is checked
    check "$(json_string 'plain')"              '"plain"'          'json quotes a plain string'
    check "$(json_string 'a "quoted" word')"    '"a \"quoted\" word"' 'json escapes double quotes'
    check "$(json_string 'back\slash')"         '"back\\slash"'    'json escapes backslashes'

    local envfile
    envfile=$(mktemp)
    printf 'OTHER=x\nSLACK_CHANNEL="#devops"\nSLACK_BOT_USER_OAUTH_TOKEN=xoxb-secret\n' > "$envfile"
    check "$(ENV_FILE=$envfile env_value SLACK_CHANNEL)"             '#devops'     'reads a quoted env value'
    check "$(ENV_FILE=$envfile env_value SLACK_BOT_USER_OAUTH_TOKEN)" 'xoxb-secret' 'reads an unquoted env value'
    check "$(ENV_FILE=$envfile env_value NOT_THERE)"                 ''            'missing env var is empty'
    check "$(ENV_FILE=/nonexistent env_value SLACK_CHANNEL)"         ''            'unreadable env file is empty'
    rm -f "$envfile"

    # end to end through the df parser, with no webhook and no state
    local out
    out=$(DISK_ALERT_STATE_DIR=$(mktemp -d) \
          DISK_ALERT_DF_OVERRIDE=$'Filesystem 1024-blocks Used Available Capacity Mounted\n/dev/md2 950000000 940000000 9231000 99% /\n/dev/md1 1000000 200000 800000 22% /boot' \
          DRY_RUN=1 bash "$0" --dry-run)
    case $out in
        *'/ is 99% full'*) printf '  ok    end to end alerts on the full filesystem\n' ;;
        *) printf '  FAIL  end to end (got: %s)\n' "$out"; failures=$((failures + 1)) ;;
    esac
    case $out in
        *'/boot'*) printf '  FAIL  end to end alerted on a healthy filesystem\n'; failures=$((failures + 1)) ;;
        *) printf '  ok    end to end stays quiet on the healthy filesystem\n' ;;
    esac

    [ "$failures" = 0 ] && { printf 'all checks passed\n'; return 0; }
    printf '%s check(s) failed\n' "$failures"
    return 1
}

case ${1:-} in
    --self-test) self_test ;;
    *) run_checks ;;
esac
