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

    /* A visit is only counted, and therefore only marked as counted, when the shop is known - the
       iris middleware puts the website on the request in production. */
    $request->attributes->set('website', (object) ['shop_id' => 1, 'type' => null]);

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

it('captures an instagram ads click as its own channel', function () {
    irisAjaxRequest('https://ecom.test/?fbclid=XYZ&utm_source=ig&utm_medium=paid&utm_campaign=120230926608450511');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_lts']['value'])->toBe('uig-120230926608450511');
});

it('keeps the other meta placements under meta ads', function () {
    irisAjaxRequest('https://ecom.test/?fbclid=XYZ&utm_source=an&utm_medium=paid&utm_campaign=120230926608450511');

    expect(CaptureTrafficSource::make()->getCookies()['aiku_lts']['value'])->toBe('f120230926608450511');
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

it('does not count the same channel twice on the same day', function () {
    irisAjaxRequest(
        'https://ecom.test/?gad_source=1&gad_campaignid=99887766&gclid=ABC123',
        ['aiku_lts' => 'b99887766', 'aiku_vcd' => now()->toDateString().'|b']
    );

    expect(CaptureTrafficSource::make()->getCookies())->toBe([]);
});

it('counts a second channel on the same day, rather than halving either', function () {
    /* Arrived from Google this morning, clicked a newsletter this afternoon: one visit each, because
       both channels did send them. The attributed columns are where credit gets shared. */
    irisAjaxRequest('https://ecom.test/', ['aiku_vcd' => now()->toDateString().'|b']);
    request()->headers->set('X-Original-Referer', 'https://www.google.com/search?q=incense');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies)->toHaveKey('aiku_vcd')
        ->and($cookies['aiku_vcd']['value'])->toBe(now()->toDateString().'|ba');
});

it('forgets yesterday\'s marker rather than carrying it forward', function () {
    irisAjaxRequest('https://ecom.test/', ['aiku_vcd' => now()->subDay()->toDateString().'|a']);
    request()->headers->set('X-Original-Referer', 'https://www.google.com/search?q=incense');

    $cookies = CaptureTrafficSource::make()->getCookies();

    expect($cookies['aiku_vcd']['value'])->toBe(now()->toDateString().'|a');
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
