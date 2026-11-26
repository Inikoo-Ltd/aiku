#!/usr/bin/env bash
#
# Smaller copy of the production database for developers' machines (INI-514).
# The full aiku_latest.dump (48 GB) stays as it is; this one is made after it as
# aiku_dev.dump + aiku_dev_rows.sql.gz and is about 30 GB.
#
# What it leaves out (tables are kept, their rows are not):
#   - logs and history: audits, request/job/visit logs, crawl and Search Console,
#     stock histories, sent emails and their tracking, notifications
#   - every table with a foreign key into one of those, found at dump time and
#     printed in the log, because their rows would point at nothing
#   - the shipment label PDFs (shipments.label), and supplier_messages keep their
#     rows with dispatched_email_id blanked, so procurement notes and agreed terms stay
#   - bank_* and manufacture_payroll_adjustments, as the full dump does; agent_payments
#     keep their rows with bank_transaction_id blanked
#   - what staff wrote to each other: staff_messages and slack_messages (with their
#     reactions, translations, versions and files); conversations and channels stay
# org_stock_movements stays: pickings point at it.
#
# Runs on boro as inikoo, after backup_aiku.sh, from the primary. Everything is
# read in one snapshot, so the rows dumped separately match the rest of the dump.
# It writes to boro's own disk, not the /sel storage box: large writes over CIFS
# fail there with "Cannot allocate memory", and this copy is not a backup.
#     install -m 755 devops/postgres/aiku-dev-dump.sh /usr/local/bin/aiku-dev-dump
#     /etc/crontab:  30 3 * * * inikoo cd /home/inikoo; ./backup_aiku.sh; /usr/local/bin/aiku-dev-dump
#
# Developers download both files (rsync resumes a dropped connection, scp starts over):
#     rsync -P --append-verify boro:/home/inikoo/dev-dump/aiku_dev.dump boro:/home/inikoo/dev-dump/aiku_dev_rows.sql.gz .
# and restore them with devops/postgres/restore-dev-dump.sh.
#
set -euo pipefail

DB="${AIKU_DEV_DUMP_DB:-aiku}"
OUT_DIR="${AIKU_DEV_DUMP_DIR:-/home/inikoo/dev-dump}"
DUMP="$OUT_DIR/aiku_dev.dump"
ROWS="$OUT_DIR/aiku_dev_rows.sql.gz"

EMPTY_TABLES=(
  audits user_requests web_user_requests website_page_views website_visitors
  customer_web_activities product_last_seens traffic_source_clicks retina_api_requests
  fetches fetch_stacks scheduled_task_logs payment_gateway_logs debug_webhooks failed_jobs
  location_org_stock_histories org_stock_histories
  dispatched_emails email_tracking_events email_copies mailshot_recipients
  mailshot_has_dispatched_emails customer_has_dispatched_emails ses_notifications notifications
  manufacture_payroll_adjustments
  staff_messages slack_messages
)
EMPTY_PATTERNS=('crawl\_%' 'search\_console\_%' 'bank\_%')
NULLED_COLUMNS=(shipments.label supplier_messages.dispatched_email_id agent_payments.bank_transaction_id)

log(){ echo "[$(date +%T)] $*"; }

sql_array(){ local out="" x; for x in "$@"; do out+="${out:+,}'$x'"; done; echo "ARRAY[$out]::text[]"; }

mkdir -p "$OUT_DIR"

cleanup(){ rm -f "$DUMP.tmp" "$ROWS.tmp"; }
trap cleanup EXIT

coproc HOLD { psql -X -qAt -v ON_ERROR_STOP=1 -d "$DB"; }
ask(){ echo "$1" >&"${HOLD[1]}"; read -r ANSWER <&"${HOLD[0]}"; }

ask "SET idle_in_transaction_session_timeout = 0; SET statement_timeout = 0;
     BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
     LOCK TABLE $(printf 'public.%s, ' "${NULLED_COLUMNS[@]%%.*}") pg_catalog.pg_class IN ACCESS SHARE MODE;
     SELECT pg_export_snapshot();"
SNAPSHOT="$ANSWER"

ask "WITH RECURSIVE nulled AS (
       SELECT split_part(x, '.', 1) AS relname, split_part(x, '.', 2) AS attname
       FROM unnest($(sql_array "${NULLED_COLUMNS[@]}")) x
     ), emptied AS (
       SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
       WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND NOT c.relispartition
         AND (c.relname = ANY ($(sql_array "${EMPTY_TABLES[@]}")) OR c.relname LIKE ANY ($(sql_array "${EMPTY_PATTERNS[@]}")))
       UNION
       SELECT k.conrelid FROM pg_constraint k JOIN emptied e ON k.confrelid = e.oid
       WHERE k.contype = 'f' AND NOT EXISTS (
         SELECT 1 FROM nulled x JOIN pg_attribute a ON a.attrelid = k.conrelid AND a.attname = x.attname
         WHERE x.relname = k.conrelid::regclass::text AND k.conkey = ARRAY[a.attnum])
     )
     SELECT string_agg(c.relname, ' ' ORDER BY c.relname) FROM emptied JOIN pg_class c USING (oid);"
read -ra EMPTIED <<<"$ANSWER"
log "emptied: ${EMPTIED[*]}"

EXCLUDE_ARGS=()
for t in "${EMPTIED[@]}"; do EXCLUDE_ARGS+=("--exclude-table-data-and-children=public.$t"); done
for nc in "${NULLED_COLUMNS[@]}"; do EXCLUDE_ARGS+=("--exclude-table-data=public.${nc%%.*}"); done

log "pg_dump"
pg_dump -Fc -x --snapshot="$SNAPSHOT" "${EXCLUDE_ARGS[@]}" -f "$DUMP.tmp" "$DB"

log "rows with blanked columns"
COPY_COMMANDS=""
for nc in "${NULLED_COLUMNS[@]}"; do
  table="${nc%%.*}" column="${nc#*.}"
  ask "SELECT string_agg(quote_ident(attname), ',' ORDER BY attnum) || '|' ||
              string_agg(CASE WHEN attname = '$column' THEN 'NULL' ELSE quote_ident(attname) END, ',' ORDER BY attnum)
       FROM pg_attribute WHERE attrelid = 'public.$table'::regclass AND attnum > 0 AND NOT attisdropped AND attgenerated = '';"
  [[ "$ANSWER" == *"|"*NULL* ]] || { log "column $nc not found"; exit 1; }
  COPY_COMMANDS+="\\qecho 'COPY public.$table (${ANSWER%%|*}) FROM stdin;'"$'\n'
  COPY_COMMANDS+="COPY (SELECT ${ANSWER#*|} FROM public.$table) TO STDOUT;"$'\n'
  COPY_COMMANDS+="\\qecho '\\\\.'"$'\n'
done
printf '%s\n%s%s\n' "\\o | gzip -c > '$ROWS.tmp'" "$COPY_COMMANDS" "\\o" >&"${HOLD[1]}"
ask "SELECT 'done';"
printf 'COMMIT;\n\\q\n' >&"${HOLD[1]}"
wait

pg_restore -l "$DUMP.tmp" >/dev/null
gzip -t "$ROWS.tmp"
mv "$DUMP.tmp" "$DUMP"
mv "$ROWS.tmp" "$ROWS"
log "done: $(du -h "$DUMP" | cut -f1) + $(du -h "$ROWS" | cut -f1)"
