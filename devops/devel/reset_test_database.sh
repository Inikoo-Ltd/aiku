#!/bin/bash

if [ -z "$7" ]; then
  JOBS=16
else
  JOBS="$7"
fi

PGPASSWORD=$4 psql -U "$3" -p "$2" -h "$5" -d postgres  -c "drop database $1 WITH (FORCE)"
PGPASSWORD=$4 psql -U "$3" -p "$2" -h "$5" -d postgres  -c "create database $1"
PGPASSWORD=$4 pg_restore --no-owner --no-acl --clean --if-exists -j "$JOBS" -U "$3" -p "$2" -h "$5" -c -d "$1" "$6"
