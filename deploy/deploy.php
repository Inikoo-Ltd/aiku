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

desc('Check for changes in frontend');
task('deploy:check-fe-changes', function () {
    $changedFiles = run('git diff --name-only {{previous_release}} HEAD');
    $triggerFiles = ['resources'];
    $frontEndChanged = false;
    foreach ($triggerFiles as $triggerFile) {
        if (str_contains($changedFiles, $triggerFile)) {
            $frontEndChanged = true;
            break;
        }
    }

    set('front_end_changed', $frontEndChanged);

    writeln(sprintf('Front-end changes detected: %s', $frontEndChanged ? 'yes' : 'no'));
});


desc('🚡 Migrating database');
task('deploy:migrate', function () {
    artisan('migrate --force', ['skipIfNoEnv', 'showOutput'])();
});
desc('🏗️ Build vue app');
task('deploy:build', function () {
    $frontEndChanged = get('front_end_changed');
    if ($frontEndChanged) {
        run("cd {{release_path}} && {{bin/npm}} run build");
    } else {
        writeln('Skipping front-end build: no changes detected');
    }
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


desc('Refresh vue after deployment');
task('artisan:refresh_vue', artisan('refresh_vue'))->select('env=prod');


desc('Refresh vue after deployment');
task('deploy:refresh-vue', function () {
    $frontEndChanged = get('front_end_changed');
    if ($frontEndChanged) {
        invoke('artisan:refresh_vue');
    } else {
        writeln('Skipping refresh vue: no changes detected');
    }
});

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
]);
desc('Deploys your project');
task('deploy', [
    'deploy:unlock',
    'deploy:prepare',
    'deploy:vendors',
    'deploy:check-fe-changes',
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
 //   'artisan:inertia:stop-ssr',
    'deploy:refresh-vue',
]);
