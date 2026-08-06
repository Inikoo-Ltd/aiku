<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Iris\CaptureTrafficSource;
use Illuminate\Http\Request;

function irisAjaxRequest(string $referer, array $cookies = []): Request
{
    $request = Request::create(
        'https://ecom.test/json/first-hit',
        'GET',
        [],
        $cookies,
        [],
        ['HTTP_REFERER' => $referer]
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

it('captures nothing for a plain visit with no ad params and no external referrer', function () {
    irisAjaxRequest('https://ecom.test/products/candles');

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('does not record the same touch twice in a row', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        ['aiku_lts' => 'b99887766']
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
