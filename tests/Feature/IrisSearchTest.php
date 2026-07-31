<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Search\Search;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Support\Facades\Route;

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
