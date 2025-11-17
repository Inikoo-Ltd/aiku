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

    $triggerFiles    = ['resources', 'vite.iris.config.mjs', 'vite.aiku-public.config.js'];
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
        // No FE changes: reuse built assets from the previous release
        writeln('No front-end changes detected. Reusing built assets from previous release if available.');
        run(
            'cd {{release_path}}/public && rsync -a {{previous_release}}/public/retina . && rsync -a {{previous_release}}/public/iris . && rsync -a {{previous_release}}/public/grp . && rsync -a {{previous_release}}/public/pupil . && rsync -a {{previous_release}}/public/aiku-public .'
        );

        run(
            'cd {{release_path}}/bootstrap && rsync -a {{previous_release}}/bootstrap/ssr . '
        );
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

desc('Save ssr checksums');
task('deploy:save-ssr-checksums', function () {
    $manifestPath = '{{release_path}}/bootstrap/ssr/ssr-manifest.json';
    $irisPath     = '{{release_path}}/bootstrap/ssr/ssr-iris.mjs';

    $manifestChecksum = '';
    $irisChecksum     = '';

    try {
        if (test('[ -f '.$manifestPath.' ]')) {
            $manifestChecksum = trim(run("sha256sum $manifestPath | awk '{print $1}'"));
        } else {
            writeln("Warning: $manifestPath not found");
        }
    } catch (\Throwable $e) {
        writeln('Error computing manifest checksum: '.$e->getMessage());
    }

    try {
        if (test('[ -f '.$irisPath.' ]')) {
            $irisChecksum = trim(run("sha256sum $irisPath | awk '{print $1}'"));
        } else {
            writeln("Warning: $irisPath not found");
        }
    } catch (\Throwable $e) {
        writeln('Error computing iris checksum: '.$e->getMessage());
    }

    // Combine both checksums and write a single checksum file
    $combined = hash('sha256', $manifestChecksum.'|'.$irisChecksum);

    $checksumFile = '{{release_path}}/SSR_CHECKSUM';
    run('printf %s '.escapeshellarg($combined).' > '.$checksumFile);
    writeln('SSR checksum saved to '.$checksumFile);



});


desc('Flush varnish cache if ssr checksum if different as previous release');
task('deploy:flush-varnish', function () {
    $currentFile  = '{{release_path}}/SSR_CHECKSUM';
    $previousFile = '{{previous_release}}/SSR_CHECKSUM';

    $current  = '';
    $previous = '';

    // Read current checksum
    try {
        if (test('[ -f '.$currentFile.' ]')) {
            $current = trim(run('cat '.$currentFile));
        } else {
            writeln('SSR checksum: current file not found, will trigger cache flush.');
        }
    } catch (\Throwable $e) {
        writeln('Error reading current SSR checksum: '.$e->getMessage());
    }

    // Read previous checksum
    try {
        if (test('[ -f '.$previousFile.' ]')) {
            $previous = trim(run('cat '.$previousFile));
        } else {
            writeln('SSR checksum: previous file not found, will trigger cache flush.');
        }
    } catch (\Throwable $e) {
        writeln('Error reading previous SSR checksum: '.$e->getMessage());
    }

    $shouldFlush = false;

    $frontEndChanged = get('front_end_changed');

    if ($previous === '' || $current === '' || $previous !== $current || $frontEndChanged) {
        $shouldFlush = true; // missing values, err on flushing
    }

    if ($shouldFlush) {
        writeln('SSR checksum changed (or missing). Flushing Varnish cache via artisan varnish...');
        try {
            artisan('varnish', ['skipIfNoEnv', 'showOutput'])();
            writeln('Varnish cache flush command executed.');
        } catch (\Throwable $e) {
            writeln('Error flushing Varnish cache: '.$e->getMessage());
        }
    } else {
        writeln('SSR checksum unchanged. Skipping Varnish cache flush.');
    }
});

desc('Restart Inertia SSR by supervisorctl');
task('restart-ssr-by-supervisorctl', function () {

    $currentFile  = '{{release_path}}/SSR_CHECKSUM';
    $previousFile = '{{previous_release}}/SSR_CHECKSUM';

    $current  = '';
    $previous = '';

    // Read current checksum
    try {
        if (test('[ -f '.$currentFile.' ]')) {
            $current = trim(run('cat '.$currentFile));
        } else {
            writeln('SSR checksum: current file not found, will trigger restart ssr.');
        }
    } catch (\Throwable $e) {
        writeln('Error reading current SSR checksum: '.$e->getMessage());
    }

    // Read previous checksum
    try {
        if (test('[ -f '.$previousFile.' ]')) {
            $previous = trim(run('cat '.$previousFile));
        } else {
            writeln('SSR checksum: previous file not found, will trigger ssr restart.');
        }
    } catch (\Throwable $e) {
        writeln('Error reading previous SSR checksum: '.$e->getMessage());
    }

    $shouldRestartSSR = false;

    $frontEndChanged = get('front_end_changed');

    if ($previous === '' || $current === '' || $previous !== $current || $frontEndChanged) {
        $shouldRestartSSR = true;
    }

    if ($shouldRestartSSR) {
        run("sudo supervisorctl restart inertia-ssr-production");
    }


})->select('env=prod');

set('keep_releases', 25);

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
    'deploy:save-ssr-checksums',
    'deploy:publish',
    'artisan:horizon:terminate',
    'deploy:sync-octane-anchor',
    'artisan:octane:reload',
    'deploy:restart-ssr-by-supervisorctl',
    'deploy:refresh-vue',
    'deploy:flush-varnish',
]);
