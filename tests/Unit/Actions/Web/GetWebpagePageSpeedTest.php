<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Webpage\GetWebpagePageSpeed;
use App\Actions\Web\Webpage\GetWebpagePageSpeedReport;
use App\Actions\Web\Webpage\RefreshWebpagePageSpeed;
use App\Actions\Web\Webpage\StoreWebpagePageSpeedTimeSeriesRecord;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;

function fakeWebpage(int $id = 1, ?string $canonicalUrl = 'https://www.example.com/shop/landing'): Webpage
{
    $webpage                = new Webpage(['url' => 'landing']);
    $webpage->id            = $id;
    $webpage->canonical_url = $canonicalUrl;
    $webpage->setRelation('website', new Website(['domain' => 'example.test']));
    $webpage->setRelation('shop', new Shop(['type' => ShopTypeEnum::B2C]));

    return $webpage;
}

function pageSpeedPayload(): array
{
    return [
        'analysisUTCTimestamp' => '2026-09-10T08:00:00.000Z',
        'loadingExperience'    => [
            'overall_category' => 'AVERAGE',
            'metrics'          => [
                'LARGEST_CONTENTFUL_PAINT_MS'   => [
                    'percentile'    => 2600,
                    'category'      => 'AVERAGE',
                    'distributions' => [
                        ['proportion' => 0.62],
                        ['proportion' => 0.25],
                        ['proportion' => 0.13],
                    ],
                ],
                'CUMULATIVE_LAYOUT_SHIFT_SCORE' => [
                    'percentile'    => 8,
                    'category'      => 'FAST',
                    'distributions' => [
                        ['proportion' => 0.91],
                        ['proportion' => 0.06],
                        ['proportion' => 0.03],
                    ],
                ],
            ],
        ],
        'lighthouseResult'     => [
            'finalUrl'   => 'https://example.test/landing',
            'categories' => [
                'performance'    => ['score' => 0.94],
                'accessibility'  => ['score' => 0.71],
                'best-practices' => ['score' => 0.42],
                'seo'            => ['score' => 1],
            ],
            'audits'     => [
                'largest-contentful-paint' => ['numericValue' => 2450.5, 'displayValue' => '2.5 s', 'score' => 0.62],
                'cumulative-layout-shift'  => ['numericValue' => 0.02, 'displayValue' => '0.02', 'score' => 1],
            ],
        ],
    ];
}

beforeEach(function () {
    cache()->flush();
});

it('maps the PageSpeed Insights response into scores, lab and field metrics', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response(pageSpeedPayload()),
    ]);

    StoreWebpagePageSpeedTimeSeriesRecord::shouldRun();

    $result = GetWebpagePageSpeed::run(fakeWebpage());

    expect($result['url'])->toBe('https://example.test/landing')
        ->and($result['strategy'])->toBe('desktop')
        ->and($result['fetched_at'])->toBe('2026-09-10T08:00:00.000Z')
        ->and($result['overall_rating'])->toBe('AVERAGE');

    expect($result['scores'])->toHaveCount(4)
        ->and($result['scores'][0])->toMatchArray([
            'key'    => 'performance',
            'label'  => 'Performance',
            'score'  => 94,
            'rating' => 'fast',
        ])
        ->and($result['scores'][1]['rating'])->toBe('average')
        ->and($result['scores'][2]['rating'])->toBe('slow')
        ->and($result['scores'][3]['score'])->toBe(100);

    expect($result['lab'])->toHaveCount(2)
        ->and($result['lab'][0])->toMatchArray([
            'key'     => 'largest-contentful-paint',
            'display' => '2.5 s',
            'rating'  => 'average',
        ]);

    expect($result['field'])->toHaveCount(2)
        ->and($result['field'][0])->toMatchArray([
            'key'           => 'LARGEST_CONTENTFUL_PAINT_MS',
            'percentile'    => 2600,
            'display'       => '2.6 s',
            'rating'        => 'average',
            'distributions' => [62, 25, 13],
        ])
        ->and($result['field'][1]['display'])->toBe('0.08');
});

it('requests the webpage url with the selected strategy, caches the result and records it once in the time series', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response(pageSpeedPayload()),
    ]);

    StoreWebpagePageSpeedTimeSeriesRecord::shouldRun()
        ->once()
        ->withArgs(fn (Webpage $webpage, array $result) => $webpage->id === 2 && $result['strategy'] === 'mobile');

    $webpage = fakeWebpage(2);

    GetWebpagePageSpeed::run($webpage, 'mobile');
    GetWebpagePageSpeed::run($webpage, 'mobile');

    Http::assertSentCount(1);

    Http::assertSent(function ($request) use ($webpage) {
        return $request['url'] === $webpage->canonical_url
            && $request['strategy'] === 'mobile'
            && str_contains($request->url(), 'category=performance&category=accessibility&category=best-practices&category=seo');
    });
});

it('analyses the live canonical url instead of the unreachable local url', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response(pageSpeedPayload()),
    ]);

    StoreWebpagePageSpeedTimeSeriesRecord::shouldRun();

    $webpage = fakeWebpage(3);

    expect($webpage->getUrl())->toBe('https://ecom.test/landing');

    GetWebpagePageSpeed::run($webpage);

    Http::assertSent(fn ($request) => $request['url'] === 'https://www.example.com/shop/landing');
});

it('reports why it could not measure a page with no public url', function () {
    Http::fake();

    StoreWebpagePageSpeedTimeSeriesRecord::shouldNotRun();

    $result = GetWebpagePageSpeed::run(fakeWebpage(4, null));

    expect($result)->toHaveKey('error');

    Http::assertNothingSent();
});

it('surfaces the PageSpeed Insights error message and does not cache or record it', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response([
            'error' => ['message' => 'Quota exceeded for quota metric Queries'],
        ], 429),
    ]);

    StoreWebpagePageSpeedTimeSeriesRecord::shouldNotRun();

    $webpage = fakeWebpage(5);

    expect(GetWebpagePageSpeed::run($webpage))
        ->toBe(['error' => 'Quota exceeded for quota metric Queries']);

    GetWebpagePageSpeed::run($webpage);

    Http::assertSentCount(2);
});

it('re-measures both strategies on the queue instead of inside the request', function () {
    Queue::fake();
    Http::fake();

    $webpage = fakeWebpage(6);

    cache()->put(GetWebpagePageSpeed::resultKey($webpage, 'desktop'), ['strategy' => 'desktop', 'scores' => []]);
    cache()->put(GetWebpagePageSpeed::pendingKey($webpage, 'mobile'), true);

    RefreshWebpagePageSpeed::run($webpage);

    Http::assertNothingSent();
    Queue::assertPushed(JobDecorator::class, 2);

    expect(cache()->has(GetWebpagePageSpeed::resultKey($webpage, 'desktop')))->toBeFalse();
});

it('queues both strategies and reports pending instead of measuring while the request is served', function () {
    Queue::fake();
    Http::fake();

    $report = GetWebpagePageSpeedReport::run(fakeWebpage(7));

    Http::assertNothingSent();

    expect($report['status'])->toBe('ready')
        ->and($report['pending'])->toBeTrue()
        ->and($report['refresh_route']['name'])->toBe('grp.models.webpage.pagespeed.refresh')
        ->and($report['desktop']['pending'])->toBeTrue()
        ->and($report['mobile']['pending'])->toBeTrue();
});

it('serves a measurement already in the cache without asking Google again', function () {
    Http::fake();

    StoreWebpagePageSpeedTimeSeriesRecord::shouldNotRun();

    $webpage = fakeWebpage(8);

    foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
        cache()->put(GetWebpagePageSpeed::resultKey($webpage, $strategy), ['strategy' => $strategy, 'scores' => []]);
    }

    expect(GetWebpagePageSpeedReport::run($webpage)['desktop']['strategy'])->toBe('desktop')
        ->and(GetWebpagePageSpeedReport::run($webpage)['pending'])->toBeFalse();

    Http::assertNothingSent();
});

it('repeats a remembered failure instead of measuring the page on every load', function () {
    Http::fake();

    StoreWebpagePageSpeedTimeSeriesRecord::shouldNotRun();

    $webpage = fakeWebpage(9);

    cache()->put(GetWebpagePageSpeed::errorKey($webpage, 'desktop'), 'Lighthouse returned error: ERRORED_DOCUMENT_REQUEST');
    cache()->put(GetWebpagePageSpeed::resultKey($webpage, 'mobile'), ['strategy' => 'mobile', 'scores' => []]);

    $report = GetWebpagePageSpeedReport::run($webpage);

    expect($report['status'])->toBe('ready')
        ->and($report['desktop']['error'])->toBe('Lighthouse returned error: ERRORED_DOCUMENT_REQUEST');

    Http::assertNothingSent();
});

it('says a page with no public url cannot be measured instead of calling Google', function () {
    Http::fake();

    $report = GetWebpagePageSpeedReport::run(fakeWebpage(10, null));

    expect($report['status'])->toBe('unavailable')
        ->and($report)->toHaveKey('message')
        ->and($report)->not->toHaveKey('desktop');

    Http::assertNothingSent();
});
