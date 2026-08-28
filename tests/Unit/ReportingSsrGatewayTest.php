<?php

use App\Services\ReportingSsrGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('inertia.ssr.enabled', true);
    config()->set('inertia.ssr.ensure_bundle_exists', false);
    config()->set('inertia.ssr.url', 'http://127.0.0.1:13714');
});

test('falls back to client rendering when the SSR server rejects a page', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([], 400),
    ]);

    $response = app(ReportingSsrGateway::class)->dispatch([
        'url'       => '/catalogue/family/example',
        'component' => 'Catalogue/Family',
    ]);

    expect($response)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:13714/render'
        && $request->data()['url'] === '/catalogue/family/example');
});

test('returns the SSR response when rendering succeeds', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => ['<title>Family</title>'],
            'body' => '<div>Family</div>',
        ]),
    ]);

    $response = app(ReportingSsrGateway::class)->dispatch([
        'url'       => '/catalogue/family/example',
        'component' => 'Catalogue/Family',
    ]);

    expect($response)->not->toBeNull()
        ->and($response->head)->toBe('<title>Family</title>')
        ->and($response->body)->toBe('<div>Family</div>');
});
