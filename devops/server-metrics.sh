#!/bin/bash
#
# Server usage sampler for the DevOps dashboard. Runs as a service on every box
# (boro, litio, helio, neon): every second it posts a live reading (CPU,
# I/O wait, memory, network) that aiku only pushes to open dashboards over
# soketi, and every minute the full reading (plus swap, disks, inodes, disk I/O,
# processes, connections) that aiku stores. Standalone bash, no PHP: runs on
# boxes without the app too.
#
# Install: see devops/systemd/aiku-server-metrics.service.
# Usage: server-metrics.sh [--dry-run]   one full reading, printed, not sent

set -uo pipefail

CONFIG=${SERVER_METRICS_CONFIG:-/etc/aiku-server-metrics.env}
[ -r "$CONFIG" ] && . "$CONFIG"

URL=${SERVER_METRICS_URL:-https://aiku.io/devops}
SLUG=${SERVER_METRICS_SLUG:-$(hostname -s | tr 'A-Z' 'a-z')}
TOKEN=${SERVER_METRICS_TOKEN:-}
INTERVAL=${SERVER_METRICS_INTERVAL:-1}
FULL_EVERY=$((60 / INTERVAL))
LOCAL_FS=(-x tmpfs -x devtmpfs -x squashfs -x efivarfs -x nfs -x nfs4 -x cifs -x overlay -x fuse.sshfs)

counters() {
    awk '/^cpu / { total = 0; for (i = 2; i <= NF; i++) total += $i; printf "%s %s %s ", $5, $6, total }' /proc/stat
    awk -F'[: ]+' 'NR > 2 && $2 != "lo" { rx += $3; tx += $11 } END { printf "%.0f %.0f ", rx, tx }' /proc/net/dev
    awk '$3 ~ /^(sd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme[0-9]+n[0-9]+)$/ { r += $6; w += $10 } END { printf "%.0f %.0f\n", r, w }' /proc/diskstats
}

memory() {
    awk '{ v[$1] = $2 }
        END {
            mt = v["MemTotal:"]; ma = v["MemAvailable:"]; st = v["SwapTotal:"]; sf = v["SwapFree:"]
            printf "%d %.2f %.2f %d\n", mt / 1024, (mt > 0 ? (1 - ma / mt) * 100 : 0), (st > 0 ? (1 - sf / st) * 100 : 0), st / 1024
        }' /proc/meminfo
}

disks() {
    awk 'FNR == 1 { file++; next }
        file == 1 && $5 ~ /%$/ { gsub(/%/, "", $5); inodes[$6] = $5 }
        file == 2 && $5 ~ /%$/ && !seen[$1]++ {
            gsub(/%/, "", $5); gsub(/["\\]/, "", $6)
            printf "%s{\"mount\":\"%s\",\"percent\":%s,\"size_gb\":%.1f,\"inode_percent\":%s}", (n++ ? "," : ""), $6, $5, $2 / 1048576, ($6 in inodes ? inodes[$6] : "null")
        }' <(df -P -i "${LOCAL_FS[@]}" 2>/dev/null) <(df -P -k "${LOCAL_FS[@]}" 2>/dev/null)
}

CLK_TCK=$(getconf CLK_TCK)
CORES=$(nproc)

# One line per process: pid, name (kernel threads like kworker/3:1 grouped as kworker), CPU ticks used since it started
process_ticks() {
    cat /proc/[0-9]*/stat 2>/dev/null | awk '{
        open = index($0, "("); match($0, /\) [A-Za-z] /)
        if (!open || !RSTART) next
        name = substr($0, open + 1, RSTART - open - 1); sub(/\/.*/, "", name); gsub(/[ "\\]/, "_", name)
        split(substr($0, RSTART + 2), f, " ")
        print $1, name, f[12] + f[13]
    }'
}

# $1 = previous process_ticks, $2 = current process_ticks, $3 = seconds between them
top_processes() {
    awk -v s="$3" -v hz="$CLK_TCK" -v cores="$CORES" '
        FNR == 1 { file++ }
        file == 1 { before[$1] = $3; next }
        {
            used = $3 - ($1 in before ? before[$1] : 0)
            if (used <= 0) next
            total[$2] += used; count[$2]++
            if (used > busiest[$2]) busiest[$2] = used
        }
        END {
            for (name in total) printf "%s %s %s %s\n", total[name], name, busiest[name], count[name]
        }' <(printf '%s\n' "$1") <(printf '%s\n' "$2") |
    sort -rn | head -5 |
    awk -v s="$3" -v hz="$CLK_TCK" -v cores="$CORES" '{
        printf "%s{\"name\":\"%s\",\"cpu_percent\":%.2f,\"max_core_percent\":%.2f,\"processes\":%d}", (NR > 1 ? "," : ""), $2, $1 / (s * hz * cores) * 100, $3 / (s * hz) * 100, $4
    }'
}

# $1 = previous counters, $2 = current counters, $3 = seconds between them
rates() {
    awk -v a="$1" -v b="$2" -v s="$3" 'BEGIN {
        split(a, p, " "); split(b, c, " ")
        idle = c[1] - p[1]; wait = c[2] - p[2]; total = c[3] - p[3]
        mb = 1048576
        d4 = c[4] - p[4]; d5 = c[5] - p[5]; d6 = c[6] - p[6]; d7 = c[7] - p[7]
        printf "%.2f %.2f %.2f %.2f %.2f %.2f\n",
            (total > 0 ? (1 - (idle + wait) / total) * 100 : 0), (total > 0 ? wait / total * 100 : 0),
            (d4 > 0 ? d4 : 0) / s / mb, (d5 > 0 ? d5 : 0) / s / mb,
            (d6 > 0 ? d6 : 0) * 512 / s / mb, (d7 > 0 ? d7 : 0) * 512 / s / mb
    }'
}

post() {
    local path=$1 payload=$2 timeout=$3 code
    code=$(curl -sS -m "$timeout" -o /dev/null -w '%{http_code}' -X POST \
        -H 'Content-Type: application/json' -H 'Accept: application/json' -H "X-DEVOPS-TOKEN: $TOKEN" \
        --data "$payload" "$URL/$path" 2>&1)
    [ "$code" = 200 ] || [ "$path" != "metrics/$SLUG" ] || logger -t aiku-server-metrics "post $path failed: $code"
}

# $1 = previous counters, $2 = current counters, $3 = seconds, $4 = full (1) or live (0), $5/$6 = previous/current process_ticks (full only)
reading() {
    local cpu iowait net_rx net_tx disk_read disk_write mem_total mem_pct swap_pct swap_total
    read -r cpu iowait net_rx net_tx disk_read disk_write < <(rates "$1" "$2" "$3")
    read -r mem_total mem_pct swap_pct swap_total < <(memory)

    if [ "$4" = 0 ]; then
        printf '{"cpu_percent":%s,"iowait_percent":%s,"memory_percent":%s,"net_rx_mbps":%s,"net_tx_mbps":%s}' \
            "$cpu" "$iowait" "$mem_pct" "$net_rx" "$net_tx"
        return
    fi

    printf '{"cpu_percent":%s,"iowait_percent":%s,"memory_percent":%s,"swap_percent":%s,"load_1":%s,"cpu_cores":%s,"memory_total_mb":%s,"swap_total_mb":%s,"net_rx_mbps":%s,"net_tx_mbps":%s,"disk_read_mbps":%s,"disk_write_mbps":%s,"processes":%s,"tcp_connections":%s,"disks":[%s],"top_processes":[%s]}' \
        "$cpu" "$iowait" "$mem_pct" "$swap_pct" "$(cut -d' ' -f1 /proc/loadavg)" "$(nproc)" "$mem_total" "$swap_total" \
        "$net_rx" "$net_tx" "$disk_read" "$disk_write" \
        "$(ls -d /proc/[0-9]* 2>/dev/null | wc -l)" "$(ss -Htn state established 2>/dev/null | wc -l)" "$(disks)" \
        "$( [ -n "${5:-}" ] && top_processes "$5" "$6" "$3")"
}

if [ "${1:-}" = "--dry-run" ]; then
    before=$(counters); before_processes=$(process_ticks); sleep 2; after=$(counters); after_processes=$(process_ticks)
    printf 'POST %s/metrics/%s\n%s\n' "$URL" "$SLUG" "$(reading "$before" "$after" 2 1 "$before_processes" "$after_processes")"
    printf 'POST %s/metrics/%s/live (every %ss)\n%s\n' "$URL" "$SLUG" "$INTERVAL" "$(reading "$before" "$after" 2 0)"
    exit 0
fi

[ -n "$TOKEN" ] || { logger -t aiku-server-metrics "no SERVER_METRICS_TOKEN in $CONFIG"; exit 1; }

now_ms() { echo $(($(date +%s%N) / 1000000)); }
seconds_since() { awk -v ms="$(( $(now_ms) - $1 ))" 'BEGIN { printf "%.3f", ms / 1000 }'; }

previous=$(counters)
previous_ms=$(now_ms)
full_previous=$previous
full_previous_ms=$previous_ms
full_previous_processes=$(process_ticks)
tick=0
while true; do
    sleep "$INTERVAL"
    current=$(counters)
    elapsed=$(seconds_since "$previous_ms")
    previous_ms=$(now_ms)
    tick=$((tick + 1))

    post "metrics/$SLUG/live" "$(reading "$previous" "$current" "$elapsed" 0)" 1 &

    if [ "$tick" -ge "$FULL_EVERY" ]; then
        current_processes=$(process_ticks)
        post "metrics/$SLUG" "$(reading "$full_previous" "$current" "$(seconds_since "$full_previous_ms")" 1 "$full_previous_processes" "$current_processes")" 20 &
        full_previous=$current
        full_previous_processes=$current_processes
        full_previous_ms=$previous_ms
        tick=0
    fi

    previous=$current
done
