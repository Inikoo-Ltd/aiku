<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 12:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Shopify\FulfilmentService\AdoptShopifyFulfilmentService;
use App\Actions\Dropshipping\Shopify\Product\BulkUpdateShopifyPortfolio;
use App\Actions\Dropshipping\Shopify\Product\CreateNewBulkPortfoliosToShopify;
use App\Actions\Dropshipping\Shopify\Product\StoreNewProductToCurrentShopify;
use App\Actions\Maintenance\Dropshipping\RepairShopifyChannelReconnects;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group = $this->organisation->group;
    $this->user = createAdminGuest($this->group)->getUser();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );

    actingAs($this->user);
});


test('create shopify channel', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();


    expect($this->customer->customerSalesChannels()->count())->toBe(0);
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        [
            'reference' => 'test_shopify_reference'
        ]
    );


    $customer = $customerSalesChannel->customer;
    expect($customer->customerSalesChannels()->first())->toBeInstanceOf(CustomerSalesChannel::class);


    return $customerSalesChannel;
});

test('channel platform user survives eager loading', function (CustomerSalesChannel $customerSalesChannel) {
    $customerSalesChannel->user()->associate($customerSalesChannel->customer)->save();

    $eager = CustomerSalesChannel::with('user')->find($customerSalesChannel->id);

    expect($eager->user?->id)->toBe($customerSalesChannel->customer->id);
})->depends('create shopify channel');

test('create customer client', function (CustomerSalesChannel $customerSalesChannel) {
    $customerClient = StoreCustomerClient::make()->action($customerSalesChannel, CustomerClient::factory()->definition());
    expect($customerClient)->toBeInstanceOf(CustomerClient::class);

    return $customerClient;
})->depends('create shopify channel');

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
    return $customerClient;
})->depends('create customer client');

test('add product to customer portfolio', function (CustomerClient $customerClient) {
    $dropshippingCustomerPortfolio = StorePortfolio::make()->action(
        $customerClient->salesChannel,
        $this->product,
        [
        ]
    );
    expect($dropshippingCustomerPortfolio)->toBeInstanceOf(Portfolio::class);

    return $dropshippingCustomerPortfolio;
})->depends('update customer client');

test('bulk portfolio upload dispatches one job per portfolio', function (Portfolio $portfolio) {
    Queue::fake();
    $portfolio->update(['status' => true, 'platform_status' => false]);

    CreateNewBulkPortfoliosToShopify::make()->handle(
        $portfolio->customerSalesChannel,
        ['portfolios' => [$portfolio->id]]
    );

    StoreNewProductToCurrentShopify::assertPushed();
})->depends('add product to customer portfolio');

test('reconnecting a shopify store reopens its closed channel with the portfolio instead of creating another', function () {
    $customer = createCustomer($this->shop);

    $firstLogin = StoreShopifyUser::make()->handle($customer, ['name' => 'reconnect-shop']);
    $channel    = $firstLogin->customerSalesChannel;
    $portfolio  = StorePortfolio::make()->action($channel, $this->product, []);

    CloseCustomerSalesChannel::make()->handle($channel);

    expect($channel->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($firstLogin->fresh()->trashed())->toBeTrue()
        ->and($portfolio->fresh()->status)->toBeFalse();

    $secondLogin = StoreShopifyUser::make()->handle($customer, ['name' => 'reconnect-shop']);
    $channel->refresh();

    expect($customer->customerSalesChannels()->where('reference', 'reconnect-shop')->count())->toBe(1)
        ->and($secondLogin->customer_sales_channel_id)->toBe($channel->id)
        ->and($channel->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($channel->closed_at)->toBeNull()
        ->and($channel->user?->id)->toBe($secondLogin->id)
        ->and($channel->portfolios()->count())->toBe(1)
        ->and($portfolio->fresh()->status)->toBeTrue();
});

test('repair merges a closed shopify channel into the open one that replaced it', function () {
    $customer = createCustomer($this->shop);
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();

    $old       = StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'merge-shop']);
    $portfolio = StorePortfolio::make()->action($old, $this->product, []);
    $client    = StoreCustomerClient::make()->action($old, CustomerClient::factory()->definition());
    CloseCustomerSalesChannel::make()->handle($old);

    $keep = StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'merge-shop']);

    $plan = RepairShopifyChannelReconnects::run($keep, true);
    expect($plan)->toBe(['portfolios' => 1, 'clients' => 1, 'orders' => 0, 'predecessors' => 1])
        ->and($portfolio->fresh()->customer_sales_channel_id)->toBe($old->id);

    RepairShopifyChannelReconnects::run($keep);

    expect($portfolio->fresh()->customer_sales_channel_id)->toBe($keep->id)
        ->and($portfolio->fresh()->status)->toBeTrue()
        ->and($client->fresh()->customer_sales_channel_id)->toBe($keep->id)
        ->and($keep->fresh()->number_portfolios)->toBe(1)
        ->and($old->fresh()->number_portfolios)->toBe(0);
});

test('adopting a location keeps the oldest aiku fulfilment service and drops the one the channel currently uses', function () {
    $services = [
        ['id' => 'gid://shopify/FulfillmentService/manual', 'serviceName' => 'Manual', 'location' => ['id' => 'L0', 'createdAt' => '2024-08-15T15:28:45Z']],
        ['id' => 'gid://shopify/FulfillmentService/new', 'serviceName' => 'aiku-dse (sho-x-1)', 'location' => ['id' => 'L2', 'createdAt' => '2026-08-30T21:49:51Z']],
        ['id' => 'gid://shopify/FulfillmentService/old', 'serviceName' => 'aiku-dse (sho-x)', 'location' => ['id' => 'L1', 'createdAt' => '2025-09-23T12:36:26Z']],
    ];

    $picked = AdoptShopifyFulfilmentService::pickServices($services, 'gid://shopify/FulfillmentService/new');
    expect($picked['adopt']['id'])->toBe('gid://shopify/FulfillmentService/old')
        ->and($picked['drop']['id'])->toBe('gid://shopify/FulfillmentService/new');

    $picked = AdoptShopifyFulfilmentService::pickServices($services, null);
    expect($picked['adopt']['id'])->toBe('gid://shopify/FulfillmentService/old')
        ->and($picked['drop'])->toBeNull();

    $picked = AdoptShopifyFulfilmentService::pickServices([$services[0], $services[1]], 'gid://shopify/FulfillmentService/new');
    expect($picked['adopt'])->toBeNull();
});

test('the stock push sends shopify ids as a list even when the portfolios are keyed by id', function () {
    $portfolios = collect([
        new Portfolio(['id' => 12, 'platform_product_id' => 'gid://shopify/Product/1']),
        new Portfolio(['id' => 34, 'platform_product_id' => 'gid://shopify/Product/2', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/2']),
        new Portfolio(['id' => 56, 'platform_product_id' => 'gid://shopify/Product/1']),
        new Portfolio(['id' => 78, 'platform_product_id' => null]),
    ])->keyBy('id');

    $ids = BulkUpdateShopifyPortfolio::shopifyIdsToFetch($portfolios);

    expect(array_is_list($ids))->toBeTrue()
        ->and($ids)->toBe(['gid://shopify/Product/1', 'gid://shopify/ProductVariant/2']);
});
