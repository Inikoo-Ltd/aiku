#!/usr/bin/env bash
#
# Restores the developer copy of production (INI-514, made by devops/postgres/aiku-dev-dump.sh)
# into an empty local database:
#     devops/postgres/restore-dev-dump.sh ~/Downloads aiku
# The folder holds aiku_dev.dump and aiku_dev_rows.sql.gz. The rows file goes in
# between the data and the constraints, so the foreign keys into shipments hold.
# pg_restore reports the usual errors about extension comments; they are harmless.
#
set -uo pipefail

DIR="${1:?usage: restore-dev-dump.sh <folder with aiku_dev.dump> [database]}"
DB="${2:-aiku}"

pg_restore -x --no-owner -d "$DB" --section=pre-data "$DIR/aiku_dev.dump"
pg_restore -x --no-owner -j 8 -d "$DB" --section=data "$DIR/aiku_dev.dump"
gunzip -c "$DIR/aiku_dev_rows.sql.gz" | psql -X -q -1 -v ON_ERROR_STOP=1 -d "$DB" || { echo "loading aiku_dev_rows.sql.gz failed"; exit 1; }
pg_restore -x --no-owner -j 8 -d "$DB" --section=post-data "$DIR/aiku_dev.dump"
