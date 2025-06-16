<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jan 2024 14:23:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Deployer;

set('bin/php', function () {
    return '/usr/bin/php8.3';
});

desc('🚡 Migrating database');
task('deploy:migrate', function () {
    artisan('migrate --force', ['skipIfNoEnv', 'showOutput'])();
});
desc('🏗️ Build vue app');
task('deploy:build', function () {
    run("cd {{release_path}} && {{bin/npm}} run build");
});

desc('Set release');
task('deploy:set-release', function () {
    run("cd {{release_path}} && sed -i~ '/^RELEASE=/s/=.*/=\"{{release_semver}}\"/' .env   ");
});


desc('Sync octane anchor');
task('deploy:sync-octane-anchor', function () {
    run("rsync -avhH --delete {{release_path}}/ {{deploy_path}}/anchor/octane");
});

desc('Stops inertia SSR server');
task('artisan:inertia:stop-ssr', artisan('inertia:stop-ssr'))->select('env=prod');

set('keep_releases', 15);

set('shared_dirs', ['storage', 'private']);
set('shared_files', [
    'isdoc-pdf',
    'rgb.icc',
    'rr',
    '.rr.yaml',
    '.env',
    '.env.testing',
    '.user.ini',
    'aurora_accounting_migration.sh',
    'aurora_catalogue_migration.sh',
    'aurora_comms_migration.sh',
    'aurora_crm_migration.sh',
    'aurora_ds_migration.sh',
    'aurora_fulfilment_migration.sh',
    'aurora_hr_migration.sh',
    'aurora_inventory_migration.sh',
    'aurora_orders_migration.sh',
    'aurora_procurement_migration.sh',
    'aurora_sales_migration.sh',
    'aurora_stock_migration.sh',
    'aurora_warehouse_migration.sh',
    'aurora_comms_migration.sh',
    'aurora_discounts_migration.sh',
    'aurora_website_migration.sh',
    'aurora_create_group.sh',
    'aurora_create_organisations.sh',
    'wowsbar_create_organisations.sh',
    'aurora_start_migration.sh',
    'reset_db.sh',
    'seed_currency_exchanges_staging.sh',
    'seed_currency_exchanges.sh',
    'database/seeders/datasets/currency-exchange/currency_exchanges.dump'
]);
desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:set-release',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'artisan:migrate',
    'deploy:build',
    'deploy:publish',
    'artisan:horizon:terminate',
    'deploy:sync-octane-anchor',
    'artisan:octane:reload',
    'artisan:inertia:stop-ssr',
]);
