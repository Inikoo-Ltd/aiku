<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;

it('returns an empty array for a blank value', function () {
    expect(ParseTrafficSourceTouches::run(null))->toBe([]);
    expect(ParseTrafficSourceTouches::run(''))->toBe([]);
});

it('parses a single touch without a campaign reference', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a');

    expect($touches)->toHaveCount(1);
    expect($touches[0]['type'])->toBe(TrafficSourcesTypeEnum::ORGANIC_GOOGLE);
    expect($touches[0]['timestamp'])->toBe(1700000000);
    expect($touches[0]['campaign_ref'])->toBeNull();
});

it('parses a single touch with a campaign reference', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000b123');

    expect($touches)->toHaveCount(1);
    expect($touches[0]['type'])->toBe(TrafficSourcesTypeEnum::GOOGLE_ADS);
    expect($touches[0]['campaign_ref'])->toBe('123');
});

it('parses multiple pipe separated touches preserving order', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000b123|1700000100e|1700000200f456');

    expect($touches)->toHaveCount(3);
    expect($touches[0]['type'])->toBe(TrafficSourcesTypeEnum::GOOGLE_ADS);
    expect($touches[0]['campaign_ref'])->toBe('123');
    expect($touches[1]['type'])->toBe(TrafficSourcesTypeEnum::ORGANIC_META);
    expect($touches[1]['campaign_ref'])->toBeNull();
    expect($touches[2]['type'])->toBe(TrafficSourcesTypeEnum::META_ADS);
    expect($touches[2]['campaign_ref'])->toBe('456');
});

it('supports the legacy comma separated format', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a,1700000100b');

    expect($touches)->toHaveCount(2);
});

it('ignores unknown abbreviations', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000z123');

    expect($touches)->toBe([]);
});

it('ignores blank segments', function () {
    $touches = ParseTrafficSourceTouches::run('1700000000a||1700000100b');

    expect($touches)->toHaveCount(2);
});
