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

it('gives every supervisor room for a booted aiku process', function () {
    $tooSmall = array_keys(array_filter(
        config('horizon.defaults'),
        fn (array $supervisor) => $supervisor['memory'] < 512
    ));

    expect($tooSmall)->toBeEmpty(
        'A booted supervisor alone takes about 380 MB and Horizon stops one over its memory limit, so these never start a worker'
    );
});
