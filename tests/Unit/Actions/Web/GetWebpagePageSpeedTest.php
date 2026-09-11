<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Webpage\GetWebpagePageSpeed;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Http;

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

    $result = GetWebpagePageSpeed::run(fakeWebpage());

    expect($result['url'])->toBe('https://example.test/landing')
        ->and($result['strategy'])->toBe('mobile')
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

it('requests the webpage url with the selected strategy and caches the result', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response(pageSpeedPayload()),
    ]);

    $webpage = fakeWebpage(2);

    GetWebpagePageSpeed::run($webpage, 'desktop');
    GetWebpagePageSpeed::run($webpage, 'desktop');

    Http::assertSentCount(1);

    Http::assertSent(function ($request) use ($webpage) {
        return $request['url'] === $webpage->canonical_url
            && $request['strategy'] === 'desktop'
            && str_contains($request->url(), 'category=performance&category=accessibility&category=best-practices&category=seo');
    });
});

it('analyses the live canonical url instead of the unreachable local url', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response(pageSpeedPayload()),
    ]);

    $webpage = fakeWebpage(3);

    expect($webpage->getUrl())->toBe('https://ecom.test/landing');

    GetWebpagePageSpeed::run($webpage);

    Http::assertSent(fn ($request) => $request['url'] === 'https://www.example.com/shop/landing');
});

it('reports why it could not measure a page with no public url', function () {
    Http::fake();

    $result = GetWebpagePageSpeed::run(fakeWebpage(4, null));

    expect($result)->toHaveKey('error');

    Http::assertNothingSent();
});

it('surfaces the PageSpeed Insights error message and does not cache it', function () {
    Http::fake([
        'www.googleapis.com/pagespeedonline/*' => Http::response([
            'error' => ['message' => 'Quota exceeded for quota metric Queries'],
        ], 429),
    ]);

    $webpage = fakeWebpage(5);

    expect(GetWebpagePageSpeed::run($webpage))
        ->toBe(['error' => 'Quota exceeded for quota metric Queries']);

    GetWebpagePageSpeed::run($webpage);

    Http::assertSentCount(2);
});
