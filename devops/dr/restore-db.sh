#!/usr/bin/env bash
#
# Restore the production database from pgBackRest in S3 Frankfurt onto this box, timed
# (INI-133; the DR document, step 4). Needs the instance's read-only IAM role and the
# data volume mounted on the cluster folder: the restored ~320 GB must never land on the
# root volume, or it ends up in the next AMI.
#
# Usage (as root):
#     restore-db.sh                                     # every change up to the last archived WAL
#     restore-db.sh "2026-10-30 10:15:00+00"            # to a moment, then promote
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run as root"; exit 1; }

DATA=/var/lib/postgresql/18/main
started=$(date +%s)
log() { echo "[$(date -u +%T) UTC, +$(( ($(date +%s) - started) / 60 )) min] $*"; }

# The data disk: the empty 1 TB one launch.sh attaches. Only a disk with no filesystem and
# nothing mounted is ever formatted.
if ! mountpoint -q "$DATA"; then
  disk=$(lsblk -dbnpo NAME,SIZE,TYPE | awk '$3=="disk" && $2 >= 500e9 {print $1}' | while read -r d; do
    [[ -z $(lsblk -no FSTYPE,MOUNTPOINT "$d" | tr -d ' \n') ]] && echo "$d"; done | head -1 || true)
  [[ -n $disk ]] || { echo "FAIL: no empty data disk of 500 GB or more, and nothing mounted on $DATA"; exit 1; }
  mkfs.xfs -q "$disk"
  echo "UUID=$(blkid -s UUID -o value "$disk") $DATA xfs defaults,noatime,nofail 0 2" >> /etc/fstab
  mount "$DATA"
  echo "OK: data disk $disk ready on $DATA"
fi

target=()
[[ -n ${1:-} ]] && target=(--type=time "--target=$1" --target-action=promote)

runuser -u postgres -- pgbackrest --stanza=aiku info

log "restore starts"
systemctl stop postgresql@18-main 2>/dev/null || true
chown postgres:postgres "$DATA"
chmod 700 "$DATA"
find "$DATA" -mindepth 1 -delete
# --archive-mode=off: the restored files carry production's archive_mode=on, and this box
# must never push WAL into the production repository (its role cannot write, so the WAL
# would pile up on disk instead)
runuser -u postgres -- pgbackrest --stanza=aiku --archive-mode=off "${target[@]}" restore
log "files restored, WAL replay starts"

# Recovery refuses to start below the primary's limits; hot_standby off lets it replay
# without serving reads, so psql answers only once the database is promoted.
cat > /etc/postgresql/18/main/conf.d/90-aiku-dr.conf <<'CONF'
listen_addresses = '*'
max_connections = 1000
max_worker_processes = 51
max_locks_per_transaction = 1024
hot_standby = off
shared_buffers = 32GB
max_wal_size = 32GB
checkpoint_timeout = 30min
wal_compression = lz4
CONF
# The app reaches the database at boro's and litio's addresses, both on lo here
hba=/etc/postgresql/18/main/pg_hba.conf
grep -q '10.0.0.0/24' "$hba" || echo 'host all all 10.0.0.0/24 md5' >> "$hba"
# md5 accepts scram and md5 hashes alike, as on boro
sed -i 's/scram-sha-256$/md5/' "$hba"
# boro's ALTER SYSTEM values come with the files and override conf.d: check them for this box
echo "--- restored postgresql.auto.conf:"; cat "$DATA/postgresql.auto.conf"
systemctl enable postgresql
systemctl start postgresql@18-main

# Never kill pgbackrest archive-get while this waits: PostgreSQL would take it as the end
# of the WAL and finish recovery early (24 Oct 2026). If that happens, start it again.
until [[ $(runuser -u postgres -- psql -tAc 'select not pg_is_in_recovery()' 2>/dev/null) == t ]]; do
  sleep 15
done
echo "OK: database restored"
log "database ready: $(runuser -u postgres -- psql -tAc "select pg_size_pretty(pg_database_size('aiku'))") , last change $(runuser -u postgres -- psql -d aiku -tAc 'select max(created_at) from audits')"
