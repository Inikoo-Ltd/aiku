#!/bin/bash
# Regenerates public/art/readme/thumb-*.jpg from a throwaway demo database (fake data only).
# The draw-*.svg illustrations are hand-authored and not touched here.
set -e
cd "$(dirname "$0")/../.."
TEST_TOKEN=readme php -d xdebug.mode=off vendor/bin/pest tests/Browser/ReadmeDemoSeedTest.php --group=browser
psql -U "${DB_USERNAME:-raul}" -h 127.0.0.1 aiku_test_readme -q <<'SQL'
update groups set name='Northwind Group', currency_id=(select id from currencies where code='GBP');
update organisations set currency_id=(select id from currencies where code='GBP'), country_id=(select id from countries where code='GB');
update shops set slug='nhg', code='NHG', state='open', currency_id=(select id from currencies where code='GBP'), country_id=(select id from countries where code='GB'), language_id=(select id from languages where code='en');
update warehouses set slug='main', code='MAIN', name='Main Warehouse';
update customers set slug=regexp_replace(slug,'-[a-z]+$','-nhg');
update invoices set grp_net_amount=net_amount, goods_amount=net_amount, currency_id=(select id from currencies where code='GBP');
update orders set grp_net_amount=net_amount, total_amount=net_amount, currency_id=(select id from currencies where code='GBP'), reference=coalesce(reference,lpad(id::text,6,'0')), dispatched_at=coalesce(dispatched_at,date), pay_status=case when state='dispatched' then 'paid' else pay_status end;
truncate dashboard_time_series_aggregates;
SQL
for c in hydrate shops:redo_time_series organisations:redo_time_series groups:redo_time_series customers:redo_time_series; do
  DB_DATABASE=aiku_test_readme NIGHTWATCH_ENABLED=false php -d xdebug.mode=off artisan $c >/dev/null
done
DB_DATABASE=aiku_test_readme php -d xdebug.mode=off vendor/bin/pest tests/Browser/ReadmeScreenshotsTest.php --group=browser
mkdir -p public/art/readme
for f in tests/Browser/Screenshots/readme-*.png; do
  n=$(basename "${f%.png}"); n=${n#readme-}
  [ "$(stat -f%z "$f")" -lt 20000 ] && continue
  magick "$f" -resize 320x -strip -quality 70 "public/art/readme/thumb-$n.jpg"
done
echo "done → public/art/readme"
