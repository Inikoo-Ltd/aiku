<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\RestoreRefererFromCurrentUrl;
use Illuminate\Http\Request;

function refererAfterRestoringFromCurrentUrl(string $currentUrl): string
{
    $request = Request::create('https://www.aw-dropship.com/app/models/portfolio/1/store-new-shopify-product', 'POST', server: [
        'HTTP_REFERER'       => 'https://www.aw-dropship.com/',
        'HTTP_X_CURRENT_URL' => $currentUrl,
    ]);

    (new RestoreRefererFromCurrentUrl())->handle($request, fn () => response('ok'));

    return $request->headers->get('referer');
}

test('a trimmed Safari referer is restored to the page the customer is on', function () {
    expect(refererAfterRestoringFromCurrentUrl('https://www.aw-dropship.com/app/dropshipping/channels/77334/portfolios'))
        ->toBe('https://www.aw-dropship.com/app/dropshipping/channels/77334/portfolios');
});

test('a current url on another host never becomes the referer', function (string $currentUrl) {
    expect(refererAfterRestoringFromCurrentUrl($currentUrl))->toBe('https://www.aw-dropship.com/');
})->with([
    'https://evil.com/app',
    'https://www.aw-dropship.com.evil.com/app',
    'https://www.aw-dropship.com@evil.com/app',
    'http://www.aw-dropship.com/app',
    '',
]);

test('back() returns to the page the customer is on', function () {
    $request = Request::create('https://www.aw-dropship.com/app/models/portfolio/1', 'DELETE', server: [
        'HTTP_REFERER'       => 'https://www.aw-dropship.com/',
        'HTTP_X_CURRENT_URL' => 'https://www.aw-dropship.com/app/dropshipping/channels/77334/my-products',
    ]);
    app('url')->setRequest($request);

    $response = (new RestoreRefererFromCurrentUrl())->handle($request, fn () => back());

    expect($response->getTargetUrl())->toBe('https://www.aw-dropship.com/app/dropshipping/channels/77334/my-products');
});

test('the referer is restored behind a proxy that terminates https', function () {
    Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_PROTO);

    $request = Request::create('http://www.aw-dropship.com/app/models/portfolio/1', 'DELETE', server: [
        'REMOTE_ADDR'            => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_REFERER'           => 'https://www.aw-dropship.com/',
        'HTTP_X_CURRENT_URL'     => 'https://www.aw-dropship.com/app/dropshipping/channels/77334/my-products',
    ]);

    (new RestoreRefererFromCurrentUrl())->handle($request, fn () => response('ok'));

    Request::setTrustedProxies([], -1);

    expect($request->headers->get('referer'))->toBe('https://www.aw-dropship.com/app/dropshipping/channels/77334/my-products');
});
