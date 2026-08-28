#!/bin/bash

if [ -z "$7" ]; then
  JOBS=16
else
  JOBS="$7"
fi

PGPASSWORD=$4 psql -U "$3" -p "$2" -h "$5" -d postgres  -c "drop database $1 WITH (FORCE)"
PGPASSWORD=$4 psql -U "$3" -p "$2" -h "$5" -d postgres  -c "create database $1"
PGPASSWORD=$4 pg_restore --no-owner --no-acl --clean --if-exists -j "$JOBS" -U "$3" -p "$2" -h "$5" -c -d "$1" "$6"
PGPASSWORD=$4 psql -v ON_ERROR_STOP=1 -U "$3" -p "$2" -h "$5" -d "$1" -c "
DO \$\$
DECLARE
    sequence_record record;
BEGIN
    FOR sequence_record IN
        SELECT
            pg_get_serial_sequence(format('%I.%I', table_schema, table_name), column_name) AS sequence_name,
            format('%I.%I', table_schema, table_name) AS qualified_table_name,
            quote_ident(column_name) AS quoted_column_name
        FROM information_schema.columns
        WHERE column_default LIKE 'nextval(%'
    LOOP
        EXECUTE format(
            'SELECT setval(%L, COALESCE(MAX(%s), 1), MAX(%s) IS NOT NULL) FROM %s',
            sequence_record.sequence_name,
            sequence_record.quoted_column_name,
            sequence_record.quoted_column_name,
            sequence_record.qualified_table_name
        );
    END LOOP;
END
\$\$;"
