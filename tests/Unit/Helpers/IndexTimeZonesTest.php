<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Dec 2025 14:59:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Helpers\TimeZone\Json\IndexTimeZones;

it('returns a non-empty curated timezone list', function () {
    $list = IndexTimeZones::run();

    expect($list)->toBeArray()->not->toBeEmpty();

    // Each item has expected keys
    $first = $list[0];
    expect($first)
        ->toHaveKeys(['value', 'label', 'offset', 'offset_label']);
});

it('filters timezones by query (London)', function () {
    $list = IndexTimeZones::run('london');

    // Should contain Europe/London
    $hasLondon = collect($list)->contains(fn ($row) => $row['value'] === 'Europe/London');

    expect($hasLondon)->toBeTrue();
});

it('includes UTC with GMT offset label', function () {
    $list = IndexTimeZones::run('utc');

    $utc = collect($list)->firstWhere('value', 'UTC');

    expect($utc)->not->toBeNull()
        ->and($utc['offset_label'])->toStartWith('GMT');
});

it('includes Asia/Makassar (Bali, WITA)', function () {
    $list = IndexTimeZones::run();

    $hasMakassar = collect($list)->contains(fn ($row) => $row['value'] === 'Asia/Makassar');

    expect($hasMakassar)->toBeTrue();
});

it('includes Asia/Kathmandu (Nepal, GMT+05:45)', function () {
    $list = IndexTimeZones::run();

    $hasKathmandu = collect($list)->contains(fn ($row) => $row['value'] === 'Asia/Kathmandu');

    expect($hasKathmandu)->toBeTrue();
});

it('includes Europe/Bratislava (Slovakia)', function () {
    $list = IndexTimeZones::run();

    $hasBratislava = collect($list)->contains(fn ($row) => $row['value'] === 'Europe/Bratislava');

    expect($hasBratislava)->toBeTrue();
});
