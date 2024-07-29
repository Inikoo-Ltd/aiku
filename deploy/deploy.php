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
    artisan('migrate --force --database=backup --path=database/migrations/backup', ['skipIfNoEnv', 'showOutput'])();
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

set('shared_dirs', ['storage','private']);
set('shared_files', ['.env','.env.testing','create_aurora_group.sh','create_aurora_organisations.sh','create_wowsbar_organisations.sh','aurora_start_migration.sh','reset_db.sh','seed_currency_exchanges_staging.sh','database/seeders/datasets/currency-exchange/currency_exchanges.dump']);
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
    'artisan:horizon:terminate'
]);
