#!/bin/bash
PURPLE='\033[01;35m'
ITALIC='\e[3m'
NONE='\033[00m'

DB=aiku_test
DB_PORT=5432
DB_COLLATE=C.UTF-8
PHP=php8.3
USER=aiku
HOST=localhost

PHP="${1:-$PHP}"
USER="${2:-$USER}"
HOST="${3:-$HOST}"
DB_PORT="${4:-$DB_PORT}"
DB_COLLATE="${5:-$DB_COLLATE}"

echo -e "✨ Resetting elasticsearch"
${PHP} artisan es:refresh --env=testing
echo -e "✨ Resetting database ${ITALIC}${DB}${NONE}"
dropdb --if-exists -p "${DB_PORT}" -U "${USER}" -h "${HOST}" -f -w ${DB}
createdb -p "${DB_PORT}" -U "${USER}" -h "${HOST}"  --template=template0 --lc-collate="${DB_COLLATE}" --lc-ctype="${DB_COLLATE}" ${DB}
echo "🌱 Migrating and seeding database"
${PHP} artisan --env=testing migrate
${PHP} artisan --env=testing db:seed
echo -e "💾 Saving ${PURPLE}fresh_with_assets.dump${NONE}"
pg_dump -Fc -p "${DB_PORT}" -U "${USER}" -f "tests/datasets/db_dumps/aiku.dump" ${DB}
echo "Test DB dumped 👍"