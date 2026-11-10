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

test('a storefront 404 is not rendered on the server, so junk urls cannot hold a worker waiting for it', function () {
    Http::preventStrayRequests();
    request()->headers->set('X-Inertia', 'true');

    $response = app(\App\Exceptions\Handler::class)->renderErrorForLogOutWebpages(
        'iris',
        request(),
        new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(),
        response('', 404)
    );

    expect($response->getStatusCode())->toBe(404)
        ->and(config('inertia.ssr.enabled'))->toBeFalse();

    Http::assertNothingSent();
});

test('a page over 1 MB is sent without Expect: 100-Continue, which the bun SSR server answers with 400', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div>Family</div>']),
    ]);

    $response = app(ReportingSsrGateway::class)->dispatch([
        'url'       => '/catalogue/family/example',
        'component' => 'Catalogue/Family',
        'props'     => ['products' => str_repeat('x', 1_100_000)],
    ]);

    expect($response)->not->toBeNull();

    Http::assertSent(fn (Request $request) => !$request->hasHeader('Expect'));
});

test('a grp error page is not sent to the storefront SSR bundle', function () {
    Http::preventStrayRequests();
    app()->instance('env', 'production');
    $request = \Illuminate\Http\Request::create('https://app.'.config('app.domain').'/grp/assets/app-grp-old.js');
    app()->instance('request', $request);
    \Illuminate\Support\Facades\Request::clearResolvedInstance('request');

    app(\App\Exceptions\Handler::class)->render($request, new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException());

    expect(config('inertia.ssr.enabled'))->toBeFalse();
    Http::assertNothingSent();
});

test('a page drawn in the browser because SSR was down is never stored by Varnish', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:13714/render' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);
    $request = \Illuminate\Http\Request::create('/catalogue/family/example');
    app()->instance('request', $request);

    app(ReportingSsrGateway::class)->dispatch(['url' => '/catalogue/family/example', 'component' => 'Catalogue/Family']);

    $response = (new \App\Http\Middleware\PreventCachingSsrFallback())->handle(
        $request,
        fn () => response('<div id="app"></div>')->header('Cache-Control', 'public, s-maxage=300, max-age=0')
    );

    expect($response->headers->get('X-Aiku-Ssr-Fallback'))->toBe('1')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('s-maxage');
});

test('a server rendered page keeps its cache headers', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div>Family</div>']),
    ]);
    $request = \Illuminate\Http\Request::create('/catalogue/family/example');
    app()->instance('request', $request);

    app(ReportingSsrGateway::class)->dispatch(['url' => '/catalogue/family/example', 'component' => 'Catalogue/Family']);

    $response = (new \App\Http\Middleware\PreventCachingSsrFallback())->handle(
        $request,
        fn () => response('<div>Family</div>')->header('Cache-Control', 'public, s-maxage=300, max-age=0')
    );

    expect($response->headers->has('X-Aiku-Ssr-Fallback'))->toBeFalse()
        ->and($response->headers->get('Cache-Control'))->toContain('s-maxage=300');
});

test('a storefront page carries one title: the SSR one, or the fallback when SSR gives none', function () {
    $page = ['component' => 'Iris/Home', 'props' => [], 'url' => '/', 'version' => ''];
    $titleCount = function (array $ssrHead) use ($page): int {
        Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => $ssrHead, 'body' => '<div></div>'])]);
        app()->forgetInstance(\Inertia\Ssr\SsrState::class);

        return substr_count(view('app-iris', ['page' => $page])->render(), '<title');
    };

    expect($titleCount(['<title data-inertia>Shop</title>']))->toBe(1)
        ->and($titleCount([]))->toBe(1);

    Http::fake(['http://127.0.0.1:13714/render' => Http::response([], 400)]);
    app()->forgetInstance(\Inertia\Ssr\SsrState::class);
    expect(substr_count(view('app-iris', ['page' => $page])->render(), '<title'))->toBe(1);
});
