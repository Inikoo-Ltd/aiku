#!/usr/bin/env bash
#
# Weekly staging refresh: pull production data from the REPLICA, restore into
# aiku_staging, migrate, sanitize, reindex, restart. Run on neon as root.
#
# Data safety: dumps from the replica (helio) so the primary takes no load;
# runs staging-sanitize.sql so live payment/store/shipper/CF credentials never
# reach staging. Email is caught by mailhog (.env).
#
# Prereqs (one-time, for scheduling): the staging user on neon needs an SSH key
# trusted by BOTH helio (DB dump, $REPLICA) and boro (media, $MEDIA_SRC) so this
# runs non-interactively. (The initial manual load instead pushes from boro/helio,
# whose keys are already on neon.) Review before scheduling.
# NOTE: `private/` is a one-time copy, deliberately NOT synced weekly.
#
# For the INITIAL FULL load, don't use this — do the manual full dump/restore
# once (see notes at bottom). This script is the recurring TRIMMED refresh.
#
set -euo pipefail

REPLICA="${REPLICA:-helio}"                 # ssh alias of the prod replica (DB dump)
MEDIA_SRC="${MEDIA_SRC:-boro:/home/aiku/aiku/shared/storage/media/}"  # ssh alias defined on neon
PROD_DB="${PROD_DB:-aiku}"
STAGING_DB="${STAGING_DB:-aiku_staging}"
STAGING_ROLE="${STAGING_ROLE:-staging}"
APP_DIR="/home/staging/aiku/current"
DUMP="/home/staging/dumps/refresh-$(date +%F).dump"
SANITIZE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/staging-sanitize.sql"

# Big log/history tables — structure kept, data skipped (staging doesn't need them)
EXCLUDES=(
  dispatched_emails email_tracking_events email_copies
  mailshot_has_dispatched_emails customer_has_dispatched_emails
  location_org_stock_histories org_stock_histories org_stock_movements audits
)
EXCLUDE_ARGS=(); for t in "${EXCLUDES[@]}"; do EXCLUDE_ARGS+=(--exclude-table-data="$t"); done

log(){ echo "[$(date +%T)] $*"; }

log "1/8 dump from replica $REPLICA (trimmed)"
ssh -o BatchMode=yes "$REPLICA" "pg_dump -Fc -x --no-owner -Z3 -d $PROD_DB ${EXCLUDE_ARGS[*]}" > "$DUMP"
log "    dump size: $(du -h "$DUMP" | cut -f1)"

log "2/8 stop app (release DB connections)"
supervisorctl stop octane horizon || true

log "3/8 drop + recreate $STAGING_DB"
sudo -u postgres psql -c "DROP DATABASE IF EXISTS $STAGING_DB;"
sudo -u postgres createdb -O "$STAGING_ROLE" --template=template0 --encoding=UTF8 --lc-collate=C.UTF-8 --lc-ctype=C.UTF-8 "$STAGING_DB"
sudo -u postgres psql -d "$STAGING_DB" -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE EXTENSION IF NOT EXISTS unaccent; CREATE EXTENSION IF NOT EXISTS pg_stat_statements;"

log "4/8 restore"
sudo -u postgres pg_restore --no-owner --role="$STAGING_ROLE" -x -j8 -d "$STAGING_DB" "$DUMP" || log "    (restore reported non-fatal errors, continuing)"

log "5/8 migrate (bring prod schema up to staging branch)"
sudo -u staging php8.4 "$APP_DIR/artisan" migrate --force

log "6/8 SANITIZE (wipe live credentials)"
sudo -u postgres psql -d "$STAGING_DB" -f "$SANITIZE"

log "7/9 clear caches + reindex search"
sudo -u staging php8.4 "$APP_DIR/artisan" optimize:clear
sudo -u staging php8.4 "$APP_DIR/artisan" search -r

log "8/9 sync media from prod (incremental; --delete mirrors prod)"
sudo -u staging rsync -aH --delete -e "ssh -o BatchMode=yes" "$MEDIA_SRC" /home/staging/aiku/shared/storage/media/

log "9/9 start app"
supervisorctl start octane horizon
rm -f "$DUMP"
log "done. staging refreshed from $REPLICA."

# ---------------------------------------------------------------------------
# INITIAL FULL LOAD (manual, one time — not this script):
#   # on the replica, streamed straight onto neon:
#   ssh -A helio 'pg_dump -Fc -x --no-owner -Z3 -d aiku \
#     | ssh staging@<neon> "cat > /home/staging/dumps/aiku-prod-full.dump"'
#   # then on neon:
#   sudo -u postgres pg_restore --no-owner --role=staging -x -j8 \
#     -d aiku_staging /home/staging/dumps/aiku-prod-full.dump
#   # then: migrate, staging-sanitize.sql, optimize:clear, artisan search -r
# ---------------------------------------------------------------------------
