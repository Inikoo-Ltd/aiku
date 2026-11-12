<?php

use App\Actions\Web\Webpage\BreakWebpageCache;

test('spreads its retries so a few seconds without varnish ports does not fail the job', function () {
    $job = BreakWebpageCache::makeJob(null);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([5, 15, 30, 60]);
});

test('address hydrators are dropped when the address was deleted before they ran', function (string $hydrator) {
    expect($hydrator::makeJob(new \App\Models\Helpers\Address())->deleteWhenMissingModels)->toBeTrue();
})->with([
    \App\Actions\Helpers\Address\Hydrators\AddressHydrateFixedUsage::class,
    \App\Actions\Helpers\Address\Hydrators\AddressHydrateMultiplicity::class,
    \App\Actions\Helpers\Address\Hydrators\AddressHydrateUsage::class,
]);
