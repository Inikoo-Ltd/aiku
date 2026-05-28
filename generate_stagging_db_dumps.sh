#!/bin/bash
PURPLE='\033[01;35m'
ITALIC='\e[3m'
NONE='\033[00m'

DB=seed_staging
DB_PORT=5432
DB_COLLATE=C.UTF-8
PHP=php8.4
USER=staging
HOST=localhost

echo -e "✨ Resetting database ${ITALIC}${DB}${NONE}"
dropdb --if-exists -p "${DB_PORT}" -U "${USER}" -h "${HOST}" -f -w ${DB}
createdb -p "${DB_PORT}" -U "${USER}" -h "${HOST}"  --template=template0 --lc-collate="${DB_COLLATE}" --lc-ctype="${DB_COLLATE}" ${DB}
echo "🌱 Migrating and seeding database"
${PHP} artisan config:clear
${PHP} artisan cache:clear
${PHP} artisan --env=testing migrate
${PHP} artisan --env=testing db:seed
echo -e "💾 Saving ${PURPLE}seed_staging.dump${NONE}"
pg_dump -Fc -p "${DB_PORT}" -U "${USER}" -f "tests/datasets/db_dumps/seed_staging.dump" ${DB}
echo "Staging seed  DB dumped 👍"
${PHP} artisan config:cache