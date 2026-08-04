<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Search\GetWebsiteSearchAnalytics;
use App\Actions\Search\Search;
use App\Actions\Search\SearchCatalogue;
use App\Actions\Search\StoreWebsiteSearchLog;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Search\WebsiteSearchSourceEnum;
use App\Events\Web\WebsiteSearchStatsUpdated;
use Illuminate\Support\Facades\Event;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Helpers\WebsiteSearchLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);
    $this->website->update(['status' => true]);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);
});

function irisSearchResults(array $products = []): array
{
    return [
        'scope'   => 'catalogue',
        'results' => [
            'products'           => $products,
            'product_categories' => [],
            'collections'        => [],
        ],
    ];
}

test('iris search routes are rate limited', function () {
    foreach (['iris.json.search.catalogue', 'iris.json.search.catalogue_page'] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);
        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('throttle:iris-search');
    }
});

test('iris search rejects overlong queries', function () {
    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.str_repeat('a', 101));
    $response->assertStatus(422);
});

test('iris search allows single character queries', function () {
    Search::shouldRun()->andReturn(irisSearchResults());

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=e');
    $response->assertOk();
});

test('a new search broadcasts the headline stats, a refinement does not', function () {
    Event::fake([WebsiteSearchStatsUpdated::class]);

    $log = fn (array $extra = []) => StoreWebsiteSearchLog::run(array_merge([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'website_id'      => $this->website->id,
        'session_id'      => 'live-session',
        'scope'           => 'catalogue',
        'query'           => 'tealights',
        'results_count'   => 3,
    ], $extra));

    $log();
    Event::assertDispatchedTimes(WebsiteSearchStatsUpdated::class, 1);

    // keystroke refinement reuses the row, so the totals cannot have moved
    $log(['query' => 'tealights blue']);
    Event::assertDispatchedTimes(WebsiteSearchStatsUpdated::class, 1);

    // a different search is a new row and a new number on screen
    $log(['session_id' => 'other-session', 'query' => 'incense']);
    Event::assertDispatchedTimes(WebsiteSearchStatsUpdated::class, 2);
});

test('the search log records which control opened the search, and only known ones', function () {
    Search::shouldRun()->andReturn(irisSearchResults());

    $logFor = function (string $url) {
        $response = $this->getJson($url);
        $response->assertOk();

        return WebsiteSearchLog::where('ulid', $response->json('search_log_ulid'))->first();
    };

    $base = 'http://'.$this->website->domain.'/json/search/catalogue';

    expect($logFor($base.'?q=candles&source=mobile_floating_button')->source)
        ->toBe(WebsiteSearchSourceEnum::MOBILE_FLOATING_BUTTON->value);

    // anything the enum does not know about is dropped rather than stored, so a crafted
    // request cannot invent entry points in the breakdown
    expect($logFor($base.'?q=tealights&source=<script>alert(1)</script>')->source)->toBeNull()
        ->and($logFor($base.'?q=incense')->source)->toBeNull();
});

test('iris search stores a website search log with the response ulid', function () {
    Search::shouldRun()->andReturn(irisSearchResults());

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=candles');
    $response->assertOk();

    $ulid = $response->json('search_log_ulid');
    expect($ulid)->toBeString();

    $log = WebsiteSearchLog::where('ulid', $ulid)->first();
    expect($log)->not->toBeNull()
        ->and($log->website_id)->toBe($this->website->id)
        ->and($log->shop_id)->toBe($this->shop->id)
        ->and($log->scope)->toBe('catalogue')
        ->and($log->query)->toBe('candles')
        ->and($log->results_count)->toBe(0)
        ->and($log->web_user_id)->toBeNull()
        ->and($log->customer_id)->toBeNull();

    $makeLog = fn (array $extra = []) => StoreWebsiteSearchLog::run(array_merge([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'website_id'      => $this->website->id,
        'session_id'      => 'dedupe-session',
        'scope'           => 'catalogue',
        'query'           => 'tealights',
        'results_count'   => 3,
    ], $extra));

    $first  = $makeLog();
    $second = $makeLog(['scope' => 'catalogue_page', 'results_count' => 7]);
    expect($second->id)->toBe($first->id)
        ->and($second->scope)->toBe('catalogue_page')
        ->and(WebsiteSearchLog::where('session_id', 'dedupe-session')->count())->toBe(1);

    $customer = createCustomer($this->shop);
    $webUser  = createWebUser($customer);

    $response = $this->actingAs($webUser, 'retina')
        ->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=lavender');
    $response->assertOk();

    $log = WebsiteSearchLog::where('ulid', $response->json('search_log_ulid'))->first();
    expect($log)->not->toBeNull()
        ->and($log->web_user_id)->toBe($webUser->id)
        ->and($log->customer_id)->toBe($customer->id);
});

test('iris search click endpoint records the click once', function () {
    $log = StoreWebsiteSearchLog::run([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'website_id'      => $this->website->id,
        'scope'           => 'catalogue',
        'query'           => 'candles',
        'results_count'   => 3,
    ]);

    $response = $this->postJson('http://'.$this->website->domain.'/json/search/click', [
        'ulid' => $log->ulid,
        'url'  => 'https://'.$this->website->domain.'/some-product',
    ]);
    $response->assertOk();

    $log->refresh();
    expect($log->clicked_at)->not->toBeNull()
        ->and($log->clicked_url)->toContain('/some-product');

    $firstClickedAt = $log->clicked_at;
    $this->postJson('http://'.$this->website->domain.'/json/search/click', [
        'ulid' => $log->ulid,
        'url'  => 'https://'.$this->website->domain.'/another-product',
    ])->assertOk();

    $log->refresh();
    expect($log->clicked_url)->toContain('/some-product')
        ->and($log->clicked_at->equalTo($firstClickedAt))->toBeTrue();
});

test('changing the search engine breaks the cached storefront layout props', function () {
    // the dump ships websites already on internal, so pin a known starting point first
    $this->website->update(['settings' => array_merge($this->website->settings, ['iris_search_model' => 'luigi'])]);

    // exactly one break: the unrelated settings write must not trigger it, the engine flip must
    \App\Actions\Web\Website\BreakWebsiteIrisCache::mock()->shouldReceive('handle')->once();

    $this->website->update(['settings' => array_merge($this->website->refresh()->settings, ['unrelated_flag' => true])]);
    $this->website->update(['settings' => array_merge($this->website->settings, ['iris_search_model' => 'internal'])]);
});

test('luigi actions skip websites on internal search', function () {
    $this->website->update(['settings' => array_merge($this->website->settings, ['iris_search_model' => 'luigi'])]);
    expect($this->website->refresh()->usesLuigiSearch())->toBeTrue();

    $this->website->update(['settings' => array_merge($this->website->settings, ['iris_search_model' => 'internal'])]);
    expect($this->website->refresh()->usesLuigiSearch())->toBeFalse();

    $webpage = \App\Models\Web\Webpage::where('website_id', $this->website->id)->first();
    $result  = \App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData::run($webpage->id);
    expect($result['status'])->toBe('skipped');
});

test('iris search only returns hits flagged is_in_website', function () {
    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    $webpage = $product->webpage ?: StoreProductWebpage::make()->action($product);

    Search::shouldRun()->andReturn(irisSearchResults([
        [
            'id'    => $product->id,
            'code'  => $product->code,
            'name'  => $product->name,
            'image' => null,
        ],
    ]));

    $webpage->update(['state' => WebpageStateEnum::CLOSED]);
    expect((bool) $product->refresh()->is_in_website)->toBeFalse();

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code);
    $response->assertOk();
    expect($response->json('results.products'))->toBe([]);

    $webpage->update(['state' => WebpageStateEnum::LIVE]);
    expect((bool) $product->refresh()->is_in_website)->toBeTrue();

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code.'&v=2');
    $response->assertOk();

    $products = $response->json('results.products');
    expect($products)->toHaveCount(1)
        ->and($products[0]['id'])->toBe($product->id)
        ->and($products[0]['url'])->not->toBeNull();

    $product->update(['is_for_sale' => false]);
    expect((bool) $product->refresh()->is_in_website)->toBeFalse();

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code.'&v=3');
    $response->assertOk();
    expect($response->json('results.products'))->toBe([]);
});

test('a query only the vector arm answers is still logged as an assortment gap', function () {
    config()->set('scout.driver', 'typesense');

    // shungite: nothing in the catalogue matches the word, hybrid answers with
    // related tumble stones. The customer sees 2 products, the buyers must still
    // see a query nothing actually matched.
    Http::fake([
        '*/multi_search' => Http::response(['results' => [
            ['hits' => [
                ['document' => ['id' => '1', 'code' => 'TS-1', 'name' => 'Tumble Stone'], 'vector_distance' => 0.14],
                ['document' => ['id' => '2', 'code' => 'TS-2', 'name' => 'Tumble Stone L'], 'vector_distance' => 0.15],
            ]],
            ['hits' => []],
            ['hits' => []],
        ]]),
    ]);

    $results = SearchCatalogue::run('shungite', ['shop_id' => $this->shop->id, 'is_in_website' => true]);

    expect($results['arm_counts'])->toBe(['keyword' => 0, 'vector' => 2])
        ->and($results['results']['products'])->toHaveCount(2);

    $log = StoreWebsiteSearchLog::run([
        'ulid'                  => (string) Str::ulid(),
        'group_id'              => $this->organisation->group_id,
        'organisation_id'       => $this->organisation->id,
        'shop_id'               => $this->shop->id,
        'website_id'            => $this->website->id,
        'scope'                 => 'catalogue',
        'query'                 => 'shungite',
        'results_count'         => 2,
        'keyword_results_count' => $results['arm_counts']['keyword'],
        'vector_results_count'  => $results['arm_counts']['vector'],
    ]);

    expect($log->results_count)->toBe(2)
        ->and($log->keyword_results_count)->toBe(0)
        ->and($log->vector_results_count)->toBe(2);

    $gaps = GetWebsiteSearchAnalytics::run($this->website);
    expect($gaps['top_zero_queries']->pluck('query')->all())->toContain('shungite');
});

test('a keyword hit is not counted as a vector rescue', function () {
    config()->set('scout.driver', 'typesense');

    Http::fake([
        '*/multi_search' => Http::response(['results' => [
            ['hits' => [['document' => ['id' => '1', 'code' => 'C-1', 'name' => 'Candle']]]],
            ['hits' => []],
            ['hits' => []],
        ]]),
    ]);

    $results = SearchCatalogue::run('candles', ['shop_id' => $this->shop->id]);

    expect($results['arm_counts'])->toBe(['keyword' => 1, 'vector' => 0]);
});

test('the typo tuning reaches every search sent to typesense', function () {
    config()->set('scout.driver', 'typesense');

    Http::fake([
        '*/multi_search' => Http::response(['results' => [
            ['hits' => []], ['hits' => []], ['hits' => []],
        ]]),
    ]);

    SearchCatalogue::run('aromcandles', ['shop_id' => $this->shop->id, 'is_in_website' => true]);

    Http::assertSent(function ($request) {
        expect($request['searches'])->not->toBeEmpty();

        foreach ($request['searches'] as $search) {
            expect($search)
                ->toMatchArray(SearchCatalogue::SEARCH_TUNING)
                ->and($search['min_len_2typo'])->toBe(7);
        }

        return true;
    });
});
