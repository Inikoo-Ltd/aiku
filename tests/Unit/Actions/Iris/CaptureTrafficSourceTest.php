<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Iris\CaptureTrafficSource;
use Illuminate\Http\Request;

function irisAjaxRequest(string $referer, array $cookies = [], ?string $originalReferer = null): Request
{
    $server = ['HTTP_REFERER' => $referer];

    if ($originalReferer !== null) {
        $server['HTTP_X_ORIGINAL_REFERER'] = $originalReferer;
    }

    $request = Request::create(
        'https://ecom.test/json/first-hit',
        'GET',
        [],
        $cookies,
        [],
        $server
    );

    app()->instance('request', $request);

    return $request;
}

it('captures a google ads click from the referring page url', function () {
    irisAjaxRequest('https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies)->toHaveKey('aiku_tsd');
    expect($cookies['aiku_tsd']['value'])->toEndWith('b99887766');
    expect($cookies['aiku_lts']['value'])->toBe('b99887766');
});

it('captures a meta ads click from the referring page url', function () {
    irisAjaxRequest('https://ecom.test/?fbclid=XYZ&utm_medium=paid&utm_campaign=120230926608450511');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_lts']['value'])->toBe('f120230926608450511');
});

it('still falls back to organic detection when the referrer is an external search engine', function () {
    irisAjaxRequest('https://www.google.com/search?q=candles');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_lts']['value'])->toBe('a');
});

it('captures an organic search visit from the forwarded document.referrer', function () {
    irisAjaxRequest('https://ecom.test/products/candles', [], 'https://www.google.com/');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_lts']['value'])->toBe('a');
});

it('captures an organic social visit from the forwarded document.referrer', function () {
    irisAjaxRequest('https://ecom.test/', [], 'https://www.instagram.com/');

    expect(CaptureTrafficSource::make()->getCookies()['aiku_lts']['value'])->toBe('e');
});

it('ignores a forwarded referrer that is the storefront itself', function () {
    irisAjaxRequest('https://ecom.test/products/candles', [], 'https://ecom.test/');

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('ignores an empty forwarded referrer from a direct visit', function () {
    irisAjaxRequest('https://ecom.test/', [], '');

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('prefers a paid click id over the forwarded organic referrer', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        [],
        'https://www.google.com/'
    );

    expect(CaptureTrafficSource::make()->getCookies()['aiku_lts']['value'])->toBe('b99887766');
});

it('captures nothing for a plain visit with no ad params and no external referrer', function () {
    irisAjaxRequest('https://ecom.test/products/candles');

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('does not record the same touch twice in a row', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        ['aiku_lts' => 'b99887766']
    );

    /* No touch is rewritten. The visit marker may still be set: the same visitor arriving again is a
       visit even when it adds nothing to their touch history. */
    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies)->not->toHaveKey('aiku_tsd')
        ->and($cookies)->not->toHaveKey('aiku_lts');
});

it('does not set the visit marker twice on the same day', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        ['aiku_lts' => 'b99887766', 'aiku_vcd' => now()->toDateString()]
    );

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('appends a new touch to an existing history', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        ['aiku_tsd' => '1700000000a', 'aiku_lts' => 'a']
    );

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_tsd']['value'])->toStartWith('1700000000a|');
    expect($cookies['aiku_tsd']['value'])->toEndWith('b99887766');
});
