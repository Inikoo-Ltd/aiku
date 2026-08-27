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

webhook_url() {
    [ -n "${DISK_ALERT_WEBHOOK:-}" ] && { printf '%s' "$DISK_ALERT_WEBHOOK"; return; }
    [ -r "$ENV_FILE" ] || return
    sed -n 's/^LOG_SLACK_WEBHOOK_URL=//p' "$ENV_FILE" | head -1 | tr -d '"'\''' | tr -d '\r'
}

notify() {
    local text=$1 url
    url=$(webhook_url)

    if [ "$DRY_RUN" = 1 ]; then
        printf 'WOULD SEND: %s\n' "$text"
        return 0
    fi

    if [ -z "$url" ]; then
        # No webhook configured: syslog plus stderr, so cron mails root rather than
        # the alert disappearing silently.
        logger -t aiku-disk-alert "$text"
        printf 'aiku-disk-alert (no LOG_SLACK_WEBHOOK_URL configured): %s\n' "$text" >&2
        return 1
    fi

    curl -sS -m 15 -X POST -H 'Content-type: application/json' \
        --data "$(printf '{"text": %s}' "$(printf '%s' "$text" | sed 's/\\/\\\\/g; s/"/\\"/g; s/^/"/; s/$/"/')")" \
        "$url" >/dev/null 2>&1
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
