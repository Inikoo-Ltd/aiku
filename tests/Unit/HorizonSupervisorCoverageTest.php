<?php

it('overrides every supervisor in every horizon environment', function (string $environment) {
    $supervisors = array_keys(config('horizon.defaults'));
    $block       = config("horizon.environments.$environment");
    $overridden  = array_keys($block);

    expect(array_diff($supervisors, $overridden))->toBeEmpty(
        "Supervisors missing from the $environment block inherit the defaults, which are sized for production"
    );
    $incompleteExtras = array_filter(
        array_diff($overridden, $supervisors),
        fn (string $supervisor) => ! isset($block[$supervisor]['connection'], $block[$supervisor]['queue'])
    );
    expect($incompleteExtras)->toBeEmpty(
        "The $environment block names supervisors that are not in the defaults and lack their own connection and queue"
    );
})->with(['production', 'staging', 'local']);

it('works every queue on its own connection', function (string $environment) {
    $working = array_filter(
        array_replace_recursive(config('horizon.defaults'), config("horizon.environments.$environment")),
        fn (array $supervisor) => $supervisor['maxProcesses'] > 0
    );
    $worked = [];
    foreach ($working as $supervisor) {
        foreach ((array) $supervisor['queue'] as $queue) {
            $worked[$supervisor['connection']][$queue] = true;
        }
    }
    $unworked = [];
    foreach (config('horizon.defaults') as $supervisor) {
        foreach ((array) $supervisor['queue'] as $queue) {
            if (! isset($worked[$supervisor['connection']][$queue])) {
                $unworked[] = $supervisor['connection'].':'.$queue;
            }
        }
    }

    expect($unworked)->toBeEmpty("Jobs on these queues are never run in $environment");
})->with(['production', 'local']);

it('keeps local horizon to one supervisor per connection', function () {
    $working = array_filter(
        config('horizon.environments.local'),
        fn (array $supervisor) => $supervisor['maxProcesses'] > 0
    );
    $connections = array_values(array_unique(array_column(config('horizon.defaults'), 'connection')));

    expect(array_keys($working))->toEqualCanonicalizing($connections);
});

it('gives every supervisor room for a booted aiku process', function () {
    $tooSmall = array_keys(array_filter(
        config('horizon.defaults'),
        fn (array $supervisor) => $supervisor['memory'] < 512
    ));

    expect($tooSmall)->toBeEmpty(
        'A booted supervisor alone takes about 380 MB and Horizon stops one over its memory limit, so these never start a worker'
    );
});
