<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\TrafficSource\SyncCustomerTrafficSourcesFromDevice;

it('passes credible touches through intact', function () {
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize('1700000000b22872123321|1700000100a'))
        ->toBe('1700000000b22872123321|1700000100a');
});

it('drops a forged epoch first touch', function () {
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize('1a|1700000100b'))
        ->toBe('1700000100b');
});

it('drops touches from the future', function () {
    $future = now()->addYear()->timestamp;

    expect(SyncCustomerTrafficSourcesFromDevice::sanitize("{$future}a|1700000100b"))
        ->toBe('1700000100b');
});

it('drops garbage and unknown abbreviations', function () {
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize('<script>|zzzz|1700000100z|1700000200a'))
        ->toBe('1700000200a');
});

it('returns null when nothing survives', function () {
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize('total|garbage'))->toBeNull();
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize(null))->toBeNull();
    expect(SyncCustomerTrafficSourcesFromDevice::sanitize(''))->toBeNull();
});
