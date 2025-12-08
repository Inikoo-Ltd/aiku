#!/bin/bash
pg_restore  -U raul -c -d aiku_test < "$1"
