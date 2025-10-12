<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jan 2024 14:23:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Deployer;

set('update_code_strategy', 'clone');

set('bin/php', function () {
    return '/usr/bin/php8.3';
});

desc('Check for changes in frontend');
task('deploy:check-fe-changes', function () {
    try {
        $prevHash = trim(run('cat {{previous_release}}/REVISION'));
    } catch (\Throwable $e) {
        $prevHash = null;
    }

    if (!empty($prevHash)) {
        try {
            $changedFiles = run("cd {{release_path}} && git diff --name-only $prevHash HEAD");
        } catch (\Throwable $e) {
            writeln('Previous release hash not .git folder. Assuming front-end changed.');
            $changedFiles = 'resources'; // force detection
        }
    } else {
        $changedFiles = 'resources'; // force detection
    }

    $triggerFiles    = ['resources'];
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
    if ($frontEndChanged || true) {
        run("cd {{release_path}} && {{bin/npm}} run build");





    } else {
        // No FE changes: reuse built assets from previous release
        writeln('No front-end changes detected. Reusing built assets from previous release if available.');
        try {
            run('if [ -d {{previous_release}} ]; then for d in iris grp retina pupil aiku-public; do if [ -d "{{previous_release}}/public/$d" ]; then mkdir -p "{{release_path}}/public/$d" && rsync -ahHq --delete "{{previous_release}}/public/$d/" "{{release_path}}/public/$d/"; fi; done; else echo "No previous release found, skipping asset copy"; fi');
        } catch (\Throwable $e) {
            writeln('Failed to copy built assets from previous release: '.($e->getMessage() ?? 'unknown error'));
        }
    }
});

desc('Set release');
task('deploy:set-release', function () {
    run("cd {{release_path}} && sed -i~ '/^RELEASE=/s/=.*/=\"{{release_semver}}\"/' .env   ");
});


desc('Sync octane anchor');
task('deploy:sync-octane-anchor', function () {
    run("rsync -ahHq --delete {{release_path}}/ {{deploy_path}}/anchor/octane");
});

desc('Stops inertia SSR server');
task('artisan:inertia:stop-ssr', artisan('inertia:stop-ssr'))->select('env=prod');


desc('Refresh vue after deployment');
task('artisan:refresh_vue', artisan('deploy:refresh_vue'))->select('env=prod');


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
    'deploy:set-release',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'artisan:migrate',
    'deploy:check-fe-changes',
    'deploy:build',
    'deploy:publish',
    'artisan:horizon:terminate',
    'deploy:sync-octane-anchor',
    'artisan:octane:reload',
    //   'artisan:inertia:stop-ssr',
    'deploy:refresh-vue',
]);
