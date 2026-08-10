<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;

function sumShares(array $shares): float
{
    return round(array_sum(array_column($shares, 'share')), 2);
}

it('returns an empty array when there are no touches', function () {
    expect(ProcessTrafficSourceShare::run([]))->toBe([]);
});

it('gives full credit to the first touch under the first touch model', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b|1700000200f');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);

    expect($shares)->toHaveCount(1);
    expect($shares[0]['type'])->toBe(TrafficSourcesTypeEnum::ORGANIC_GOOGLE);
    expect($shares[0]['share'])->toBe(1.0);
    expect($shares[0]['is_first_touch'])->toBeTrue();
});

it('gives full credit to the last touch under the last touch model', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b|1700000200f');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LAST_TOUCH);

    expect($shares)->toHaveCount(1);
    expect($shares[0]['type'])->toBe(TrafficSourcesTypeEnum::META_ADS);
    expect($shares[0]['share'])->toBe(1.0);
    expect($shares[0]['is_first_touch'])->toBeFalse();
});

it('marks the single touch as both first and last touch', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LAST_TOUCH);

    expect($shares[0]['is_first_touch'])->toBeTrue();
});

it('splits credit evenly across unique touches under the linear model', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b|1700000200f');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($shares)->toHaveCount(3);
    expect(sumShares($shares))->toBe(1.0);
    expect($shares[0]['is_first_touch'])->toBeTrue();
});

it('deduplicates repeated touches of the same source and campaign under the linear model', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000b123|1700000100b123|1700000200f');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($shares)->toHaveCount(2);
    expect(sumShares($shares))->toBe(1.0);
});

it('treats the same source with different campaigns as distinct touches', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000b123|1700000100b456');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($shares)->toHaveCount(2);
    expect(sumShares($shares))->toBe(1.0);
});

it('always sums shares to exactly 1.0 regardless of the number of unique touches', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b|1700000200f|1700000300e|1700000400c|1700000500d|1700000600i');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($shares)->toHaveCount(7);
    expect(sumShares($shares))->toBe(1.0);
});

it('defaults to the linear attribution model when none is given', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b');

    $shares = ProcessTrafficSourceShare::run($touches);

    expect($shares)->toHaveCount(2);
    expect(sumShares($shares))->toBe(1.0);
});

it('gives full credit to the last paid touch under the last paid touch model', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100b|1700000200e|1700000300f');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LAST_PAID_TOUCH);

    expect($shares)->toHaveCount(1);
    expect($shares[0]['type'])->toBe(TrafficSourcesTypeEnum::META_ADS);
    expect($shares[0]['share'])->toBe(1.0);
});

it('returns no credit under the last paid touch model when the journey has no paid touch', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a|1700000100e');

    $shares = ProcessTrafficSourceShare::run($touches, ProcessTrafficSourceShare::ATTRIBUTION_LAST_PAID_TOUCH);

    expect($shares)->toBe([]);
});
