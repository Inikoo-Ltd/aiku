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
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\PlatformOutboundGuard;
use App\Actions\Dropshipping\Portfolio\MatchBulkPortfoliosToPlatform;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\RetrieveShopifyAssignedOrders;
use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolio;
use App\Actions\Dropshipping\Shopify\Product\MatchPortfolioToCurrentShopifyProduct;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyInventory;
use App\Helpers\PlatformResponseFormatter;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\Http;
use Tests\Support\ShopifyFake;
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
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

afterEach(function () {
    expect(ShopifyFake::$stray)->toBe([]);
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
        ->and($ids)->toBe(['gid://shopify/Product/1', 'gid://shopify/ProductVariant/2', 'gid://shopify/Product/2']);
});

function shopifyProductChannel($test, string $name): ShopifyUser
{
    $customer    = StoreCustomer::make()->action($test->shop, Customer::factory()->definition());
    $shopifyUser = StoreShopifyUser::make()->handle($customer, ['name' => $name]);
    $shopifyUser->update([
        'shopify_location_id'           => 'gid://shopify/Location/1001',
        'shopify_fulfilment_service_id' => 'gid://shopify/FulfillmentService/501',
    ]);

    return $shopifyUser->refresh();
}

function shopifyProductNode(string $productGid, string $variantGid, string $sku, string $price = '0.00', ?string $levelId = 'gid://shopify/InventoryLevel/1'): array
{
    return [
        'id'              => $productGid,
        'title'           => 'Listed Product',
        'handle'          => 'listed-product',
        'descriptionHtml' => '<p>d</p>',
        'productType'     => 'Type',
        'vendor'          => 'Vendor',
        'tags'            => [],
        'options'         => [],
        'variants'        => ['edges' => [['node' => [
            'id'                => $variantGid,
            'title'             => 'Default Title',
            'price'             => $price,
            'compareAtPrice'    => null,
            'sku'               => $sku,
            'barcode'           => null,
            'inventoryQuantity' => 7,
            'inventoryItem'     => ['id' => 'gid://shopify/InventoryItem/'.Str::afterLast($variantGid, '/'), 'inventoryLevel' => $levelId ? ['id' => $levelId] : null],
        ]]]],
        'images'          => ['edges' => [['node' => ['id' => 'gid://shopify/ProductImage/1', 'src' => 'https://cdn.shopify.com/x.jpg', 'altText' => null, 'width' => 1, 'height' => 1]]]],
        'collections'     => ['edges' => []],
        'metafields'      => ['edges' => []],
        'onlineStoreUrl'  => null,
        'createdAt'       => '2026-09-01T00:00:00Z',
        'updatedAt'       => '2026-09-01T00:00:00Z',
    ];
}

test('uploading a product creates it, creates one variant with the right stock and price and records the variant shopify kept', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-upload');
    $channel     = $shopifyUser->customerSalesChannel;
    $channel->update(['max_quantity_advertise' => 5, 'stock_threshold' => 0]);
    $this->product->update(['available_quantity' => 40]);

    $portfolio = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update(['customer_price' => 19.5, 'sku' => 'UP-1']);
    $portfolio->refresh();

    $created = shopifyProductNode('gid://shopify/Product/7100', 'gid://shopify/ProductVariant/8100', '');

    ShopifyFake::fake([
        'productCreate'               => ShopifyFake::graphql(['productCreate' => ['product' => $created, 'userErrors' => []]]),
        'ProductVariantsList'         => ShopifyFake::graphql(['productVariants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8100', 'title' => 'Default Title', 'price' => '0.00', 'updatedAt' => 'x', 'inventoryQuantity' => 0, 'product' => ['id' => 'gid://shopify/Product/7100', 'title' => 'Listed Product']]]]]]),
        'ProductVariantsCreate'       => ShopifyFake::graphql(['productVariantsBulkCreate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8101', 'title' => 'Default Title']], 'userErrors' => []]]),
        'getProduct'                  => ShopifyFake::graphql(['product' => shopifyProductNode('gid://shopify/Product/7100', 'gid://shopify/ProductVariant/8101', $portfolio->sku)]),
        'GET shop.json'               => ['shop' => ['id' => 1]],
        'getProductExistence'         => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7100', 'title' => 'Listed Product']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8101', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/1']]]]]]]]),
    ]);

    $portfolio = StoreNewProductToCurrentShopify::make()->handle($portfolio, []);
    $portfolio->refresh();

    $productVariables = ShopifyFake::calls('productCreate')[0]['variables'];
    $variantCalls     = ShopifyFake::calls('ProductVariantsCreate');
    expect($productVariables['product']['title'])->toBe($this->product->name)
        ->and($productVariables['product']['vendor'])->toBe($this->product->shop->name)
        ->and($variantCalls)->toHaveCount(1)
        ->and($variantCalls[0]['variables']['productId'])->toBe('gid://shopify/Product/7100')
        ->and($variantCalls[0]['variables']['variants'][0]['inventoryItem']['sku'])->toBe($portfolio->sku)
        ->and((float) $variantCalls[0]['variables']['variants'][0]['price'])->toBe(19.5)
        ->and($variantCalls[0]['variables']['variants'][0]['inventoryQuantities'])->toBe(['availableQuantity' => 5, 'locationId' => 'gid://shopify/Location/1001'])
        ->and($portfolio->platform_product_id)->toBe('gid://shopify/Product/7100')
        ->and($portfolio->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8101')
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->has_valid_platform_product_id)->toBeTrue()
        ->and($portfolio->data['shopify_product']['id'])->toBe('gid://shopify/Product/7100')
        ->and($portfolio->errors_response)->toBeNull();
});

test('a rejected product upload keeps the portfolio unpublished with a readable error and never creates a variant', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-rejected');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);

    ShopifyFake::fake([
        'productCreate' => ShopifyFake::graphql(['productCreate' => ['product' => null, 'userErrors' => [['field' => ['title'], 'message' => 'Title cannot be blank']]]]),
    ]);

    StoreNewProductToCurrentShopify::make()->handle($portfolio, []);
    $portfolio->refresh();

    expect(ShopifyFake::calls('ProductVariantsCreate'))->toBe([])
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->errors_response['message'])->toBe('Title cannot be blank');

    ShopifyFake::fake([
        'productCreate' => Http::response(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]]),
    ]);
    StoreNewProductToCurrentShopify::make()->handle($portfolio, []);
    expect(ShopifyFake::calls('productCreate'))->toHaveCount(3)
        ->and($portfolio->refresh()->errors_response['message'])->toBe('Throttled')
        ->and(PlatformResponseFormatter::make()->format($portfolio->errors_response['message'])['hint'])->toContain('limited how fast')
        ->and(PlatformResponseFormatter::make()->format('Exceeded 2 calls per second for api client. Reduce request rates to resume uninterrupted service.')['hint'])->toContain('limited how fast');
});

test('checking an unmatched portfolio stores the shopify matches in the shape the retina table expects', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-match');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'match-me']);

    ShopifyFake::fake([
        'GET shop.json'        => ['shop' => ['id' => 1]],
        'getProductsByVariant' => fn (array $variables) => ShopifyFake::graphql(['products' => ['edges' => [['node' => [
            'id'              => 'gid://shopify/Product/7200',
            'title'           => 'Already Listed',
            'handle'          => 'already-listed',
            'descriptionHtml' => '',
            'productType'     => '',
            'vendor'          => 'Vendor',
            'variants'        => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8200', 'title' => 'Default', 'price' => '9.00', 'sku' => 'match-me', 'barcode' => null, 'inventoryQuantity' => 1]]]],
            'images'          => ['edges' => [['node' => ['id' => 'gid://shopify/ProductImage/2', 'src' => 'https://cdn.shopify.com/listed.jpg']]]],
        ]]]]]),
    ]);

    $portfolio = CheckShopifyPortfolio::run($portfolio->refresh())->refresh();

    expect(ShopifyFake::calls('getProductsByVariant')[0]['variables']['query'])->toBe('match-me')
        ->and($portfolio->number_platform_possible_matches)->toBe(1)
        ->and($portfolio->platform_possible_matches['matches_labels'])->toBe(['Already Listed'])
        ->and($portfolio->platform_possible_matches['raw_data'][0]['id'])->toBe('gid://shopify/Product/7200')
        ->and($portfolio->platform_possible_matches['raw_data'][0]['name'])->toBe('Already Listed')
        ->and($portfolio->platform_possible_matches['raw_data'][0]['images'][0]['src'])->toBe('https://cdn.shopify.com/listed.jpg')
        ->and($portfolio->platform_status)->toBeFalse();
});

test('matching a portfolio to an existing shopify product keeps the price the merchant already set', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-existing');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update(['sku' => 'match-me', 'customer_price' => 12]);
    $portfolio->refresh();

    ShopifyFake::fake([
        'ProductVariantsList'           => ShopifyFake::graphql(['productVariants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8200', 'title' => 'Default', 'price' => '9.00', 'updatedAt' => 'x', 'inventoryQuantity' => 1, 'product' => ['id' => 'gid://shopify/Product/7200', 'title' => 'Already Listed']]]]]]),
        'ProductVariantsCreate'         => ShopifyFake::graphql(['productVariantsBulkCreate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8201', 'title' => 'Default Title']], 'userErrors' => []]]),
        'getProduct'                    => ShopifyFake::graphql(['product' => shopifyProductNode('gid://shopify/Product/7200', 'gid://shopify/ProductVariant/8201', 'match-me', '9.00')]),
        'GET shop.json'                 => ['shop' => ['id' => 1]],
        'getProductExistence'           => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7200', 'title' => 'Already Listed']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8201', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/2']]]]]]]]),
    ]);

    MatchPortfolioToCurrentShopifyProduct::run($portfolio, ['shopify_product_id' => 'gid://shopify/Product/7200']);
    $portfolio->refresh();

    $variant = ShopifyFake::calls('ProductVariantsCreate')[0]['variables']['variants'][0];
    expect($variant['price'])->toBe('9.00')
        ->and($variant['compareAtPrice'])->toBe('9.00')
        ->and($portfolio->platform_product_id)->toBe('gid://shopify/Product/7200')
        ->and($portfolio->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8201')
        ->and($portfolio->platform_status)->toBeTrue();
});

test('bulk matching links portfolios by sku to the active listings and skips ambiguous skus', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-bulk-match');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update(['sku' => 'BULK-1']);

    $variantEdge = fn (string $sku, string $productId, string $status) => ['node' => ['sku' => $sku, 'product' => ['id' => $productId, 'status' => $status]]];

    ShopifyFake::fake([
        'listProductVariants' => Http::sequence()
            ->push(ShopifyFake::graphql(['productVariants' => ['pageInfo' => ['hasNextPage' => true, 'endCursor' => 'c1'], 'edges' => [$variantEdge('bulk-1', 'gid://shopify/Product/7300', 'ACTIVE'), $variantEdge('draft-1', 'gid://shopify/Product/7301', 'DRAFT')]]]))
            ->push(ShopifyFake::graphql(['productVariants' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => [$variantEdge('dup', 'gid://shopify/Product/7302', 'ACTIVE'), $variantEdge('dup', 'gid://shopify/Product/7303', 'ACTIVE')]]])),
    ]);

    $result = MatchBulkPortfoliosToPlatform::run($channel);

    expect(ShopifyFake::calls('listProductVariants'))->toHaveCount(2)
        ->and(ShopifyFake::calls('listProductVariants')[1]['variables']['cursor'])->toBe('c1')
        ->and($result)->toBe(['matched' => 1, 'ignored' => 0]);
    MatchPortfolioToCurrentShopifyProduct::assertPushed(fn ($action, $parameters) => $parameters[0]->id === $portfolio->id && $parameters[1] === ['shopify_product_id' => 'gid://shopify/Product/7300']);
});

test('the stock push heals a stale variant id through the product, applies threshold and cap and records failures per line', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-stock');
    $channel     = $shopifyUser->customerSalesChannel;
    $channel->update(['max_quantity_advertise' => 10, 'stock_threshold' => 3]);

    $this->product->update(['available_quantity' => 25]);
    $secondProduct = \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->product->family, array_merge(\App\Models\Catalogue\Product::factory()->definition(), ['trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]], 'price' => 50]));
    $secondProduct->update(['available_quantity' => 2]);

    $fresh = StorePortfolio::make()->action($channel, $this->product, []);
    $fresh->update(['platform_product_id' => 'gid://shopify/Product/7400', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/8400', 'platform_status' => true]);
    $stale = StorePortfolio::make()->action($channel, $secondProduct, []);
    $stale->update(['platform_product_id' => 'gid://shopify/Product/7401', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/dead', 'platform_status' => true]);

    ShopifyFake::fake([
        'getNodes'             => fn (array $variables) => ShopifyFake::graphql(['nodes' => array_map(fn (string $id) => match ($id) {
            'gid://shopify/ProductVariant/8400' => ['__typename' => 'ProductVariant', 'id' => $id, 'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/8400']],
            'gid://shopify/Product/7400'        => ['__typename' => 'Product', 'id' => $id, 'variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8400', 'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/8400']]]]]],
            'gid://shopify/Product/7401'        => ['__typename' => 'Product', 'id' => $id, 'variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/8401']]]]]],
            default                             => null,
        }, $variables['ids'])]),
        'inventorySetQuantities' => ShopifyFake::graphql(['inventorySetQuantities' => ['userErrors' => [['field' => ['input', 'quantities', '1', 'inventoryItemId'], 'message' => 'The specified inventory item is not stocked at the location.']]]]),
    ]);

    BulkUpdateShopifyPortfolio::run($channel->id);

    $ids        = ShopifyFake::calls('getNodes')[0]['variables']['ids'];
    $quantities = ShopifyFake::calls('inventorySetQuantities')[0]['variables']['input']['quantities'];
    expect(array_is_list($ids))->toBeTrue()
        ->and($ids)->toContain('gid://shopify/ProductVariant/dead', 'gid://shopify/Product/7401')
        ->and($quantities)->toBe([
            ['inventoryItemId' => 'gid://shopify/InventoryItem/8400', 'locationId' => 'gid://shopify/Location/1001', 'quantity' => 10],
            ['inventoryItemId' => 'gid://shopify/InventoryItem/8401', 'locationId' => 'gid://shopify/Location/1001', 'quantity' => 0],
        ])
        ->and(ShopifyFake::calls('inventorySetQuantities')[0]['variables']['input']['ignoreCompareQuantity'])->toBeTrue()
        ->and($fresh->refresh()->last_stock_value)->toBe(10)
        ->and($fresh->stock_last_updated_at)->not->toBeNull()
        ->and($stale->refresh()->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8401')
        ->and($stale->stock_last_fail_updated_at)->not->toBeNull()
        ->and($stale->stock_last_updated_at)->toBeNull();

    ShopifyFake::fake([
        'getNodes'               => fn (array $variables) => ShopifyFake::graphql(['nodes' => array_map(fn (string $id) => str_contains($id, 'ProductVariant') ? ['__typename' => 'ProductVariant', 'id' => $id, 'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/'.Str::afterLast($id, '/')]] : null, $variables['ids'])]),
        'inventorySetQuantities' => Http::response(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]]),
    ]);
    BulkUpdateShopifyPortfolio::run($channel->id);
    expect($fresh->refresh()->stock_last_fail_updated_at)->not->toBeNull();
});

test('the six-hourly stock push only queues open channels that want stock updates and are live on shopify', function () {
    Queue::fake();
    $live = shopifyProductChannel($this, 'stock-live')->customerSalesChannel;
    $live->update(['stock_update' => true, 'platform_status' => true]);
    $paused = shopifyProductChannel($this, 'stock-paused')->customerSalesChannel;
    $paused->update(['stock_update' => false, 'platform_status' => true]);
    $broken = shopifyProductChannel($this, 'stock-broken')->customerSalesChannel;
    $broken->update(['stock_update' => true, 'platform_status' => false]);

    UpdateShopifyInventory::run();

    BulkUpdateShopifyPortfolio::assertPushed(fn ($action, $parameters) => $parameters[0] === $live->id);
    BulkUpdateShopifyPortfolio::assertNotPushed(fn ($action, $parameters) => in_array($parameters[0], [$paused->id, $broken->id]));
});

test('outbound shopify calls are blocked in tests unless every http request is faked', function () {
    expect(PlatformOutboundGuard::blocks('Shopify'))->toBeTrue();

    $shopifyUser = shopifyProductChannel($this, 'no-client');
    [$status, $message] = RetrieveShopifyAssignedOrders::run($shopifyUser);
    expect($status)->toBeFalse()->and($message)->toContain('Failed to initialize');

    Http::preventStrayRequests();
    expect(PlatformOutboundGuard::blocks('Shopify'))->toBeFalse();
});
