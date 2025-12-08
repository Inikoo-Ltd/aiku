#!/bin/bash
pg_restore --no-owner --no-acl --clean --if-exists -j 15 -U ${USER} -c -d aiku  "database/seeders/datasets/currency-exchange/currency_exchanges.dump"
