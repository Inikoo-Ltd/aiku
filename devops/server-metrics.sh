#!/bin/bash
#
# Posts one CPU / iowait / memory / swap / disk / inode / network / disk I/O / process sample of this box to aiku every minute
# (DevOps dashboard > Servers). Standalone bash, no PHP: runs on boxes without
# the app too (helio, neon).
#
# Install: see devops/cron/server-metrics.
# Usage: server-metrics.sh [--dry-run]

set -uo pipefail

CONFIG=${SERVER_METRICS_CONFIG:-/etc/aiku-server-metrics.env}
[ -r "$CONFIG" ] && . "$CONFIG"

URL=${SERVER_METRICS_URL:-https://app.aiku.io/devops}
SLUG=${SERVER_METRICS_SLUG:-$(hostname -s | tr 'A-Z' 'a-z')}
TOKEN=${SERVER_METRICS_TOKEN:-}

cpu_sample() { awk '/^cpu / { total = 0; for (i = 2; i <= NF; i++) total += $i; print $5, $6, total }' /proc/stat; }
net_sample() { awk -F'[: ]+' 'NR > 2 && $2 != "lo" { rx += $3; tx += $11 } END { printf "%.0f %.0f\n", rx, tx }' /proc/net/dev; }
disk_sample() { awk '$3 ~ /^(sd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme[0-9]+n[0-9]+)$/ { r += $6; w += $10 } END { printf "%.0f %.0f\n", r, w }' /proc/diskstats; }

INTERVAL=2
read -r idle1 iowait1 total1 < <(cpu_sample)
read -r rx1 tx1 < <(net_sample)
read -r rd1 wr1 < <(disk_sample)
sleep $INTERVAL
read -r idle2 iowait2 total2 < <(cpu_sample)
read -r rx2 tx2 < <(net_sample)
read -r rd2 wr2 < <(disk_sample)

read -r cpu iowait < <(awk -v i="$((idle2 - idle1))" -v w="$((iowait2 - iowait1))" -v t="$((total2 - total1))" \
    'BEGIN { if (t > 0) printf "%.2f %.2f\n", (1 - (i + w) / t) * 100, w / t * 100; else print "0 0" }')
rate() { awk -v d="$1" -v s="$INTERVAL" -v u="$2" 'BEGIN { printf "%.2f", (d > 0 ? d : 0) * u / s / 1048576 }'; }
net_rx=$(rate "$((rx2 - rx1))" 1)
net_tx=$(rate "$((tx2 - tx1))" 1)
disk_read=$(rate "$((rd2 - rd1))" 512)
disk_write=$(rate "$((wr2 - wr1))" 512)

read -r mem_total mem_pct swap_pct < <(awk '
    { v[$1] = $2 }
    END {
        mt = v["MemTotal:"]; ma = v["MemAvailable:"]; st = v["SwapTotal:"]; sf = v["SwapFree:"]
        printf "%d %.2f %.2f\n", mt / 1024, (mt > 0 ? (1 - ma / mt) * 100 : 0), (st > 0 ? (1 - sf / st) * 100 : 0)
    }' /proc/meminfo)

processes=$(ls -d /proc/[0-9]* 2>/dev/null | wc -l)
tcp_connections=$(ss -Htn state established 2>/dev/null | wc -l)

load1=$(cut -d' ' -f1 /proc/loadavg)
cores=$(nproc)

LOCAL_FS=(-x tmpfs -x devtmpfs -x squashfs -x efivarfs -x nfs -x nfs4 -x cifs -x overlay -x fuse.sshfs)
disks=$(awk 'FNR == 1 { file++; next }
    file == 1 && $5 ~ /%$/ { gsub(/%/, "", $5); inodes[$6] = $5 }
    file == 2 && $5 ~ /%$/ && !seen[$1]++ {
        gsub(/%/, "", $5); gsub(/["\\]/, "", $6)
        printf "%s{\"mount\":\"%s\",\"percent\":%s,\"size_gb\":%.1f,\"inode_percent\":%s}", (n++ ? "," : ""), $6, $5, $2 / 1048576, ($6 in inodes ? inodes[$6] : "null")
    }' <(df -P -i "${LOCAL_FS[@]}" 2>/dev/null) <(df -P -k "${LOCAL_FS[@]}" 2>/dev/null))

payload=$(printf '{"cpu_percent":%s,"iowait_percent":%s,"memory_percent":%s,"swap_percent":%s,"load_1":%s,"cpu_cores":%s,"memory_total_mb":%s,"net_rx_mbps":%s,"net_tx_mbps":%s,"disk_read_mbps":%s,"disk_write_mbps":%s,"processes":%s,"tcp_connections":%s,"disks":[%s]}' \
    "$cpu" "$iowait" "$mem_pct" "$swap_pct" "$load1" "$cores" "$mem_total" "$net_rx" "$net_tx" "$disk_read" "$disk_write" "$processes" "$tcp_connections" "$disks")

if [ "${1:-}" = "--dry-run" ]; then
    printf 'POST %s/server/%s/metrics\n%s\n' "$URL" "$SLUG" "$payload"
    exit 0
fi

[ -n "$TOKEN" ] || { logger -t aiku-server-metrics "no SERVER_METRICS_TOKEN in $CONFIG"; exit 1; }

code=$(curl -sS -m 20 -o /dev/null -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' -H 'Accept: application/json' -H "X-DEVOPS-TOKEN: $TOKEN" \
    --data "$payload" "$URL/server/$SLUG/metrics" 2>&1)

[ "$code" = 200 ] || logger -t aiku-server-metrics "post failed: $code"
