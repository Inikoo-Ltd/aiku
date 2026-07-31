<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Search\Search;
use App\Actions\Search\StoreWebsiteSearchLog;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Helpers\WebsiteSearchLog;
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
        ->and($log->results_count)->toBe(0);
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

test('iris search only returns hits with a live webpage on the website', function () {
    [, $product] = createProduct($this->shop);

    Search::shouldRun()->andReturn(irisSearchResults([
        [
            'id'    => $product->id,
            'code'  => $product->code,
            'name'  => $product->name,
            'image' => null,
        ],
    ]));

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code);
    $response->assertOk();
    expect($response->json('results.products'))->toBe([]);

    $webpage = StoreProductWebpage::make()->action($product);
    $webpage->update(['state' => WebpageStateEnum::LIVE]);

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code);
    $response->assertOk();

    $products = $response->json('results.products');
    expect($products)->toHaveCount(1)
        ->and($products[0]['id'])->toBe($product->id)
        ->and($products[0]['url'])->not->toBeNull();
});
