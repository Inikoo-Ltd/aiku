<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Iris\CaptureTrafficSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

function captureWith(array $headers = [], string $url = 'https://ancientwisdom.biz/'): void
{
    $server = [];

    foreach ($headers as $key => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($key))] = $value;
    }

    app()->instance('request', Request::create($url, 'GET', [], [], [], $server));

    CaptureTrafficSource::make()->getCookies();
}

function captureCount(string $outcome, string $audience = 'anon'): int
{
    return (int) Cache::get('traffic_capture:'.now()->toDateString().':'.$audience.':'.$outcome, 0);
}

beforeEach(function () {
    Cache::flush();
});

it('counts a hit with no referrer at all as direct', function () {
    captureWith();

    expect(captureCount('direct'))->toBe(1)
        ->and(captureCount('unmatched'))->toBe(0);
});

it('counts a hit from an external site we do not recognise as a matched referral', function () {
    captureWith(['X-Original-Referer' => 'https://someforum.example/thread/12']);

    expect(captureCount('matched'))->toBe(1)
        ->and(captureCount('direct'))->toBe(0)
        ->and(captureCount('unmatched'))->toBe(0);
});

it('does not count our own admin app as a referral', function () {
    captureWith(['X-Original-Referer' => 'https://app.aiku.io/org/aw/shops/uk']);

    expect(captureCount('direct'))->toBe(1)
        ->and(captureCount('matched'))->toBe(0);
});

it('counts anonymous and logged in visitors separately', function () {
    captureWith();

    expect(captureCount('direct', 'anon'))->toBe(1)
        ->and(captureCount('direct', 'auth'))->toBe(0);
});

it('does not count the storefront referring itself as an unrecognised source', function () {
    captureWith(['X-Original-Referer' => 'https://ancientwisdom.biz/products']);

    expect(captureCount('direct'))->toBe(1)
        ->and(captureCount('unmatched'))->toBe(0);
});

it('counts an identified source as matched', function () {
    captureWith(['X-Original-Referer' => 'https://www.google.com/search?q=incense']);

    expect(captureCount('matched'))->toBe(1)
        ->and(captureCount('direct'))->toBe(0);
});
