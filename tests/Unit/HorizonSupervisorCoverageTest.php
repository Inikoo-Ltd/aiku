<?php

it('overrides every supervisor in every horizon environment', function (string $environment) {
    $supervisors = array_keys(config('horizon.defaults'));
    $overridden  = array_keys(config("horizon.environments.$environment"));

    expect(array_diff($supervisors, $overridden))->toBeEmpty(
        "Supervisors missing from the $environment block inherit the defaults, which are sized for production"
    );
    expect(array_diff($overridden, $supervisors))->toBeEmpty(
        "The $environment block names supervisors that do not exist in the defaults"
    );
})->with(['production', 'staging', 'local']);
