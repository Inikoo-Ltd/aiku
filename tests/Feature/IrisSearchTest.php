<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\HumanResources\Employee;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Search\GetWebsiteSearchAnalytics;
use App\Actions\Search\Search;
use App\Actions\Search\SearchCatalogue;
use App\Actions\Search\SearchIrisInvoices;
use App\Actions\Search\SearchIrisOrders;
use App\Actions\Search\PurgeStaffWebsiteSearchLogs;
use App\Actions\Search\StoreWebsiteSearchLog;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Search\WebsiteSearchSourceEnum;
use App\Events\Web\WebsiteSearchStatsUpdated;
use Illuminate\Support\Facades\Event;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

test('changing a cached storefront setting breaks the cached layout props', function () {
    $this->website->update(['settings' => array_merge($this->website->settings, ['google_tag_id' => 'GTM-OLD'])]);

    // exactly one break: the unrelated settings write must not trigger it, the cached setting must
    \App\Actions\Web\Website\BreakWebsiteIrisCache::mock()->shouldReceive('handle')->once();

    $this->website->update(['settings' => array_merge($this->website->refresh()->settings, ['unrelated_flag' => true])]);
    $this->website->update(['settings' => array_merge($this->website->settings, ['google_tag_id' => 'GTM-NEW'])]);
});

test('storefront loads GTM from the Google tag gateway path when the website has one', function () {
    $this->website->update(['settings' => array_merge($this->website->settings, ['google_tag_id' => 'GTM-TEST123'])]);
    $homepage = fn () => $this->withoutVite()->followingRedirects()->get('http://'.$this->website->domain.'/')->assertOk();

    $homepage()->assertSee('"https://www.googletagmanager.com/gtm.js?id="', false);

    \App\Actions\Web\Website\UpdateWebsite::make()->action($this->website, ['google_tag_gateway_path' => '/gtg']);
    expect($this->website->refresh()->settings['google_tag_gateway_path'])->toBe('/gtg');

    $homepage()->assertSee('"/gtg/gtm.js?id="', false)
        ->assertDontSee('"https://www.googletagmanager.com/gtm.js?id="', false)
        ->assertSee("var gtmId = 'GTM-TEST123';", false)
        ->assertSee('window.dataLayer = window.gtmDataLayer = window.dataLayer || [];', false);

    DetectWebsiteFromDomain::mock()->shouldReceive('handle')->andReturn($this->website);
    $this->withoutVite()->followingRedirects()->get('http://'.$this->website->domain.'/app/login')->assertOk()
        ->assertSee('"/gtg/gtm.js?id="', false)
        ->assertDontSee('"https://www.googletagmanager.com/gtm.js?id="', false);

    expect(fn () => \App\Actions\Web\Website\UpdateWebsite::make()->action($this->website, ['google_tag_gateway_path' => '/gtg/"><script>']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('legacy search settings still use internal search', function () {
    $this->website->update(['settings' => array_merge($this->website->settings, ['iris_search_model' => 'luigi'])]);
    Http::preventStrayRequests();
    Search::shouldRun()->once()->andReturn(irisSearchResults());

    $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=candles')
        ->assertOk()
        ->assertJsonPath('results.products', []);
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

test('a product whose webpage goes live after it was indexed gets its search entry refreshed', function () {
    [, $product] = createProduct($this->shop);
    $product = $product->fresh();

    $product->update(['is_in_website' => !$product->is_in_website]);

    expect($product->searchIndexShouldBeUpdated())->toBeTrue();
});

test('order search hydrate never returns another customer\'s order', function () {
    $customer      = createCustomer($this->shop);
    $otherCustomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $order = StoreOrder::make()->action($customer, [
        'reference'        => 'GB550706',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    $order->update(['state' => OrderStateEnum::SUBMITTED]);

    $otherOrder = StoreOrder::make()->action($otherCustomer, [
        'reference'        => 'GB550707',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    $otherOrder->update(['state' => OrderStateEnum::SUBMITTED]);

    // a stale or poisoned index handing over the other customer's order id must be dropped
    $staleIndexDocuments = [
        ['id' => (string)$order->id],
        ['id' => (string)$otherOrder->id],
    ];
    $results = SearchIrisOrders::make()->hydrate($staleIndexDocuments, 'GB5507', $customer->id, $this->shop->id);
    expect(array_column($results, 'id'))->toBe([$order->id])
        ->and($results[0]['url'])->toBe('/app/orders/'.$order->slug);

    // the direct reference match is scoped to the customer too, even when it matches both references
    $results = SearchIrisOrders::make()->hydrate([], 'GB5507', $customer->id, $this->shop->id);
    expect(array_column($results, 'id'))->toBe([$order->id]);

    // orders still in basket state never surface
    $order->update(['state' => OrderStateEnum::CREATING]);
    expect(SearchIrisOrders::make()->hydrate([], 'GB5507', $customer->id, $this->shop->id))->toBe([]);
});

test('invoice search hydrate never returns another customer\'s invoice', function () {
    $customer      = createCustomer($this->shop);
    $otherCustomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'reference', 'INV-550801');
    $invoice = StoreInvoice::make()->action($customer, $invoiceData);

    $otherInvoiceData = Invoice::factory()->definition();
    data_set($otherInvoiceData, 'reference', 'INV-550802');
    $otherInvoice = StoreInvoice::make()->action($otherCustomer, $otherInvoiceData);

    $staleIndexDocuments = [
        ['id' => (string)$invoice->id],
        ['id' => (string)$otherInvoice->id],
    ];
    $results = SearchIrisInvoices::make()->hydrate($staleIndexDocuments, 'INV-5508', $customer->id, $this->shop->id);
    expect(array_column($results, 'id'))->toBe([$invoice->id])
        ->and($results[0]['url'])->toBe('/app/invoices/'.$invoice->slug);

    $results = SearchIrisInvoices::make()->hydrate([], 'INV-5508', $customer->id, $this->shop->id);
    expect(array_column($results, 'id'))->toBe([$invoice->id]);

    $invoice->update(['in_process' => true]);
    expect(SearchIrisInvoices::make()->hydrate([], 'INV-5508', $customer->id, $this->shop->id))->toBe([]);
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

test('discontinued family drops out of the storefront search', function () {
    [, $product] = createProduct($this->shop);
    $family      = $product->family;
    StoreProductCategoryWebpage::make()->action($family)->update(['state' => WebpageStateEnum::LIVE]);
    \App\Actions\Web\Webpage\Hydrators\HydrateIsInWebsite::run($family->refresh());
    expect((bool) $family->refresh()->is_in_website)->toBeTrue();

    Search::shouldRun()->andReturn([
        'scope'   => 'catalogue',
        'results' => [
            'products'           => [],
            'product_categories' => [['id' => $family->id, 'code' => $family->code, 'name' => $family->name, 'image' => null]],
            'collections'        => [],
        ],
    ]);

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$family->code);
    $response->assertOk();
    expect($response->json('results.product_categories'))->toHaveCount(1);

    $family->update(['state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::DISCONTINUED]);
    expect((bool) $family->refresh()->is_in_website)->toBeFalse();

    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$family->code.'&v=2');
    $response->assertOk();
    expect($response->json('results.product_categories'))->toBe([]);
});

test('a family that is the strongest hit becomes the best match, superseding products', function () {
    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    $productWebpage = $product->webpage ?: StoreProductWebpage::make()->action($product);
    $productWebpage->update(['state' => WebpageStateEnum::LIVE]);
    expect((bool) $product->refresh()->is_in_website)->toBeTrue();

    $family = $product->family;
    StoreProductCategoryWebpage::make()->action($family)->update(['state' => WebpageStateEnum::LIVE]);
    \App\Actions\Web\Webpage\Hydrators\HydrateIsInWebsite::run($family->refresh());
    expect((bool) $family->refresh()->is_in_website)->toBeTrue();

    $searchResults = fn (int $familyScore, int $productScore) => [
        'scope'   => 'catalogue',
        'results' => [
            'products'           => [['id' => $product->id, 'code' => $product->code, 'name' => $product->name, 'image' => null, 'score' => $productScore]],
            'product_categories' => [['id' => $family->id, 'code' => $family->code, 'name' => $family->name, 'image' => null, 'score' => $familyScore]],
            'collections'        => [],
        ],
    ];

    Search::shouldRun()->andReturn(
        $searchResults(1, 100),
        $searchResults(100, 1),
        $searchResults(1, 100),
    );

    // exact family code wins the spotlight even when a product outscores it (HELP-3002: jcg -> JCG family, not a JCGB product)
    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$family->code);
    $response->assertOk();
    expect($response->json('results.best_match.id'))->toBe($family->id)
        ->and($response->json('results.best_match.type'))->toBe('product_category');

    // a family outscoring every product also takes the spotlight on a non-exact query
    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=bath+bombs');
    $response->assertOk();
    expect($response->json('results.best_match.id'))->toBe($family->id);

    // otherwise there is no best_match and the top product keeps the spotlight
    $response = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q=bath+bombs+again');
    $response->assertOk();
    expect($response->json('results.best_match'))->toBeNull();
});

test('full page search leads with the products of a best-matching category', function () {
    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    $webpage = $product->webpage ?: StoreProductWebpage::make()->action($product);
    $webpage->update(['state' => WebpageStateEnum::LIVE]);
    expect((bool) $product->refresh()->is_in_website)->toBeTrue();

    $family = $product->family;

    $action = \App\Actions\Search\SearchIrisCataloguePage::make();
    $shop   = $this->shop;
    (function () use ($shop) {
        $this->shop = $shop;
    })->call($action);

    $familyHit = fn (int $score) => [
        'document'   => ['id' => (string) $family->id, 'code' => $family->code, 'name' => $family->name, 'type' => 'family'],
        'text_match' => $score,
    ];
    $productHits = [['document' => ['id' => '999'], 'text_match' => 50]];

    // exact family code pulls its products to the front even when a product hit outscores it
    expect($action->withBestMatchCategoryProducts($family->code, $familyHit(1), $productHits, [999]))
        ->toBe([$product->id, 999]);

    // a category outscoring every product hit leads too
    expect($action->withBestMatchCategoryProducts('gongs', $familyHit(100), $productHits, [999]))
        ->toBe([$product->id, 999]);

    // otherwise the direct hits keep their order
    expect($action->withBestMatchCategoryProducts('gongs', $familyHit(1), $productHits, [999]))
        ->toBe([999]);

    // no category hit at all leaves the ids untouched
    expect($action->withBestMatchCategoryProducts('gongs', null, $productHits, [999]))->toBe([999]);
});

test('a family created empty is out of the website until its first product arrives', function () {
    [, $product] = createProduct($this->shop);

    $familyData = \App\Models\Catalogue\ProductCategory::factory()->definition();
    data_set($familyData, 'type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'state', \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::IN_PROCESS->value);
    $family = \App\Actions\Catalogue\ProductCategory\StoreProductCategory::make()->action($product->department, $familyData);

    $webpage = StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['state' => WebpageStateEnum::LIVE]);
    \App\Actions\Web\Webpage\Hydrators\HydrateIsInWebsite::run($family->refresh());
    expect((bool) $family->refresh()->is_in_website)->toBeFalse();

    \App\Actions\Catalogue\Product\UpdateProductFamily::make()->action($product, ['family_id' => $family->id]);
    \App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts::run($family->refresh());

    expect($family->refresh()->state)->toBe(\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE)
        ->and((bool) $family->is_in_website)->toBeTrue();
});

test('staff customers never leave a search log and the purge removes the old ones', function () {
    config(['marketing.staff_email_domains' => ['staff.test']]);
    Cache::forget('marketing:staff_email_domains');

    $makeLog = fn (Customer $customer) => [
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'website_id'      => $this->website->id,
        'customer_id'     => $customer->id,
        'scope'           => 'catalogue',
        'query'           => 'candles',
        'results_count'   => 3,
    ];

    $newCustomer = fn (string $email) => StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), ['email' => $email])
    );
    $staffByDomain = $newCustomer('tester@staff.test');
    $staffByUser   = $newCustomer(User::query()->firstOrFail()->email);
    $shopper       = $newCustomer('shopper@example.com');

    expect($staffByDomain->is_staff)->toBeTrue()
        ->and($staffByUser->is_staff)->toBeTrue()
        ->and($shopper->is_staff)->toBeFalse()
        ->and(StoreWebsiteSearchLog::run($makeLog($staffByDomain)))->toBeNull()
        ->and(StoreWebsiteSearchLog::run($makeLog($staffByUser)))->toBeNull()
        ->and(StoreWebsiteSearchLog::run($makeLog($shopper)))->not->toBeNull();

    $legacy = WebsiteSearchLog::create($makeLog($staffByDomain));
    expect(PurgeStaffWebsiteSearchLogs::run())->toBe(1)
        ->and(WebsiteSearchLog::find($legacy->id))->toBeNull()
        ->and(WebsiteSearchLog::where('customer_id', $shopper->id)->count())->toBe(1);

    UpdateCustomer::make()->action($shopper, ['email' => 'now-staff@staff.test']);
    expect($shopper->fresh()->is_staff)->toBeTrue();

    $employee = StoreEmployee::make()->action($this->organisation, array_merge(
        Employee::factory()->make(['organisation_id' => $this->organisation->id])->toArray(),
        [
            'worker_number'   => 'W-staff',
            'alias'           => 'staff-alias',
            'type'            => EmployeeTypeEnum::EMPLOYEE,
            'employment_type' => EmploymentTypeEnum::FULL_TIME,
            'state'           => EmployeeStateEnum::WORKING,
        ]
    ));
    $linked = $newCustomer('private-mail@example.com');
    expect($linked->is_staff)->toBeFalse();
    UpdateCustomer::make()->action($linked, ['as_employee_id' => $employee->id]);
    expect($linked->fresh()->is_staff)->toBeTrue();
    UpdateCustomer::make()->action($linked, ['as_employee_id' => null]);
    expect($linked->fresh()->is_staff)->toBeFalse();
});

test('a pre-order product in the search results is flagged so its picture is not greyed out (HELP-4100)', function () {
    $originalSettings = $this->shop->settings;
    $this->shop->update(['settings' => array_merge($originalSettings ?? [], ['pre_orders' => ['enabled' => true]])]);

    try {
        [, $product] = createProduct($this->shop);
        $product->update(['is_for_sale' => true]);
        $product->updateQuietly(['is_back_order' => true, 'available_quantity' => 0]);
        $webpage = $product->webpage ?: StoreProductWebpage::make()->action($product);
        $webpage->update(['state' => WebpageStateEnum::LIVE]);

        Search::shouldRun()->andReturn(irisSearchResults([
            [
                'id'    => $product->id,
                'code'  => $product->code,
                'name'  => $product->name,
                'image' => null,
            ],
        ]));

        $products = $this->getJson('http://'.$this->website->domain.'/json/search/catalogue?q='.$product->code.'&v=preorder')
            ->assertOk()
            ->json('results.products');

        expect($products)->toHaveCount(1)
            ->and($products[0]['stock'])->toEqual(0)
            ->and($products[0]['is_pre_order'])->toBeTrue();
    } finally {
        $this->shop->update(['settings' => $originalSettings]);
    }
});

test('full page search category facets read every category picture in one query, with the same facets as one category at a time', function () {
    [, $seed] = createProduct($this->shop);

    $newFamilyWithPictureAndProduct = function () use ($seed) {
        $familyData = \App\Models\Catalogue\ProductCategory::factory()->definition();
        data_set($familyData, 'type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY->value);
        $family = \App\Actions\Catalogue\ProductCategory\StoreProductCategory::make()->action($seed->department, $familyData);

        $picture = \App\Models\Helpers\Media::create([
            'group_id'              => $this->organisation->group_id,
            'ulid'                  => (string) Str::ulid(),
            'uuid'                  => (string) Str::uuid(),
            'name'                  => 'family-picture',
            'file_name'             => 'family-picture.png',
            'mime_type'             => 'image/png',
            'disk'                  => 'local',
            'collection_name'       => 'photo',
            'size'                  => 4,
            'manipulations'         => [],
            'custom_properties'     => [],
            'generated_conversions' => [],
            'responsive_images'     => [],
        ]);
        \Illuminate\Support\Facades\DB::table('product_categories')->where('id', $family->id)->update(['image_id' => $picture->id]);

        $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action($seed->family, array_merge(
            \App\Models\Catalogue\Product::factory()->definition(),
            ['trade_units' => [['id' => $seed->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 100]
        ));
        \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['family_id' => $family->id]);

        return [$family->fresh(), $product->id];
    };

    $action  = \App\Actions\Search\SearchIrisCataloguePage::make();
    $shop    = $this->shop;
    $website = $this->website;
    (function () use ($shop, $website) {
        $this->shop    = $shop;
        $this->website = $website;
    })->call($action);

    $countedFamilyFacets = function (array $productIds) use ($action) {
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $families = (fn () => $this->categoryFacets($productIds))->call($action)['families'];
        $queries  = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        return [$families, $queries];
    };

    [$firstFamily, $firstProductId] = $newFamilyWithPictureAndProduct();
    [, $queriesForOneFamily]        = $countedFamilyFacets([$firstProductId]);

    $created = collect([[$firstFamily, $firstProductId], $newFamilyWithPictureAndProduct(), $newFamilyWithPictureAndProduct()]);
    [$families, $queriesForThreeFamilies] = $countedFamilyFacets($created->pluck(1)->all());

    $expected = $created->map(fn ($pair) => [
        'id'    => $pair[0]->id,
        'name'  => $pair[0]->name,
        'count' => 1,
        'url'   => null,
        'image' => $pair[0]->imageSources(200, 200),
    ])->sortBy('id')->values()->all();

    expect(collect($families)->sortBy('id')->values()->all())->toEqual($expected)
        ->and(collect($expected)->pluck('image')->filter()->count())->toBe(3)
        ->and($queriesForThreeFamilies)->toBe($queriesForOneFamily);
});

test('the search stats broadcast is queued, so the headline aggregate never runs inside a search request', function () {
    $event = new WebsiteSearchStatsUpdated($this->website);

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class)
        ->and($event)->not->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
});

test('full page search reuses the query-only part for a minute while the product list stays live', function () {
    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    ($product->webpage ?: StoreProductWebpage::make()->action($product))->update(['state' => WebpageStateEnum::LIVE]);
    expect((bool) $product->refresh()->is_in_website)->toBeTrue();

    $search = function (array $modelData) {
        $action  = \App\Actions\Search\SearchIrisCataloguePage::make();
        $shop    = $this->shop;
        $website = $this->website;
        (function () use ($shop, $website) {
            $this->shop    = $shop;
            $this->website = $website;
        })->call($action);

        return $action->handle($modelData);
    };

    $cacheKey = 'iris-search-page:'.$this->website->id.':'.md5('candles');
    Cache::forget($cacheKey);

    expect($search(['q' => 'candles'])['results']['products'])->toBe([])
        ->and(Cache::has($cacheKey))->toBeTrue();

    $facets = ['departments' => [], 'sub_departments' => [], 'families' => [], 'brands' => [], 'tags' => [], 'price' => ['min' => 1.0, 'max' => 2.0]];
    Cache::put($cacheKey, ['ids' => [$product->id], 'arm_counts' => ['keyword' => 1, 'vector' => 0], 'facets' => $facets, 'collections' => []], 60);

    $results = $search(['q' => 'candles', 'page' => 1])['results'];
    expect($results['facets'])->toBe($facets)
        ->and($results['total'])->toBe(1)
        ->and(collect($results['products'])->pluck('id')->all())->toBe([$product->id]);

    $product->update(['is_for_sale' => false]);
    expect($search(['q' => 'candles'])['results']['total'])->toBe(0);
});

test('catalogue queries of up to three characters are shared for 30 seconds, longer ones are not', function () {
    $options = ['shop_id' => $this->shop->id, 'is_in_website' => true];
    $key     = fn (string $query) => 'search:catalogue:'.md5(json_encode($options)).':'.mb_strtolower($query);
    Cache::forget($key('cnd'));
    Cache::forget($key('cndl'));

    Search::run('catalogue', 'cnd', $options);
    Search::run('catalogue', 'cndl', $options);

    expect(Cache::has($key('cnd')))->toBeTrue()
        ->and(Cache::has($key('cndl')))->toBeFalse();
});

test('ticket searches are never served from the shared short-query cache, their results depend on who is looking', function () {
    $user = createAdminGuest($this->organisation->group)->getUser();
    request()->setUserResolver(fn () => $user);

    $key = 'search:tickets:'.md5(json_encode([])).':abc';
    Cache::forget($key);

    Search::run('tickets', 'abc');

    expect(Cache::has($key))->toBeFalse();
});

function keepExploringOn(object $test): void
{
    \App\Actions\Web\Website\UpdateWebsite::make()->action($test->website->refresh(), ['search_keep_exploring' => true]);
    expect($test->website->refresh()->settings['search_keep_exploring'])->toBeTrue();
    Cache::flush();
}

function keepExploringUrl(object $test, array $params): string
{
    return 'http://'.$test->website->domain.'/json/search/keep-exploring?'.http_build_query($params);
}

function keepExploringSearchLog(object $test, string $query, string $sessionId, array $extra = []): void
{
    WebsiteSearchLog::create(array_merge([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $test->organisation->group_id,
        'organisation_id' => $test->organisation->id,
        'shop_id'         => $test->shop->id,
        'website_id'      => $test->website->id,
        'scope'           => 'catalogue_page',
        'query'           => $query,
        'session_id'      => $sessionId,
        'results_count'   => 4,
    ], $extra));
}

/**
 * @return array<int, int>
 */
function keepExploringCustomers(object $test, int $count = 6): array
{
    return collect(range(1, $count))->map(fn () => StoreCustomer::make()->action($test->shop, Customer::factory()->definition())->id)->all();
}

/**
 * @param array<int, int> $customerIds
 */
function keepExploringPopular(object $test, string $query, array $customerIds): void
{
    foreach ($customerIds as $customerId) {
        keepExploringSearchLog($test, $query, 'ke-popular-'.Str::random(12), ['customer_id' => $customerId]);
    }
}

function keepExploringWord(): string
{
    return fake()->unique()->lexify('??????');
}

test('keep exploring answers only when the website switch is on', function () {
    $this->website->update(['settings' => array_merge($this->website->refresh()->settings ?? [], ['search_keep_exploring' => false])]);

    $this->getJson(keepExploringUrl($this, ['q' => 'candles']))->assertNotFound();

    keepExploringOn($this);

    $this->getJson(keepExploringUrl($this, ['q' => 'candles']))->assertOk()
        ->assertJsonStructure(['did_you_mean', 'searches' => ['kind', 'items'], 'families', 'products']);
    $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'families' => [1, 2, 3, 4, 5, 6]]))->assertStatus(422);
    expect(Route::getRoutes()->getByName('iris.json.search.keep_exploring')->gatherMiddleware())->toContain('throttle:iris-search');
});

test('related searches need three different customers in the same sessions, else popular searches are offered', function () {
    keepExploringOn($this);
    $customerIds = keepExploringCustomers($this);

    [$seed, $related, $oneCustomer, $dropdownOnly, $zeroResults, $lonely, $visitorsOnlyPair, $popularWithVisitorsOnly] = array_map(fn () => keepExploringWord(), range(1, 8));
    $withDigits = keepExploringWord().' 2024';
    $withAt     = keepExploringWord().'@mail';

    foreach ([$related, $oneCustomer, $dropdownOnly, $zeroResults, $withDigits, $withAt, $visitorsOnlyPair] as $popularQuery) {
        keepExploringPopular($this, $popularQuery, $customerIds);
    }

    foreach (range(1, 10) as $visit) {
        keepExploringSearchLog($this, $popularWithVisitorsOnly, 'ke-visitor-'.Str::random(12));
    }

    foreach (array_slice($customerIds, 0, 3) as $customerId) {
        $session = 'ke-related-'.Str::random(12);
        keepExploringSearchLog($this, $seed, $session, ['scope' => 'catalogue']);
        foreach ([$related, $withDigits, $withAt, $popularWithVisitorsOnly] as $coOccurring) {
            keepExploringSearchLog($this, $coOccurring, $session, ['customer_id' => $customerId]);
        }
        keepExploringSearchLog($this, $dropdownOnly, $session, ['scope' => 'catalogue', 'customer_id' => $customerId]);
        keepExploringSearchLog($this, $zeroResults, $session, ['results_count' => 0, 'customer_id' => $customerId]);
    }

    foreach (range(1, 5) as $visit) {
        $session = 'ke-visitor-pair-'.Str::random(12);
        keepExploringSearchLog($this, $seed, $session);
        keepExploringSearchLog($this, $visitorsOnlyPair, $session);
    }

    foreach (range(1, 5) as $visit) {
        $session = 'ke-one-customer-'.Str::random(12);
        keepExploringSearchLog($this, $seed, $session, ['customer_id' => $customerIds[5]]);
        keepExploringSearchLog($this, $oneCustomer, $session, ['customer_id' => $customerIds[5]]);
    }

    $searches = $this->getJson(keepExploringUrl($this, ['q' => ' '.strtoupper($seed).' ']))->assertOk()->json('searches');
    expect($searches)->toBe(['kind' => 'related', 'items' => [$related]]);

    $searches = $this->getJson(keepExploringUrl($this, ['q' => $lonely]))->assertOk()->json('searches');
    expect($searches['kind'])->toBe('popular')
        ->and($searches['items'])->not->toBeEmpty()
        ->and($searches['items'])->not->toContain($lonely)
        ->and($searches['items'])->not->toContain($popularWithVisitorsOnly)
        ->and(collect($searches['items'])->filter(fn (string $item) => preg_match('/[0-9@]/', $item))->all())->toBe([]);
});

test('did you mean offers a popular spelling of a query, never for a popular query, and copes with any alphabet', function () {
    keepExploringOn($this);
    $popularSpelling = keepExploringWord().' '.keepExploringWord();
    keepExploringPopular($this, $popularSpelling, keepExploringCustomers($this, 5));

    $this->getJson(keepExploringUrl($this, ['q' => str_replace(' ', '', $popularSpelling)]))->assertOk()
        ->assertJsonPath('did_you_mean', $popularSpelling);

    $this->getJson(keepExploringUrl($this, ['q' => $popularSpelling]))->assertOk()
        ->assertJsonPath('did_you_mean', null);

    foreach (['свечи ароматические', 'crème brûlée', '香薰蜡烛'] as $query) {
        $this->getJson(keepExploringUrl($this, ['q' => $query]))->assertOk();
    }
});

test('keep exploring products are only live and in stock, a visitor gets no forgotten group, and nothing about other customers leaks', function () {
    keepExploringOn($this);
    keepExploringPopular($this, keepExploringWord(), keepExploringCustomers($this, 5));

    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    $webpage = $product->webpage ?: StoreProductWebpage::make()->action($product);
    $webpage->update(['state' => WebpageStateEnum::LIVE]);
    $product->refresh()->updateQuietly(['available_quantity' => 10, 'has_live_webpage' => true, 'first_available_at' => now()]);

    $groupedIds = function () {
        $response = $this->getJson(keepExploringUrl($this, ['q' => keepExploringWord()]))->assertOk();

        expect(preg_match('/"(segment_[^"]*|customers|revenue|customer_[^"]*)"\s*:/', $response->getContent()))->toBe(0)
            ->and($response->json('searches.items'))->not->toBeEmpty()
            ->and(collect($response->json('products'))->pluck('key')->all())->not->toContain('forgotten');

        return collect($response->json('products'))->mapWithKeys(fn (array $group) => [$group['key'] => collect($group['items'])->pluck('id')->all()])->all();
    };

    expect($groupedIds()['new_arrivals'] ?? [])->toContain($product->id);

    $product->updateQuietly(['available_quantity' => 0]);
    expect(collect($groupedIds())->flatten()->all())->not->toContain($product->id);

    $product->updateQuietly(['available_quantity' => 10]);
    $webpage->update(['state' => WebpageStateEnum::CLOSED]);
    expect(collect($groupedIds())->flatten()->all())->not->toContain($product->id);
});

test('keep exploring families are related families of this shop with something to buy, ids of another shop are ignored', function () {
    keepExploringOn($this);

    [, $product] = createProduct($this->shop);
    $product->update(['is_for_sale' => true]);
    ($product->webpage ?: StoreProductWebpage::make()->action($product))->update(['state' => WebpageStateEnum::LIVE]);
    $product->refresh()->updateQuietly(['available_quantity' => 10, 'has_live_webpage' => true]);

    $relatedFamily = $product->family;
    $relatedFamily->updateQuietly(['show_in_website' => true, 'state' => \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE]);
    ($relatedFamily->webpage ?: StoreProductCategoryWebpage::make()->action($relatedFamily))->update(['state' => WebpageStateEnum::LIVE]);

    $newCategory = function ($shop) {
        $data = \App\Models\Catalogue\ProductCategory::factory()->definition();
        data_set($data, 'type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::DEPARTMENT->value);

        return \App\Actions\Catalogue\ProductCategory\StoreProductCategory::make()->action($shop, $data);
    };

    $searchedFamily = $newCategory($this->shop);
    $otherShopCategory = $newCategory(createOwnShop('keep exploring other shop')[2]);

    foreach ([$searchedFamily, $otherShopCategory] as $category) {
        \Illuminate\Support\Facades\DB::table('product_category_has_related_product_categories')->insert([
            'product_category_id'         => $category->id,
            'related_product_category_id' => $relatedFamily->id,
            'position'                    => 1,
        ]);
    }

    $families = $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'families' => [$searchedFamily->id]]))->assertOk()->json('families');
    expect(collect($families)->pluck('id')->all())->toBe([$relatedFamily->id])
        ->and(array_keys($families[0]))->toBe(['id', 'name', 'url', 'image'])
        ->and($families[0]['url'])->not->toBeNull();

    $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'families' => [$otherShopCategory->id]]))->assertOk()
        ->assertJsonPath('families', []);
});

test('a keep exploring click is stored once apart from the result click and ends the search in that session', function () {
    Event::fake([WebsiteSearchStatsUpdated::class]);

    $log = StoreWebsiteSearchLog::run([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'website_id'      => $this->website->id,
        'session_id'      => 'ke-click-'.Str::random(12),
        'scope'           => 'catalogue_page',
        'query'           => 'candles',
        'results_count'   => 3,
    ]);
    Event::fake([WebsiteSearchStatsUpdated::class]);

    $click = fn (string $suggestion, string $url) => $this->postJson('http://'.$this->website->domain.'/json/search/click', [
        'ulid'       => $log->ulid,
        'url'        => $url,
        'suggestion' => $suggestion,
    ]);

    $click('related_search', '/search?q=candles+white')->assertOk();
    $click('family', '/candles/pillar')->assertOk();
    $click('anything', '/x')->assertStatus(422);

    $log->refresh();
    expect($log->suggestion_kind)->toBe('related_search')
        ->and($log->suggestion_url)->toBe('/search?q=candles+white')
        ->and($log->suggestion_clicked_at)->not->toBeNull()
        ->and($log->clicked_at)->toBeNull()
        ->and($log->clicked_url)->toBeNull();
    Event::assertNotDispatched(WebsiteSearchStatsUpdated::class);

    $next = StoreWebsiteSearchLog::run(array_merge($log->only(['group_id', 'organisation_id', 'shop_id', 'website_id', 'session_id', 'scope', 'results_count']), [
        'ulid'  => (string) Str::ulid(),
        'query' => 'candles white',
    ]));
    expect($next->id)->not->toBe($log->id)
        ->and($log->refresh()->query)->toBe('candles');
});

test('keep exploring best sellers say similar shops only when every one comes from the shop type, and a customer gets the products they stopped ordering', function () {
    keepExploringOn($this);

    [, $seed] = createProduct($this->shop);
    $liveProduct = function () use ($seed) {
        $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action($seed->family, array_merge(
            \App\Models\Catalogue\Product::factory()->definition(),
            ['trade_units' => [['id' => $seed->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 10]
        ));
        $product->update(['state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE, 'is_for_sale' => true]);
        ($product->webpage ?: StoreProductWebpage::make()->action($product))->update(['state' => WebpageStateEnum::LIVE]);
        $product->refresh()->updateQuietly(['has_live_webpage' => true, 'price' => 10, 'available_quantity' => 1000]);

        return $product->refresh();
    };
    [$shopBestSeller, $typeBestSeller, $stopped] = [$liveProduct(), $liveProduct(), $liveProduct()];
    $shopType = \App\Enums\CRM\Customer\CustomerShopTypeEnum::GIFT_SHOP->value;

    \Illuminate\Support\Facades\DB::table('segment_product_sales')->where('shop_id', $this->shop->id)->delete();
    $segmentRow = fn (\App\Models\Catalogue\Product $product, string $level, ?string $type) => [
        'shop_id'           => $this->shop->id,
        'level'             => $level,
        'shop_type'         => $type,
        'product_id'        => $product->id,
        'family_id'         => $product->family_id,
        'segment_customers' => 40,
        'customers'         => 12,
        'repeat_customers'  => 5,
        'revenue'           => 999.50,
    ];
    \Illuminate\Support\Facades\DB::table('segment_product_sales')->insert([
        $segmentRow($shopBestSeller, \App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales::LEVEL_SHOP, null),
        $segmentRow($typeBestSeller, \App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales::LEVEL_TYPE, $shopType),
    ]);

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $customer->updateQuietly(['shop_type' => $shopType]);
    \Illuminate\Support\Facades\DB::table('customer_product_purchases')->insert([
        'customer_id'      => $customer->id,
        'shop_id'          => $customer->shop_id,
        'product_id'       => $stopped->id,
        'family_id'        => $stopped->family_id,
        'orders'           => 3,
        'orders_12m'       => 0,
        'quantity_12m'     => 0,
        'spend_12m'        => 0,
        'first_ordered_at' => now()->subMonths(30),
        'last_ordered_at'  => now()->subMonths(14),
    ]);
    $webUser = \App\Actions\CRM\WebUser\StoreWebUser::make()->action($customer, [
        'username' => 'ke-'.Str::lower(Str::random(10)),
        'email'    => 'ke-'.Str::lower(Str::random(10)).'@example.com',
        'password' => 'test',
    ]);

    $groups = function () {
        $response = $this->getJson(keepExploringUrl($this, ['q' => keepExploringWord()]))->assertOk();
        expect(preg_match('/"(segment_[^"]*|customers|revenue|customer_[^"]*)"\s*:/', $response->getContent()))->toBe(0);

        return collect($response->json('products'))->keyBy('key')
            ->map(fn (array $group) => ['similar_shops' => $group['similar_shops'] ?? null, 'ids' => collect($group['items'])->pluck('id')->all()])
            ->all();
    };

    $visitor = $groups();
    expect($visitor['best_sellers'])->toBe(['similar_shops' => false, 'ids' => [$shopBestSeller->id]])
        ->and($visitor)->not->toHaveKey('forgotten');

    $this->actingAs($webUser, 'retina');

    $signedIn = $groups();
    expect($signedIn['best_sellers'])->toBe(['similar_shops' => false, 'ids' => [$typeBestSeller->id, $shopBestSeller->id]])
        ->and($signedIn['forgotten']['ids'])->toBe([$stopped->id]);

    \Illuminate\Support\Facades\DB::table('segment_product_sales')->where('shop_id', $this->shop->id)->where('level', \App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales::LEVEL_SHOP)->delete();

    expect($groups()['best_sellers'])->toBe(['similar_shops' => true, 'ids' => [$typeBestSeller->id]]);
});

function irisSearchClickLog(object $test, array $extra = []): WebsiteSearchLog
{
    return StoreWebsiteSearchLog::run(array_merge([
        'ulid'            => (string) Str::ulid(),
        'group_id'        => $test->organisation->group_id,
        'organisation_id' => $test->organisation->id,
        'shop_id'         => $test->shop->id,
        'website_id'      => $test->website->id,
        'session_id'      => 'click-'.Str::random(12),
        'scope'           => 'catalogue_page',
        'query'           => 'candles',
        'results_count'   => 3,
    ], $extra));
}

test('the search log stores whether the assistant was offered, a refinement in either order keeps it true, and the param stays out of every cache key', function () {
    $searchCalls = [];
    Search::shouldRun()->andReturnUsing(function (...$arguments) use (&$searchCalls) {
        $searchCalls[] = $arguments;

        return irisSearchResults();
    });

    $base    = 'http://'.$this->website->domain.'/json/search/catalogue';
    $offered = $this->getJson($base.'?q=candles&assistant=1')->assertOk()->json('search_log_ulid');
    $plain   = $this->getJson($base.'?q=lamps')->assertOk()->json('search_log_ulid');

    expect(WebsiteSearchLog::where('ulid', $offered)->value('assistant_available'))->toBeTrue()
        ->and(WebsiteSearchLog::where('ulid', $plain)->value('assistant_available'))->toBeFalse()
        ->and($searchCalls)->toHaveCount(2)
        ->and([$searchCalls[0][0], $searchCalls[0][1], $searchCalls[0][2]])->toEqual(['catalogue', 'candles', $searchCalls[1][2]]);

    foreach ([[true, false], [false, true]] as [$first, $second]) {
        $log     = irisSearchClickLog($this, ['query' => 'tea', 'scope' => 'catalogue', 'assistant_available' => $first]);
        $refined = irisSearchClickLog($this, ['query' => 'tealights', 'session_id' => $log->session_id, 'assistant_available' => $second]);
        expect($refined->id)->toBe($log->id)
            ->and($log->refresh()->assistant_available)->toBeTrue();
    }

    $action = \App\Actions\Search\SearchIrisCataloguePage::make();
    $shop   = $this->shop;
    $site   = $this->website;
    (function () use ($shop, $site) {
        $this->shop    = $shop;
        $this->website = $site;
    })->call($action);
    $cacheKey = 'iris-search-page:'.$this->website->id.':'.md5('assistant lamps');
    Cache::forget($cacheKey);
    $action->handle(['q' => 'assistant lamps', 'assistant' => true]);

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($action->rules())->toHaveKey('assistant');
});

test('keep exploring stores what it suggested on the search log of the same website once, never on another website\'s, and suggestions viewed is set once', function () {
    keepExploringOn($this);
    $ulid      = (string) Str::ulid();
    $otherUlid = (string) Str::ulid();
    keepExploringSearchLog($this, 'candles', 'ke-log-'.Str::random(12), ['ulid' => $ulid]);
    keepExploringSearchLog($this, 'candles', 'ke-log-'.Str::random(12), ['ulid' => $otherUlid, 'website_id' => $this->website->id + 100000]);

    $response = $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'search_log' => $ulid]))->assertOk();
    $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'search_log' => $otherUlid]))->assertOk();
    $this->getJson(keepExploringUrl($this, ['q' => 'candles', 'search_log' => 'short']))->assertStatus(422);

    $stored = WebsiteSearchLog::where('ulid', $ulid)->first()->suggestions;
    $groups = collect($response->json('products'))->keyBy('key');

    expect($stored)->toEqual([
        'did_you_mean' => $response->json('did_you_mean'),
        'searches'     => $response->json('searches'),
        'families'     => array_column($response->json('families'), 'id'),
        'products'     => [
            'best_sellers' => array_column($groups->get('best_sellers')['items'] ?? [], 'id'),
            'new_arrivals' => array_column($groups->get('new_arrivals')['items'] ?? [], 'id'),
            'forgotten'    => array_column($groups->get('forgotten')['items'] ?? [], 'id'),
        ],
    ])
        ->and(WebsiteSearchLog::where('ulid', $otherUlid)->value('suggestions'))->toBeNull();

    DB::table('website_search_logs')->where('ulid', $ulid)->update(['suggestions' => json_encode(['kept' => true])]);
    $this->getJson(keepExploringUrl($this, ['q' => 'lavender', 'search_log' => $ulid]))->assertOk();
    expect(WebsiteSearchLog::where('ulid', $ulid)->first()->suggestions)->toBe(['kept' => true]);

    $viewed = 'http://'.$this->website->domain.'/json/search/suggestions-viewed';
    $this->travelTo(now()->startOfSecond());
    $this->postJson($viewed, ['ulid' => $ulid])->assertOk()->assertExactJson(['ok' => true]);
    $firstSeen = WebsiteSearchLog::where('ulid', $ulid)->first()->suggestions_viewed_at;
    $this->travel(5)->minutes();
    $this->postJson($viewed, ['ulid' => $ulid])->assertOk()->assertExactJson(['ok' => true]);
    $this->postJson($viewed, ['ulid' => (string) Str::ulid()])->assertOk();
    $this->travelBack();

    expect($firstSeen)->not->toBeNull()
        ->and(WebsiteSearchLog::where('ulid', $ulid)->first()->suggestions_viewed_at->equalTo($firstSeen))->toBeTrue()
        ->and(Route::getRoutes()->getByName('iris.json.search.suggestions_viewed')->gatherMiddleware())->toContain('throttle:iris-search');
});

test('every search click is stored with its type, kind and target up to 20 a search, and a suggestion click on a button leaves the first suggestion click to a link', function () {
    $log   = irisSearchClickLog($this);
    Event::fake([WebsiteSearchStatsUpdated::class]);
    $click = fn (array $data) => $this->postJson('http://'.$this->website->domain.'/json/search/click', array_merge(['ulid' => $log->ulid], $data))->assertOk();

    $click(['url' => '/candles/pillar', 'suggestion' => 'family', 'target' => 'button']);
    expect($log->refresh()->suggestion_clicked_at)->toBeNull()
        ->and($log->suggestion_kind)->toBeNull();

    $click(['url' => '/lavender-soap', 'suggestion' => 'best_sellers', 'target' => 'link']);
    $click(['url' => '/new-candle', 'suggestion' => 'new_arrivals']);
    $click(['url' => '/some-product', 'target' => 'button']);
    $click(['url' => '/another-product']);
    $this->postJson('http://'.$this->website->domain.'/json/search/click', ['ulid' => $log->ulid, 'url' => '/x', 'target' => 'elsewhere'])->assertStatus(422);

    $log->refresh();
    $rows = DB::table('website_search_log_clicks')->where('website_search_log_id', $log->id)->orderBy('id')->get();

    expect($log->suggestion_kind)->toBe('best_sellers')
        ->and($log->suggestion_url)->toBe('/lavender-soap')
        ->and($log->clicked_url)->toBe('/some-product')
        ->and($log->clicked_at)->not->toBeNull()
        ->and($rows->map(fn ($row) => [$row->type, $row->kind, $row->target, $row->url, $row->website_id])->all())->toBe([
            ['suggestion', 'family', 'button', '/candles/pillar', $this->website->id],
            ['suggestion', 'best_sellers', 'link', '/lavender-soap', $this->website->id],
            ['suggestion', 'new_arrivals', 'link', '/new-candle', $this->website->id],
            ['result', null, 'button', '/some-product', $this->website->id],
            ['result', null, 'link', '/another-product', $this->website->id],
        ]);
    Event::assertDispatchedTimes(WebsiteSearchStatsUpdated::class, 1);

    foreach (range(1, 20) as $index) {
        $click(['url' => '/more-'.$index]);
    }

    expect(DB::table('website_search_log_clicks')->where('website_search_log_id', $log->id)->count())->toBe(20);
});
