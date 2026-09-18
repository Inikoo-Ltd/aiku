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
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetShopifyProducts;
use App\Actions\Dropshipping\Shopify\FulfilmentService\AdoptShopifyFulfilmentService;
use App\Actions\Dropshipping\Shopify\FulfilmentService\AddShopifyLocationToDeliveryProfiles;
use App\Actions\Dropshipping\Shopify\Product\BulkUpdateShopifyPortfolio;
use App\Actions\Dropshipping\Shopify\Product\StoreShopifyLocationToProductVariant;
use App\Actions\Dropshipping\Shopify\Product\CreateNewBulkPortfoliosToShopify;
use App\Actions\Dropshipping\Shopify\Product\StoreNewProductToCurrentShopify;
use App\Actions\Maintenance\Dropshipping\RepairShopifyChannelReconnects;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\PlatformOutboundGuard;
use App\Actions\Dropshipping\Portfolio\MatchBulkPortfoliosToPlatform;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\RetrieveShopifyAssignedOrders;
use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolio;
use App\Actions\Dropshipping\Shopify\Product\DeactivateShopifyProduct;
use App\Actions\Dropshipping\Shopify\Product\StoreShopifyProductVariant;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProduct;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProductDimensions;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProductVariant;
use App\Actions\Dropshipping\Shopify\SetShopifyChannelLinksExistingVariants;
use App\Actions\Dropshipping\Shopify\WithShopifyPortfolioMatching;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Actions\Dropshipping\Shopify\Product\MatchPortfolioToCurrentShopifyProduct;
use App\Actions\Dropshipping\Shopify\Product\RepairShopifyPortfolioConnections;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyInventory;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use App\Helpers\PlatformResponseFormatter;
use Lorisleiva\Actions\Decorators\JobDecorator;
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
use Illuminate\Support\Arr;
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

test('the stock push sends product ids as a list even when the portfolios are keyed by id', function () {
    $portfolios = collect([
        new Portfolio(['id' => 12, 'platform_product_id' => 'gid://shopify/Product/1']),
        new Portfolio(['id' => 34, 'platform_product_id' => 'gid://shopify/Product/2', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/2']),
        new Portfolio(['id' => 56, 'platform_product_id' => 'gid://shopify/Product/1']),
        new Portfolio(['id' => 78, 'platform_product_id' => null]),
    ])->keyBy('id');

    $ids = BulkUpdateShopifyPortfolio::shopifyIdsToFetch($portfolios);

    expect(array_is_list($ids))->toBeTrue()
        ->and($ids)->toBe(['gid://shopify/Product/1', 'gid://shopify/Product/2']);
});

test('the stock push resolves the variant by sku and never falls back to a sibling variant', function () {
    $variant   = fn (string $id, string $sku) => ['variantId' => "gid://shopify/ProductVariant/$id", 'inventoryItemId' => "gid://shopify/InventoryItem/$id", 'sku' => $sku];
    $bracelets = [$variant('1', 'BFGx-01'), $variant('3', 'BFGx-03')];
    $product   = fn (string $code) => new \App\Models\Catalogue\Product(['code' => $code]);

    $rewrittenOntoSibling = new Portfolio(['sku' => 'bfgx-03', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/1']);
    expect(BulkUpdateShopifyPortfolio::resolveVariant($rewrittenOntoSibling, $product('BFGx-03'), $bracelets)['variantId'])->toBe('gid://shopify/ProductVariant/3');

    $deletedVariant = new Portfolio(['sku' => 'spbic-12', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/10']);
    expect(BulkUpdateShopifyPortfolio::resolveVariant($deletedVariant, $product('SPBiC-12'), [$variant('10', 'spbic-10')]))->toBeNull();

    $merchantWithoutSkus = new Portfolio(['sku' => 'gel-08', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/dead']);
    expect(BulkUpdateShopifyPortfolio::resolveVariant($merchantWithoutSkus, $product('GEL-08'), [$variant('8', '')])['variantId'])->toBe('gid://shopify/ProductVariant/8')
        ->and(BulkUpdateShopifyPortfolio::resolveVariant($merchantWithoutSkus, $product('GEL-08'), [$variant('8', ''), $variant('9', '')]))->toBeNull()
        ->and(BulkUpdateShopifyPortfolio::resolveVariant($merchantWithoutSkus, $product('GEL-08'), []))->toBeNull();

    $storedUnlabelledAmongMany = new Portfolio(['sku' => 'gel-08', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/9']);
    expect(BulkUpdateShopifyPortfolio::resolveVariant($storedUnlabelledAmongMany, $product('GEL-08'), [$variant('8', ''), $variant('9', '')])['variantId'])->toBe('gid://shopify/ProductVariant/9');
});

test('a new fulfilment location joins the shipping profiles the previous aiku location was in, or the default one', function () {
    $shopifyUser = shopifyProductChannel($this, 'shipping-profile');
    $channel     = $shopifyUser->customerSalesChannel;

    $group = fn (string $groupId, array $locations) => ['locationGroup' => ['id' => "gid://shopify/DeliveryLocationGroup/$groupId", 'locations' => ['nodes' => array_map(fn (array $location) => ['id' => 'gid://shopify/Location/'.$location[0], 'name' => $location[1]], $locations)]]];
    $profiles = [
        ['id' => 'gid://shopify/DeliveryProfile/1', 'name' => 'General', 'default' => true, 'profileLocationGroups' => [$group('g1', [['5', 'Shop'], ['1000', 'aiku-dse (sho-old)']])]],
        ['id' => 'gid://shopify/DeliveryProfile/2', 'name' => 'Local pickup', 'default' => false, 'profileLocationGroups' => [$group('g2', [['5', 'Shop']])]],
    ];

    expect(AddShopifyLocationToDeliveryProfiles::groupsToJoin($profiles, 'gid://shopify/Location/1001'))->toBe([['profileId' => 'gid://shopify/DeliveryProfile/1', 'groupId' => 'gid://shopify/DeliveryLocationGroup/g1']])
        ->and(AddShopifyLocationToDeliveryProfiles::groupsToJoin($profiles, 'gid://shopify/Location/1000'))->toBe([])
        ->and(AddShopifyLocationToDeliveryProfiles::groupsToJoin([$profiles[1], ['id' => 'gid://shopify/DeliveryProfile/3', 'default' => true, 'profileLocationGroups' => [$group('g3', [['5', 'Shop']])]]], 'gid://shopify/Location/1001'))->toBe([['profileId' => 'gid://shopify/DeliveryProfile/3', 'groupId' => 'gid://shopify/DeliveryLocationGroup/g3']]);

    ShopifyFake::fake([
        'getDeliveryProfiles'   => ShopifyFake::graphql(['deliveryProfiles' => ['nodes' => $profiles]]),
        'deliveryProfileUpdate' => ShopifyFake::graphql(['deliveryProfileUpdate' => ['userErrors' => []]]),
    ]);

    [$status, $message] = AddShopifyLocationToDeliveryProfiles::run($channel);
    $update = ShopifyFake::calls('deliveryProfileUpdate');

    expect($status)->toBeTrue()
        ->and($message)->toBe('Joined 1 shipping profile group(s)')
        ->and($update)->toHaveCount(1)
        ->and($update[0]['variables'])->toBe(['id' => 'gid://shopify/DeliveryProfile/1', 'profile' => ['locationGroupsToUpdate' => [['id' => 'gid://shopify/DeliveryLocationGroup/g1', 'locationsToAdd' => ['gid://shopify/Location/1001']]]]]);

    ShopifyFake::fake([
        'getDeliveryProfiles' => ShopifyFake::graphql([], [['message' => 'Access denied for deliveryProfiles field. Required access: `read_shipping` access scope.', 'extensions' => ['code' => 'ACCESS_DENIED']]]),
    ]);
    [$status, $message] = AddShopifyLocationToDeliveryProfiles::run($channel);
    expect($status)->toBeFalse()->and($message)->toContain('read_shipping');
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

function shopifyVariantLinkingChannel($test, string $name): ShopifyUser
{
    $shopifyUser = shopifyProductChannel($test, $name);
    SetShopifyChannelLinksExistingVariants::run($shopifyUser->customerSalesChannel, true);

    return $shopifyUser->refresh();
}

function shopifyProductWithSiblingVariant(string $productGid, string $variantGid, string $sku): array
{
    $product = shopifyProductNode($productGid, $variantGid, $sku, '19.00');
    $sibling = shopifyProductNode($productGid, 'gid://shopify/ProductVariant/8401', 'first-sibling', '5.00')['variants']['edges'][0];

    $product['variants']['edges'] = [$sibling, $product['variants']['edges'][0]];

    return $product;
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

function shopifyLoggedStatuses(): array
{
    return collect(Queue::pushedJobs())
        ->flatten(1)
        ->pluck('job')
        ->filter(fn ($job) => $job instanceof JobDecorator && $job->getAction() instanceof UpdatePlatformPortfolioLog)
        ->map(fn ($job) => Arr::get($job->getParameters(), '1.status'))
        ->values()
        ->all();
}

test('an upload records a portfolio log that ends ok when shopify accepts the product and fail when it rejects it', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-logged');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'LOG-1']);

    ShopifyFake::fake([
        'productCreate' => ShopifyFake::graphql(['productCreate' => ['product' => null, 'userErrors' => [['field' => ['title'], 'message' => 'Title cannot be blank']]]]),
    ]);

    StoreNewProductToCurrentShopify::make()->handle($portfolio, []);

    $log = PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->latest('id')->firstOrFail();

    expect($log->type)->toBe(PlatformPortfolioLogsTypeEnum::UPLOAD)
        ->and($log->platform_id)->toBe($portfolio->platform_id)
        ->and(shopifyLoggedStatuses())->toBe([PlatformPortfolioLogsStatusEnum::FAIL]);

    $created = shopifyProductNode('gid://shopify/Product/7300', 'gid://shopify/ProductVariant/8300', $portfolio->sku);

    ShopifyFake::fake([
        'productCreate'                 => ShopifyFake::graphql(['productCreate' => ['product' => $created, 'userErrors' => []]]),
        'ProductVariantsList'           => ShopifyFake::graphql(['productVariants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8300', 'title' => 'Default Title', 'price' => '0.00', 'updatedAt' => 'x', 'inventoryQuantity' => 0, 'product' => ['id' => 'gid://shopify/Product/7300', 'title' => 'Listed Product']]]]]]),
        'ProductVariantsCreate'         => ShopifyFake::graphql(['productVariantsBulkCreate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8301', 'title' => 'Default Title']], 'userErrors' => []]]),
        'getProduct'                    => ShopifyFake::graphql(['product' => shopifyProductNode('gid://shopify/Product/7300', 'gid://shopify/ProductVariant/8301', $portfolio->sku)]),
        'GET shop.json'                 => ['shop' => ['id' => 1]],
        'getProductExistence'           => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7300', 'title' => 'Listed Product']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8301', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/1']]]]]]]]),
    ]);

    StoreNewProductToCurrentShopify::make()->handle($portfolio, []);

    expect(PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->count())->toBe(2)
        ->and(shopifyLoggedStatuses())->toBe([PlatformPortfolioLogsStatusEnum::FAIL, PlatformPortfolioLogsStatusEnum::OK]);
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
        'getProductVariantsToAdopt'     => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8200', 'sku' => 'match-me']]]]]]),
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

test('matching to a product sold as several variants links the variant that carries our sku and never creates another or touches its price', function () {
    Queue::fake();
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-adopt-variant');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'crbask-05a', 'customer_price' => 12]);

    ShopifyFake::fake([
        'getProductVariantsToAdopt'     => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [
            ['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'sku' => 'crbask-05b']],
            ['node' => ['id' => 'gid://shopify/ProductVariant/8402', 'sku' => 'CRBASK-05A']],
        ]]]]),
        'ProductVariantAdopt'           => ShopifyFake::graphql(['productVariantsBulkUpdate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8402']], 'userErrors' => []]]),
        'GetVariantInventoryItem'       => ShopifyFake::graphql(['productVariant' => ['inventoryItem' => ['id' => 'gid://shopify/InventoryItem/8402']]]),
        'InventoryActivate'             => ShopifyFake::graphql(['inventoryActivate' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/3'], 'userErrors' => []]]),
        'getProduct'                    => ShopifyFake::graphql(['product' => shopifyProductWithSiblingVariant('gid://shopify/Product/7400', 'gid://shopify/ProductVariant/8402', 'CRBASK-05A')]),
        'GET shop.json'                 => ['shop' => ['id' => 1]],
        'getProductExistence'           => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7400', 'title' => 'Juego de 3 cestas']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [
            ['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'inventoryItem' => ['inventoryLevel' => null]]],
            ['node' => ['id' => 'gid://shopify/ProductVariant/8402', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/3']]]],
        ]]]]),
    ]);

    MatchPortfolioToCurrentShopifyProduct::run($portfolio->refresh(), ['shopify_product_id' => 'gid://shopify/Product/7400']);
    $portfolio->refresh();

    $adoptedVariant = ShopifyFake::calls('ProductVariantAdopt')[0]['variables']['variants'][0];
    expect(ShopifyFake::calls('ProductVariantsCreate'))->toBeEmpty()
        ->and($adoptedVariant)->toBe(['id' => 'gid://shopify/ProductVariant/8402', 'inventoryItem' => ['tracked' => true]])
        ->and(ShopifyFake::calls('InventoryActivate')[0]['variables']['inventoryItemId'])->toBe('gid://shopify/InventoryItem/8402')
        ->and($portfolio->platform_product_id)->toBe('gid://shopify/Product/7400')
        ->and($portfolio->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8402')
        ->and($portfolio->sku)->toBe('CRBASK-05A')
        ->and($portfolio->isShopifyVariantAdopted())->toBeTrue()
        ->and($portfolio->errors_response)->toBeNull()
        ->and($portfolio->platform_status)->toBeTrue();

    $pushedVariants = [
        ['variantId' => 'gid://shopify/ProductVariant/8401', 'inventoryItemId' => 'gid://shopify/InventoryItem/8401', 'sku' => 'first-sibling'],
        ['variantId' => 'gid://shopify/ProductVariant/8402', 'inventoryItemId' => 'gid://shopify/InventoryItem/8402', 'sku' => 'CRBASK-05A'],
    ];
    expect(BulkUpdateShopifyPortfolio::resolveVariant($portfolio, $this->product, $pushedVariants)['variantId'])->toBe('gid://shopify/ProductVariant/8402');
});

test('a linked variant whose stock could not be activated is not shown as connected because a sibling variant is', function () {
    Queue::fake();
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-adopt-unstocked');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'crbask-05a', 'platform_status' => true]);

    ShopifyFake::fake([
        'getProductVariantsToAdopt'     => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [
            ['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'sku' => 'first-sibling']],
            ['node' => ['id' => 'gid://shopify/ProductVariant/8402', 'sku' => 'crbask-05a']],
        ]]]]),
        'ProductVariantAdopt'           => ShopifyFake::graphql(['productVariantsBulkUpdate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8402']], 'userErrors' => []]]),
        'GetVariantInventoryItem'       => ShopifyFake::graphql(['productVariant' => ['inventoryItem' => ['id' => 'gid://shopify/InventoryItem/8402']]]),
        'InventoryActivate'             => ShopifyFake::graphql(['inventoryActivate' => ['inventoryLevel' => null, 'userErrors' => [['field' => ['locationId'], 'message' => 'Location can not stock this item']]]]),
        'GET shop.json'                 => ['shop' => ['id' => 1]],
        'getProductExistence'           => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7400', 'title' => 'Juego de 3 cestas']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [
            ['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/1']]]],
            ['node' => ['id' => 'gid://shopify/ProductVariant/8402', 'inventoryItem' => ['inventoryLevel' => null]]],
        ]]]]),
        'getProductsByVariant'          => ShopifyFake::graphql(['products' => ['edges' => []]]),
    ]);

    MatchPortfolioToCurrentShopifyProduct::run($portfolio->refresh(), ['shopify_product_id' => 'gid://shopify/Product/7400']);
    $portfolio->refresh();

    expect($portfolio->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8402')
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->errors_response['message'])->toContain('Location can not stock this item');
});

test('a channel that was not switched on keeps creating its own variant even on a product sold as several variants', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-adopt-switched-off');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'crbask-05a', 'customer_price' => 12]);

    ShopifyFake::fake([
        'ProductVariantsList'           => ShopifyFake::graphql(['productVariants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'title' => 'Default', 'price' => '9.00', 'updatedAt' => 'x', 'inventoryQuantity' => 1, 'product' => ['id' => 'gid://shopify/Product/7400', 'title' => 'Juego de 3 cestas']]]]]]),
        'ProductVariantsCreate'         => ShopifyFake::graphql(['productVariantsBulkCreate' => ['productVariants' => [['id' => 'gid://shopify/ProductVariant/8403', 'title' => 'Default Title']], 'userErrors' => []]]),
        'getProduct'                    => ShopifyFake::graphql(['product' => shopifyProductNode('gid://shopify/Product/7400', 'gid://shopify/ProductVariant/8403', 'crbask-05a', '9.00')]),
        'GET shop.json'                 => ['shop' => ['id' => 1]],
        'getProductExistence'           => ShopifyFake::graphql(['product' => ['id' => 'gid://shopify/Product/7400', 'title' => 'Juego de 3 cestas']]),
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [['node' => ['id' => 'gid://shopify/ProductVariant/8403', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/4']]]]]]]]),
    ]);

    MatchPortfolioToCurrentShopifyProduct::run($portfolio->refresh(), ['shopify_product_id' => 'gid://shopify/Product/7400']);
    $portfolio->refresh();

    expect(ShopifyFake::calls('getProductVariantsToAdopt'))->toBeEmpty()
        ->and(ShopifyFake::calls('ProductVariantsCreate'))->toHaveCount(1)
        ->and($portfolio->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8403')
        ->and($portfolio->isShopifyVariantAdopted())->toBeFalse();
});

test('nothing of ours is ever written to the listing of a variant the merchant already had', function () {
    Queue::fake();
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-adopted-untouched');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update([
        'sku'                         => 'crbask-05a',
        'customer_price'              => 99,
        'platform_product_id'         => 'gid://shopify/Product/7400',
        'platform_product_variant_id' => 'gid://shopify/ProductVariant/8402',
        'settings'                    => ['shopify_variant_adopted' => true],
    ]);
    $portfolio->refresh();

    ShopifyFake::fake([]);

    UpdateShopifyProductVariant::run($portfolio);
    UpdateShopifyProduct::run($portfolio);
    UpdateShopifyProductDimensions::run($channel, $portfolio);
    [$variantCreated] = StoreShopifyProductVariant::run($portfolio);

    expect(ShopifyFake::$requests)->toBeEmpty()
        ->and($variantCreated)->toBeFalse();
});

test('an order line never falls back by product id onto a portfolio linked to a sibling variant, and unlinking it switches its variant off', function () {
    Queue::fake();
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-adopted-orders');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update([
        'sku'                         => 'crbask-05a',
        'platform_product_id'         => 'gid://shopify/Product/7400',
        'platform_product_variant_id' => 'gid://shopify/ProductVariant/8402',
        'settings'                    => ['shopify_variant_adopted' => true],
    ]);

    $orderLines = new class () {
        use WithShopifyPortfolioMatching;
    };

    expect($orderLines->matchShopifyLineItemToPortfolio($channel, 'gid://shopify/Product/7400', 'gid://shopify/ProductVariant/8401', 'first-sibling'))->toBeNull()
        ->and($portfolio->refresh()->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/8402')
        ->and($orderLines->matchShopifyLineItemToPortfolio($channel, 'gid://shopify/Product/7400', 'gid://shopify/ProductVariant/8402', 'crbask-05a')?->id)->toBe($portfolio->id);

    $portfolio->update(['settings' => []]);
    expect($orderLines->matchShopifyLineItemToPortfolio($channel, 'gid://shopify/Product/7400', 'gid://shopify/ProductVariant/8499', 'unknown')?->id)->toBe($portfolio->id);

    $portfolio->refresh()->update(['platform_product_variant_id' => 'gid://shopify/ProductVariant/8402', 'settings' => ['shopify_variant_adopted' => true]]);

    ShopifyFake::fake([
        'getProductInventoryAtLocation' => ShopifyFake::graphql(['product' => ['variants' => ['edges' => [
            ['node' => ['id' => 'gid://shopify/ProductVariant/8401', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/1']]]],
            ['node' => ['id' => 'gid://shopify/ProductVariant/8402', 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/2']]]],
        ]]]]),
        'inventoryDeactivate'           => ShopifyFake::graphql(['inventoryDeactivate' => ['userErrors' => []]]),
    ]);

    UnlinkRetinaPortfolio::run($portfolio->refresh());
    $portfolio->refresh();

    expect(ShopifyFake::calls('inventoryDeactivate')[0]['variables']['inventoryLevelId'])->toBe('gid://shopify/InventoryLevel/2')
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->isShopifyVariantAdopted())->toBeFalse();
});

test('removing a portfolio linked to one variant switches off that variant and leaves its siblings stocked', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-deactivate-variant');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['platform_product_id' => 'gid://shopify/Product/7400', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/8402', 'settings' => []]);
    $portfolio->markShopifyVariantAdopted(true);

    $levels = fn (array $variantLevels) => ShopifyFake::graphql(['product' => ['variants' => ['edges' => array_map(
        fn (string $variantId, string $levelId) => ['node' => ['id' => 'gid://shopify/ProductVariant/'.$variantId, 'inventoryItem' => ['inventoryLevel' => ['id' => 'gid://shopify/InventoryLevel/'.$levelId]]]],
        array_keys($variantLevels),
        $variantLevels
    )]]]);

    ShopifyFake::fake([
        'getProductInventoryAtLocation' => $levels(['8401' => '1', '8402' => '2']),
        'inventoryDeactivate'           => ShopifyFake::graphql(['inventoryDeactivate' => ['userErrors' => []]]),
    ]);

    expect(DeactivateShopifyProduct::run($portfolio->refresh()))->toBeTrue()
        ->and(ShopifyFake::calls('inventoryDeactivate')[0]['variables']['inventoryLevelId'])->toBe('gid://shopify/InventoryLevel/2');

    $portfolio->markShopifyVariantAdopted(false);
    DeactivateShopifyProduct::run($portfolio->refresh());

    expect(ShopifyFake::calls('inventoryDeactivate')[1]['variables']['inventoryLevelId'])->toBe('gid://shopify/InventoryLevel/1');
});

test('matching refuses to link, and creates nothing, when the variants of the product do not single out our sku', function (array $variantSkus) {
    Queue::fake();
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-adopt-refused-'.Str::random(6));
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'twin-sku']);

    ShopifyFake::fake([
        'getProductVariantsToAdopt' => Http::sequence()
            ->push(ShopifyFake::graphql(['product' => ['variants' => ['pageInfo' => ['hasNextPage' => true, 'endCursor' => 'v1'], 'edges' => [
                ['node' => ['id' => 'gid://shopify/ProductVariant/8501', 'sku' => $variantSkus[0]]],
            ]]]]))
            ->push(ShopifyFake::graphql(['product' => ['variants' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => [
                ['node' => ['id' => 'gid://shopify/ProductVariant/8502', 'sku' => $variantSkus[1]]],
            ]]]])),
        'GET shop.json'             => ['shop' => ['id' => 1]],
        'getProductsByVariant'      => ShopifyFake::graphql(['products' => ['edges' => []]]),
    ]);

    MatchPortfolioToCurrentShopifyProduct::run($portfolio->refresh(), ['shopify_product_id' => 'gid://shopify/Product/7500']);
    $portfolio->refresh();

    expect(ShopifyFake::calls('ProductVariantsCreate'))->toBeEmpty()
        ->and(ShopifyFake::calls('ProductVariantAdopt'))->toBeEmpty()
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->platform_product_variant_id)->toBeNull()
        ->and(ShopifyFake::calls('getProductVariantsToAdopt')[1]['variables']['cursor'])->toBe('v1')
        ->and($portfolio->errors_response['message'])->toContain('twin-sku');
})->with([
    'two variants share it, the second on a later page' => [['twin-sku', 'TWIN-SKU']],
    'no variant carries it'                             => [['colour-red', 'colour-blue']],
]);

test('the product picker says which existing variant a portfolio will link to, only for products sold as several variants', function () {
    $shopifyUser = shopifyVariantLinkingChannel($this, 'product-picker-hint');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = StorePortfolio::make()->action($channel, $this->product, []);
    $portfolio->update(['sku' => 'crbask-05a']);

    $listedProduct = fn (string $id, array $skus) => ['node' => ['id' => 'gid://shopify/Product/'.$id, 'title' => 'Listed '.$id, 'handle' => 'listed-'.$id, 'vendor' => 'Vendor', 'variants' => ['edges' => array_map(fn (string $sku) => ['node' => ['sku' => $sku]], $skus)], 'images' => ['edges' => []]]];

    ShopifyFake::fake([
        'listProducts' => ShopifyFake::graphql(['products' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => [
            $listedProduct('7601', ['CRBASK-05A', 'crbask-05b']),
            $listedProduct('7602', ['crbask-05a']),
            $listedProduct('7603', ['other-1', 'other-2']),
            $listedProduct('7604', ['crbask-05a', ...array_map(fn (int $position) => 'filler-'.$position, range(1, 9))]),
        ]]]),
    ]);

    $products = GetShopifyProducts::make()->handle($channel, ['portfolio' => $portfolio->id])['products'];

    expect(Arr::pluck($products, 'variant_to_link'))->toBe(['CRBASK-05A', null, null, null])
        ->and(GetShopifyProducts::make()->handle($channel, [])['products'][0])->not->toHaveKey('variant_to_link');
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

test('the stock push heals a stale variant id by sku, applies threshold and cap, records failures per line and activates unstocked items', function () {
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

    $variantEdge = fn (string $id, string $sku) => ['node' => ['id' => "gid://shopify/ProductVariant/$id", 'sku' => $sku, 'inventoryItem' => ['id' => "gid://shopify/InventoryItem/$id"]]];
    ShopifyFake::fake([
        'getProductsVariants'    => fn (array $variables) => ShopifyFake::graphql(['nodes' => array_map(fn (string $id) => match ($id) {
            'gid://shopify/Product/7400' => ['id' => $id, 'variants' => ['edges' => [$variantEdge('8400', '')]]],
            'gid://shopify/Product/7401' => ['id' => $id, 'variants' => ['edges' => [$variantEdge('8402', 'other-sku'), $variantEdge('8401', strtoupper($secondProduct->code))]]],
            default                      => null,
        }, $variables['ids'])]),
        'inventorySetQuantities' => ShopifyFake::graphql(['inventorySetQuantities' => ['userErrors' => [['field' => ['input', 'quantities', '1', 'inventoryItemId'], 'message' => 'The specified inventory item is not stocked at the location.']]]]),
    ]);

    BulkUpdateShopifyPortfolio::run($channel->id);

    $ids        = ShopifyFake::calls('getProductsVariants')[0]['variables']['ids'];
    $quantities = ShopifyFake::calls('inventorySetQuantities')[0]['variables']['input']['quantities'];
    expect(array_is_list($ids))->toBeTrue()
        ->and($ids)->toBe(['gid://shopify/Product/7400', 'gid://shopify/Product/7401'])
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
    StoreShopifyLocationToProductVariant::assertPushed(fn ($action, $parameters) => $parameters[0]->id === $stale->id);

    ShopifyFake::fake([
        'getProductsVariants'    => fn (array $variables) => ShopifyFake::graphql(['nodes' => array_map(fn (string $id) => ['id' => $id, 'variants' => ['edges' => [$variantEdge(Str::afterLast($id, '/') === '7400' ? '8400' : '8401', '')]]], $variables['ids'])]),
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

test('a store that cannot give us a client is reported once a day, not on every call', function () {
    Http::preventStrayRequests();

    $unavailable = new class () extends ShopifyUser {
        public function api(): \Gnikyt\BasicShopifyAPI\BasicShopifyAPI
        {
            throw new \Osiset\ShopifyApp\Exceptions\OAuthTokenRefreshException('This store is unavailable');
        }
    };
    $unavailable->forceFill(shopifyProductChannel($this, 'frozen-store')->getAttributes());
    $unavailable->exists = true;

    \Illuminate\Support\Facades\Cache::forget('shopify-client-failure:'.$unavailable->id);

    expect($unavailable->getShopifyClient())->toBeNull()
        ->and(\Illuminate\Support\Facades\Cache::has('shopify-client-failure:'.$unavailable->id))->toBeTrue()
        ->and($unavailable->getShopifyClient(true))->toBeNull();
});

test('an upload throwing a non-Exception error records it on the portfolio instead of vanishing', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-throws');
    $portfolio   = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $this->product, []);

    ShopifyFake::fake([
        'productCreate' => fn () => throw new Error('Call to a member function on null'),
    ]);

    StoreNewProductToCurrentShopify::make()->asJob($portfolio, ['cache_key' => 'upload_progress_test', 'total' => 1]);
    $portfolio->refresh();

    expect($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->errors_response)->not->toBeNull()
        ->and($portfolio->errors_response['message'])->toContain('Something went wrong on our side');

    expect(fn () => StoreNewProductToCurrentShopify::make()->asJob($portfolio->refresh()))
        ->toThrow(Error::class);
});

test('repairing a channel re-points portfolios whose product is gone onto the one active listing with the same sku, without writing to shopify', function () {
    Queue::fake();
    $shopifyUser = shopifyProductChannel($this, 'product-repair-connections');
    $channel     = $shopifyUser->customerSalesChannel;

    $gone = StorePortfolio::make()->action($channel, $this->product, []);
    $gone->update(['sku' => 'REPAIR-1', 'platform_product_id' => 'gid://shopify/Product/6001', 'platform_product_variant_id' => 'gid://shopify/ProductVariant/6001', 'platform_status' => true, 'errors_response' => ['message' => 'Throttled']]);

    $variantEdge = fn (string $variantId, string $sku, string $productId, string $status, bool $atLocation) => ['node' => [
        'id'            => $variantId,
        'sku'           => $sku,
        'product'       => ['id' => $productId, 'status' => $status],
        'inventoryItem' => ['inventoryLevel' => $atLocation ? ['id' => 'gid://shopify/InventoryLevel/1'] : null]
    ]];

    ShopifyFake::fake([
        'auditProductVariants' => ShopifyFake::graphql(['productVariants' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => [
            $variantEdge('gid://shopify/ProductVariant/9101', 'repair-1', 'gid://shopify/Product/9100', 'ACTIVE', false),
            $variantEdge('gid://shopify/ProductVariant/9201', 'other', 'gid://shopify/Product/9200', 'ACTIVE', true),
        ]]]),
    ]);

    $borrower = $gone->replicate();
    $borrower->fill(['item_id' => 990001, 'item_code' => 'BORROWER-1', 'sku' => 'other', 'platform_product_id' => 'gid://shopify/Product/6002', 'platform_product_variant_id' => null]);
    $borrower->save();
    $gone->update(['item_code' => 'REPAIR-1']);
    $owner = $gone->replicate();
    $owner->fill(['item_id' => 990002, 'item_code' => 'OTHER', 'sku' => 'other-x', 'platform_product_id' => 'gid://shopify/Product/6003', 'platform_product_variant_id' => null]);
    $owner->save();

    $dryRun = RepairShopifyPortfolioConnections::run($channel, null, true);

    expect($dryRun['repaired'])->toBe(2)
        ->and($gone->refresh()->platform_product_id)->toBe('gid://shopify/Product/6001');

    $result = RepairShopifyPortfolioConnections::run($channel);
    $gone->refresh();

    expect($result)->toMatchArray(['complete' => true, 'repaired' => 2, 'connected' => 1, 'not_at_location' => 1, 'skipped_sku_of_another_product' => 1, 'portfolio_ids' => [$gone->id, $owner->id]])
        ->and($borrower->refresh()->platform_product_id)->toBe('gid://shopify/Product/6002')
        ->and($owner->refresh()->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/9201')
        ->and($gone->platform_product_id)->toBe('gid://shopify/Product/9100')
        ->and($gone->platform_product_variant_id)->toBe('gid://shopify/ProductVariant/9101')
        ->and($gone->platform_status)->toBeFalse()
        ->and($gone->errors_response)->toBeNull()
        ->and($gone->isShopifyVariantAdopted())->toBeTrue()
        ->and(array_unique(array_column(ShopifyFake::$requests, 'operation')))->toBe(['auditProductVariants']);
});
