#!/usr/bin/env bash
#
# Weekly staging refresh: pull a trimmed production dump + media from boro,
# restore into aiku_staging on neon, migrate, SANITIZE, reindex, restart.
# Runs on neon as root (cron, Sunday 03:00 UTC).
#
# Source is the PRODUCTION PRIMARY (boro) by deliberate choice: the helio
# replica cancels long dumps with recovery conflicts. The dump is trimmed
# (giant log tables excluded) and scheduled off-peak to keep primary load low.
#
# Safety invariants:
#   - The app NEVER starts against an un-sanitized DB: sanitize runs before
#     start, and on ANY failure the DB is dropped and the app stays stopped.
#   - The dump file (full prod secrets) is deleted on every exit path.
#   - All destructive commands run on the LOCAL postgres socket only; the only
#     remote commands are read-only (pg_dump, rsync pull).
# NOTE: `private/` is a one-time copy, deliberately NOT synced weekly.
#
set -euo pipefail

REPLICA="${REPLICA:-boro}"
MEDIA_SRC="${MEDIA_SRC:-boro:/home/aiku/aiku/shared/storage/media/}"
PROD_DB="${PROD_DB:-aiku}"
STAGING_DB="${STAGING_DB:-aiku_staging}"
STAGING_ROLE="${STAGING_ROLE:-staging}"
APP_DIR="/home/staging/aiku/current"
DUMP_DIR="/home/staging/dumps"
DUMP="$DUMP_DIR/refresh-$(date +%F).dump"
SANITIZE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/staging-sanitize.sql"

# allow-list guard: critical vars may only contain [a-zA-Z0-9_.-] (anything
# else could be re-parsed by the remote shell on the pg_dump line)
for v in "$REPLICA" "$PROD_DB" "$STAGING_DB" "$STAGING_ROLE"; do
  case "$v" in
    ""|*[!a-zA-Z0-9_.-]*) echo "ABORT: unsafe or empty variable: '$v'"; exit 1;;
  esac
done

# Big log/history tables — structure kept, data skipped (staging doesn't need
# them; failed_jobs excluded so prod job payloads never even land on disk here)
EXCLUDES=(
  dispatched_emails email_tracking_events email_copies
  mailshot_has_dispatched_emails customer_has_dispatched_emails
  location_org_stock_histories org_stock_histories org_stock_movements audits
  failed_jobs
)
EXCLUDE_ARGS=""; for t in "${EXCLUDES[@]}"; do EXCLUDE_ARGS+=" --exclude-table-data=$t"; done

log(){ echo "[$(date +%T)] $*"; }

SANITIZED=0
DONE=0
cleanup(){
  rm -f "$DUMP"
  [ "$DONE" = 1 ] && return
  if [ "$SANITIZED" != 1 ]; then
    log "FAILED before sanitize completed — dropping DB and keeping app STOPPED"
    sudo -u postgres psql -c "DROP DATABASE IF EXISTS $STAGING_DB;" || true
    touch /home/staging/logs/refresh-staging.FAILED
    log "staging is DOWN; investigate, then re-run this script"
  else
    # failure after sanitize: data is safe — bring the app back up and flag it
    log "FAILED after sanitize (reindex/media step) — data is safe, restarting app"
    supervisorctl start octane horizon || true
    touch /home/staging/logs/refresh-staging.FAILED
  fi
}
trap cleanup EXIT

install -d -m 700 -o staging -g staging "$DUMP_DIR"
rm -f /home/staging/logs/refresh-staging.FAILED

log "1/9 dump from $REPLICA (trimmed, read-only)"
sudo -u staging ssh -o BatchMode=yes "$REPLICA" "pg_dump -Fc -x --no-owner -Z3 -d $PROD_DB$EXCLUDE_ARGS" > "$DUMP"
log "    dump size: $(du -h "$DUMP" | cut -f1)"

log "2/9 validate dump BEFORE any destructive step"
setfacl -m u:postgres:x /home/staging "$DUMP_DIR"
setfacl -m u:postgres:r "$DUMP"
sudo -u postgres pg_restore -l "$DUMP" >/dev/null || { log "dump corrupt/truncated — aborting with staging untouched"; exit 1; }

log "3/9 stop app + drop + recreate $STAGING_DB (local socket only)"
supervisorctl stop octane horizon || true
sudo -u postgres psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$STAGING_DB' AND pid<>pg_backend_pid();" >/dev/null || true
sudo -u postgres psql -c "DROP DATABASE IF EXISTS $STAGING_DB;"
sudo -u postgres createdb -O "$STAGING_ROLE" --template=template0 --encoding=UTF8 --lc-collate=C.UTF-8 --lc-ctype=C.UTF-8 "$STAGING_DB"
sudo -u postgres psql -d "$STAGING_DB" -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE EXTENSION IF NOT EXISTS unaccent; CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"

log "4/9 restore"
# pg_restore exits non-zero on the expected extension-comment errors; verify
# real success via table count instead of trusting the exit code blindly
sudo -u postgres pg_restore --no-owner --role="$STAGING_ROLE" -x -j8 -d "$STAGING_DB" "$DUMP" || true
TABLES=$(sudo -u postgres psql -d "$STAGING_DB" -tAc "SELECT count(*) FROM pg_tables WHERE schemaname='public';")
[ "$TABLES" -gt 500 ] || { log "restore produced only $TABLES tables — aborting"; exit 1; }
log "    restored $TABLES tables"

log "5/9 migrate (bring prod schema up to staging branch)"
sudo -u staging php8.4 "$APP_DIR/artisan" migrate --force

log "6/9 SANITIZE (neutralize live credentials + queue remnants)"
setfacl -m u:postgres:r "$SANITIZE" 2>/dev/null || true
sudo -u postgres psql -v ON_ERROR_STOP=1 -d "$STAGING_DB" -f "$SANITIZE"
SANITIZED=1

log "7/9 clear caches + reindex search"
sudo -u staging php8.4 "$APP_DIR/artisan" optimize:clear
sudo -u staging php8.4 "$APP_DIR/artisan" horizon:clear 2>/dev/null || true
sudo -u staging php8.4 "$APP_DIR/artisan" search -r

log "8/9 sync media from prod (incremental; --delete mirrors prod)"
sudo -u staging rsync -aH --delete -e "ssh -o BatchMode=yes" "$MEDIA_SRC" /home/staging/aiku/shared/storage/media/

log "9/9 start app"
supervisorctl start octane horizon
DONE=1
log "done. staging refreshed from $REPLICA."
