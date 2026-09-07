<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 14:30:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\CallbackFulfillmentOrderNotification;
use App\Actions\Dropshipping\Shopify\Fulfilment\CancelFulfillOrderToShopify;
use App\Actions\Dropshipping\Shopify\Fulfilment\CloseFulfillOrderToShopify;
use App\Actions\Dropshipping\Shopify\Fulfilment\FulfillOrderToShopify;
use App\Actions\Dropshipping\Shopify\Fulfilment\UI\SyncOrderCancellationToShopify;
use App\Actions\Dropshipping\Shopify\FulfilmentService\AdoptShopifyFulfilmentService;
use App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteAllFulfilmentServices;
use App\Actions\Dropshipping\Shopify\FulfilmentService\StoreFulfilmentService;
use App\Actions\Dropshipping\Shopify\Order\FetchShopifyOrdersFromApi;
use App\Actions\Dropshipping\Shopify\Order\GetShopifyFulfilmentOrderFromApi;
use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolios;
use App\Actions\Dropshipping\Shopify\ResetShopifyChannel;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Actions\Dropshipping\ShopifyUser\WebhookUninstalledShopifyUser;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Listeners\ShopifyAppInstalledListener;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Tests\Support\ShopifyFake;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user         = createAdminGuest($this->group)->getUser();
    $this->warehouse    = createWarehouse();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);
        $shop = StoreShop::make()->action($this->organisation, $storeData);
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    [, $this->product] = createProduct($this->shop);

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

afterEach(function () {
    expect(ShopifyFake::$stray)->toBe([]);
});

/**
 * A fresh customer with a connected Shopify store whose fulfilment service and location are already known.
 */
function shopifyOrderChannel($test, string $name): ShopifyUser
{
    $customer    = StoreCustomer::make()->action($test->shop, Customer::factory()->definition());
    $shopifyUser = StoreShopifyUser::make()->handle($customer, ['name' => $name]);
    $shopifyUser->update([
        'shopify_location_id'           => 'gid://shopify/Location/1001',
        'shopify_fulfilment_service_id' => 'gid://shopify/FulfillmentService/501',
    ]);

    return $shopifyUser->refresh();
}

function shopifyPortfolioFor($test, ShopifyUser $shopifyUser, string $productGid = 'gid://shopify/Product/7001', string $variantGid = 'gid://shopify/ProductVariant/8001'): Portfolio
{
    $portfolio = StorePortfolio::make()->action($shopifyUser->customerSalesChannel, $test->product, []);
    $portfolio->update([
        'platform_product_id'         => $productGid,
        'platform_product_variant_id' => $variantGid,
        'platform_status'             => true,
    ]);

    return $portfolio->refresh();
}

/**
 * @param  array<int, array<string, mixed>>  $lineItems
 */
function shopifyFulfilmentOrder(array $lineItems, array $overrides = []): array
{
    $edges = [];
    foreach ($lineItems as $index => $lineItem) {
        $edges[] = ['node' => array_merge([
            'id'                => 'gid://shopify/FulfillmentOrderLineItem/'.(9001 + $index),
            'productTitle'      => 'Listed product',
            'sku'               => null,
            'remainingQuantity' => 2,
            'lineItem'          => [
                'variant' => ['id' => 'gid://shopify/ProductVariant/8001'],
                'product' => ['id' => 'gid://shopify/Product/7001'],
            ],
        ], $lineItem)];
    }

    return array_replace_recursive([
        'id'          => 'gid://shopify/FulfillmentOrder/6001',
        'destination' => [
            'firstName'   => 'Ada',
            'lastName'    => 'Lovelace',
            'address1'    => '12 Analytical Lane',
            'address2'    => null,
            'city'        => 'London',
            'province'    => null,
            'zip'         => 'N1 9GU',
            'countryCode' => Country::latest()->first()->code,
            'phone'       => '+44 20 7946 0958',
        ],
        'order'       => [
            'id'          => 'gid://shopify/Order/5001',
            'createdAt'   => '2026-09-01T10:00:00Z',
            'processedAt' => '2026-09-01T10:05:00Z',
            'customer'    => [
                'id'        => 'gid://shopify/Customer/4001',
                'firstName' => 'Ada',
                'lastName'  => 'Lovelace',
                'email'     => 'ada@example.com',
                'phone'     => '+44 20 7946 0958',
            ],
        ],
        'lineItems'   => ['edges' => $edges],
        'merchantRequests' => ['edges' => []],
    ], $overrides);
}

function shopifyAssignedOrdersReply(array $fulfilmentOrders): array
{
    return ShopifyFake::graphql(['shop' => ['assignedFulfillmentOrders' => ['edges' => array_map(fn ($node) => ['node' => $node], $fulfilmentOrders)]]]);
}

test('a fulfilment request webhook creates the aiku order with client, address and milestones then accepts it in shopify', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-intake');
    $portfolio   = shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([['sku' => $portfolio->sku]])]),
        'acceptFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    $accept = ShopifyFake::calls('acceptFulfillmentRequest');
    expect($accept)->toHaveCount(1)
        ->and($accept[0]['variables']['id'])->toBe('gid://shopify/FulfillmentOrder/6001');

    $order = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6001')->first();
    expect($order)->not->toBeNull()
        ->and($order->customer_sales_channel_id)->toBe($shopifyUser->customer_sales_channel_id)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->transactions()->count())->toBe(1)
        ->and((float) $order->transactions()->first()->quantity_ordered)->toBe(2.0)
        ->and($order->transactions()->first()->platform_transaction_id)->toBe('gid://shopify/FulfillmentOrderLineItem/9001')
        ->and($order->data['platform_milestones'])->toEqual(['draft_created_at' => '2026-09-01T10:00:00Z', 'placed_at' => '2026-09-01T10:05:00Z'])
        ->and($order->data['shopify_data']['order']['id'])->toBe('gid://shopify/Order/5001')
        ->and($order->deliveryAddress->postal_code)->toBe('N1 9GU')
        ->and($order->deliveryAddress->address_line_1)->toBe('12 Analytical Lane');

    $client = $order->customerClient;
    expect($client->contact_name)->toBe('Ada Lovelace')
        ->and($client->email)->toBe('ada@example.com')
        ->and($client->phone)->toBe('+442079460958')
        ->and($client->platform_customer_id)->toBe('gid://shopify/Customer/4001')
        ->and($client->reference)->toBe('AdaLovelace'.$shopifyUser->customer_sales_channel_id)
        ->and($shopifyUser->debugWebhooks()->count())->toBe(1);
});

test('the same fulfilment request arriving twice creates one order and one client', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-twice');
    shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6005'])]),
        'acceptFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    $channel = $shopifyUser->customerSalesChannel;
    expect(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6005')->count())->toBe(1)
        ->and($channel->clients()->count())->toBe(1)
        ->and(ShopifyFake::calls('acceptFulfillmentRequest'))->toHaveCount(2);
});

test('a line item outside the portfolio splits the request, rejects the rest and resubmits the matched part', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-split');
    shopifyPortfolioFor($this, $shopifyUser);

    $fulfilmentOrder = shopifyFulfilmentOrder([
        [],
        ['sku' => 'not-ours', 'lineItem' => ['variant' => ['id' => 'gid://shopify/ProductVariant/1'], 'product' => ['id' => 'gid://shopify/Product/1']]],
    ]);

    ShopifyFake::fake([
        'assignedFulfillmentOrders'              => shopifyAssignedOrdersReply([$fulfilmentOrder]),
        'rejectFulfillmentRequest'               => ShopifyFake::graphql(['fulfillmentOrderRejectFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'REJECTED'], 'userErrors' => []]]),
        'fulfillmentOrderSplit'                  => ShopifyFake::graphql(['fulfillmentOrderSplit' => ['fulfillmentOrderSplits' => [[
            'fulfillmentOrder'          => ['id' => 'gid://shopify/FulfillmentOrder/6001', 'lineItems' => ['edges' => []]],
            'remainingFulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/6002', 'lineItems' => ['edges' => []]],
        ]], 'userErrors' => []]]),
        'fulfillmentOrderSubmitFulfillmentRequest' => ShopifyFake::graphql(['fulfillmentOrderSubmitFulfillmentRequest' => ['originalFulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/6002', 'status' => 'OPEN', 'requestStatus' => 'SUBMITTED'], 'userErrors' => []]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    $split = ShopifyFake::calls('fulfillmentOrderSplit');
    expect(ShopifyFake::calls('rejectFulfillmentRequest')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentOrder/6001')
        ->and($split)->toHaveCount(1)
        ->and($split[0]['variables']['fulfillmentOrderSplits'][0]['fulfillmentOrderLineItems'])->toBe([['id' => 'gid://shopify/FulfillmentOrderLineItem/9001', 'quantity' => 2]])
        ->and(ShopifyFake::calls('fulfillmentOrderSubmitFulfillmentRequest')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentOrder/6002')
        ->and(ShopifyFake::calls('acceptFulfillmentRequest'))->toBe([])
        ->and(Order::where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)->count())->toBe(0);
});

test('a request with nothing from the portfolio is rejected without a split and a failed split does not blow up', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-reject');
    shopifyPortfolioFor($this, $shopifyUser);

    $foreign = ['sku' => 'not-ours', 'lineItem' => ['variant' => ['id' => 'gid://shopify/ProductVariant/1'], 'product' => ['id' => 'gid://shopify/Product/1']]];

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([$foreign])]),
        'rejectFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderRejectFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'REJECTED'], 'userErrors' => []]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    expect(ShopifyFake::calls('rejectFulfillmentRequest'))->toHaveCount(1)
        ->and(ShopifyFake::calls('fulfillmentOrderSplit'))->toBe([]);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([[], $foreign])]),
        'rejectFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderRejectFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'REJECTED'], 'userErrors' => []]]),
        'fulfillmentOrderSplit'     => Http::response(['errors' => [['message' => 'Internal error']]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    expect(ShopifyFake::calls('fulfillmentOrderSubmitFulfillmentRequest'))->toBe([])
        ->and(ShopifyFake::calls('acceptFulfillmentRequest'))->toBe([]);
});

test('lines with nothing left to fulfil are skipped and a guest checkout without a shopify customer still becomes an order', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-guest');
    shopifyPortfolioFor($this, $shopifyUser);

    $accept = ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([['remainingQuantity' => 0]], ['id' => 'gid://shopify/FulfillmentOrder/6006'])]),
        'acceptFulfillmentRequest'  => $accept,
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    expect(ShopifyFake::calls('acceptFulfillmentRequest'))->toHaveCount(1)
        ->and(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6006')->exists())->toBeFalse();

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([['remainingQuantity' => 3]], ['id' => 'gid://shopify/FulfillmentOrder/6003', 'order' => ['customer' => null]])]),
        'acceptFulfillmentRequest'  => $accept,
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    $order = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6003')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->transactions()->first()->quantity_ordered)->toBe(3.0)
        ->and($order->customerClient->contact_name)->toBe('Ada Lovelace')
        ->and($order->customerClient->email)->toBeNull()
        ->and($order->customerClient->platform_customer_id)->toBeNull();
});

test('a cancellation request is accepted for an order still in the office and rejected once the warehouse has it', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-cancel');
    shopifyPortfolioFor($this, $shopifyUser);

    $accept = ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]);
    ShopifyFake::fake([
        'assignedFulfillmentOrders' => fn (array $variables) => shopifyAssignedOrdersReply([
            shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6010']),
            shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6011', 'order' => ['id' => 'gid://shopify/Order/5011']]),
        ]),
        'acceptFulfillmentRequest'  => $accept,
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    $cancellable = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6010')->firstOrFail();
    $picking     = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6011')->firstOrFail();
    $picking->update(['state' => OrderStateEnum::HANDLING]);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => fn (array $variables) => shopifyAssignedOrdersReply([
            shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6010']),
            shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6011']),
            shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6099']),
        ]),
        'acceptCancellationRequest' => ShopifyFake::graphql(['fulfillmentOrderAcceptCancellationRequest' => ['fulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/6010', 'status' => 'CANCELLED', 'requestStatus' => 'CANCELLATION_ACCEPTED'], 'userErrors' => []]]),
        'rejectCancellationRequest' => ShopifyFake::graphql(['fulfillmentOrderRejectCancellationRequest' => ['fulfillmentOrder' => ['status' => 'IN_PROGRESS', 'requestStatus' => 'CANCELLATION_REJECTED'], 'userErrors' => []]]),
    ]);

    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'CANCELLATION_REQUEST']);

    expect(ShopifyFake::calls('assignedFulfillmentOrders')[0]['variables']['assignmentStatus'])->toBe('CANCELLATION_REQUESTED')
        ->and(ShopifyFake::calls('acceptCancellationRequest'))->toHaveCount(1)
        ->and(ShopifyFake::calls('acceptCancellationRequest')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentOrder/6010')
        ->and(ShopifyFake::calls('rejectCancellationRequest'))->toHaveCount(1)
        ->and(ShopifyFake::calls('rejectCancellationRequest')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentOrder/6011')
        ->and($cancellable->refresh()->state)->toBe(OrderStateEnum::CANCELLED)
        ->and($picking->refresh()->state)->toBe(OrderStateEnum::HANDLING);
});

test('the fulfilment order notification route refuses a store that has no customer and otherwise processes the kind', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-route');
    shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([]),
    ]);

    $url = 'https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id.'/fulfillment_order_notification';

    $this->postJson($url, ['kind' => 'FULFILLMENT_REQUEST'])->assertSuccessful();
    expect(ShopifyFake::calls('assignedFulfillmentOrders'))->toHaveCount(1);

    $this->postJson($url, [])->assertStatus(422);

    $orphan = ShopifyUser::create(['name' => 'orphan-route.myshopify.com', 'password' => 'x', 'group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'language_id' => $shopifyUser->language_id]);
    $this->postJson('https://'.config('app.domain').'/webhooks/shopify/'.$orphan->id.'/fulfillment_order_notification', ['kind' => 'FULFILLMENT_REQUEST'])->assertStatus(422);
});

test('the order poller imports the open fulfilment order of each unfulfilled shopify order through the webhook path', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-poll');
    shopifyPortfolioFor($this, $shopifyUser);

    $orderNode = function (string $orderGid, array $fulfilmentOrders) {
        return [
            'id'                => $orderGid,
            'name'              => '#1001',
            'createdAt'         => '2026-09-02T08:00:00Z',
            'processedAt'       => '2026-09-02T08:01:00Z',
            'customer'          => ['id' => 'gid://shopify/Customer/4001', 'email' => 'ada@example.com', 'firstName' => 'Ada', 'lastName' => 'Lovelace', 'phone' => null],
            'fulfillmentOrders' => ['edges' => array_map(fn ($node) => ['node' => $node], $fulfilmentOrders)],
        ];
    };
    $fulfilmentOrder = fn (string $gid, string $status) => array_merge(shopifyFulfilmentOrder([[]]), ['id' => $gid, 'status' => $status]);

    ShopifyFake::fake([
        'getUnfulfilledOrders' => ShopifyFake::graphql(['orders' => ['edges' => [
            ['node' => $orderNode('gid://shopify/Order/5020', [$fulfilmentOrder('gid://shopify/FulfillmentOrder/6020', 'CLOSED'), $fulfilmentOrder('gid://shopify/FulfillmentOrder/6021', 'OPEN')])],
            ['node' => $orderNode('gid://shopify/Order/5022', [])],
        ]]]),
    ]);

    FetchShopifyOrdersFromApi::run($shopifyUser, 7);

    $query = ShopifyFake::calls('getUnfulfilledOrders')[0]['variables']['query'];
    expect($query)->toStartWith('fulfillment_status:unfulfilled AND created_at:>'.now()->subDays(7)->toDateString())
        ->and(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6021')->exists())->toBeTrue()
        ->and(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6020')->exists())->toBeFalse()
        ->and(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6021')->first()->data['platform_milestones']['placed_at'])->toBe('2026-09-02T08:01:00Z');

    ShopifyFake::fake([
        'getUnfulfilledOrders' => Http::response(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]]),
    ]);
    expect(fn () => FetchShopifyOrdersFromApi::run($shopifyUser))->toThrow(Exception::class);

    ShopifyFake::fake([
        'getFulfilmentOrder' => ShopifyFake::graphql(['order' => $orderNode('gid://shopify/Order/5030', [$fulfilmentOrder('gid://shopify/FulfillmentOrder/6030', 'IN_PROGRESS')])]),
    ]);
    $payload = GetShopifyFulfilmentOrderFromApi::run($shopifyUser, '5030');
    expect(ShopifyFake::calls('getFulfilmentOrder')[0]['variables']['id'])->toBe('gid://shopify/Order/5030')
        ->and($payload['id'])->toBe('gid://shopify/FulfillmentOrder/6030')
        ->and($payload['order']['id'])->toBe('gid://shopify/Order/5030');
});

test('dispatching sends the fulfilment to shopify with the tracking of the delivery note and a refusal never blocks the dispatch', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-fulfil');
    shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6040'])]),
        'acceptFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    $order = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6040')->firstOrFail();

    $deliveryNote = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN'.Str::random(6),
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id,
    ]);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'SH'.Str::random(4), 'name' => 'Royal Mail', 'trade_as' => 'Royal Mail']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'RM123']);
    $deliveryNote->shipments()->first()->update(['tracking_urls' => ['https://track.example/RM123']]);

    ShopifyFake::fake([
        'fulfillmentCreate' => ShopifyFake::graphql(['fulfillmentCreate' => ['fulfillment' => ['id' => 'gid://shopify/Fulfillment/1'], 'userErrors' => []]]),
    ]);
    FulfillOrderToShopify::run($order, $deliveryNote->refresh());

    $variables = ShopifyFake::calls('fulfillmentCreate')[0]['variables'];
    expect($variables['fulfillment']['lineItemsByFulfillmentOrder'])->toBe([['fulfillmentOrderId' => 'gid://shopify/FulfillmentOrder/6040']])
        ->and($variables['fulfillment']['notifyCustomer'])->toBeTrue()
        ->and($variables['fulfillment']['trackingInfo'])->toBe(['numbers' => ['RM123'], 'company' => 'Royal Mail', 'urls' => ['https://track.example/RM123']])
        ->and($variables['message'])->toBe('Shipper: Royal Mail, RM123');

    ShopifyFake::fake([
        'fulfillmentCreate' => ShopifyFake::graphql(['fulfillmentCreate' => ['fulfillment' => null, 'userErrors' => [['field' => ['fulfillment'], 'message' => 'Fulfillment order is already fulfilled']]]]),
    ]);
    FulfillOrderToShopify::run($order, $deliveryNote);
    expect(ShopifyFake::calls('fulfillmentCreate'))->toHaveCount(1);
});

test('closing and cancelling a fulfilment order in shopify send the fulfilment order id and refuse to sync a live order', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-close');
    shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => shopifyAssignedOrdersReply([shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6050'])]),
        'acceptFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    $order = Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6050')->firstOrFail();

    ShopifyFake::fake([
        'fulfillmentOrderClose'  => ShopifyFake::graphql(['fulfillmentOrderClose' => ['fulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/6050', 'status' => 'INCOMPLETE', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
        'fulfillmentOrderCancel' => ShopifyFake::graphql(['fulfillmentOrderCancel' => ['fulfillmentOrder' => null, 'userErrors' => [['field' => ['id'], 'message' => 'Cannot cancel a closed fulfillment order']]]]),
    ]);

    expect(fn () => SyncOrderCancellationToShopify::run($order))->toThrow(ValidationException::class);

    $order->update(['state' => OrderStateEnum::CANCELLED]);
    SyncOrderCancellationToShopify::run($order->refresh());
    expect(ShopifyFake::calls('fulfillmentOrderClose')[0]['variables'])->toBe(['id' => 'gid://shopify/FulfillmentOrder/6050']);

    expect(fn () => CancelFulfillOrderToShopify::run($order))->toThrow(ValidationException::class, 'closed fulfillment order');

    ShopifyFake::fake(['fulfillmentOrderClose' => Http::response(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]])]);
    expect(fn () => CloseFulfillOrderToShopify::run($order))->toThrow(ValidationException::class, 'Throttled');
});

test('a throttled shopify reply is retried after the wait shopify asks for', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'orders-throttle');
    shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => Http::sequence()
            ->push(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]], 'extensions' => ['cost' => ['requestedQueryCost' => 120, 'throttleStatus' => ['currentlyAvailable' => 20, 'restoreRate' => 100]]]])
            ->push(shopifyAssignedOrdersReply([shopifyFulfilmentOrder([[]], ['id' => 'gid://shopify/FulfillmentOrder/6060'])])),
        'acceptFulfillmentRequest'  => ShopifyFake::graphql(['fulfillmentOrderAcceptFulfillmentRequest' => ['fulfillmentOrder' => ['status' => 'OPEN', 'requestStatus' => 'ACCEPTED'], 'userErrors' => []]]),
    ]);

    $started = microtime(true);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);

    expect(ShopifyFake::calls('assignedFulfillmentOrders'))->toHaveCount(2)
        ->and(microtime(true) - $started)->toBeGreaterThan(0.9)
        ->and(Order::where('platform_order_id', 'gid://shopify/FulfillmentOrder/6060')->exists())->toBeTrue();

    ShopifyFake::fake([
        'assignedFulfillmentOrders' => Http::response(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]]),
    ]);
    CallbackFulfillmentOrderNotification::run($shopifyUser, ['kind' => 'FULFILLMENT_REQUEST']);
    expect(ShopifyFake::calls('assignedFulfillmentOrders'))->toHaveCount(3);
});

function shopifyShopReply(array $fulfilmentServices, string $name = 'Ada Store'): array
{
    return ShopifyFake::graphql(['shop' => [
        'id'                  => 'gid://shopify/Shop/100',
        'name'                => $name,
        'email'               => 'owner@example.com',
        'url'                 => 'https://ada.example',
        'myshopifyDomain'     => 'ada.myshopify.com',
        'description'         => null,
        'fulfillmentServices' => $fulfilmentServices,
        'billingAddress'      => ['company_name' => 'Ada Ltd', 'address_line_1' => '1 Road', 'address_line_2' => null, 'locality' => 'London', 'administrative_area' => null, 'postal_code' => 'N1', 'country_code' => 'GB'],
    ]]);
}

function shopifyFulfilmentServiceNode(string $id, string $name, string $locationId, string $createdAt = '2026-01-01T00:00:00Z'): array
{
    return [
        'id'                  => $id,
        'serviceName'         => $name,
        'inventoryManagement' => true,
        'callbackUrl'         => 'https://app.example/webhooks/shopify/1',
        'type'                => 'THIRD_PARTY',
        'location'            => ['id' => $locationId, 'name' => $name, 'createdAt' => $createdAt, 'isActive' => true, 'fulfillsOnlineOrders' => true, 'address' => []],
    ];
}

test('creating the fulfilment service names it after the channel, learns its location and switches the channel live', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'service-create');
    $shopifyUser->update(['shopify_location_id' => null, 'shopify_fulfilment_service_id' => null]);
    $channel     = $shopifyUser->customerSalesChannel;
    $serviceName = 'aiku-'.$this->shop->slug.' ('.$channel->slug.')';

    ShopifyFake::fake([
        'fulfillmentServiceCreate' => ShopifyFake::graphql(['fulfillmentServiceCreate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/700', 'serviceName' => $serviceName, 'callbackUrl' => 'x', 'inventoryManagement' => true, 'trackingSupport' => false, 'fulfillmentOrdersOptIn' => true], 'userErrors' => []]]),
        'shop'                     => shopifyShopReply([
            shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/1', 'Other App', 'gid://shopify/Location/1'),
            shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700'),
        ]),
        'locationEdit'             => ShopifyFake::graphql(['locationEdit' => ['location' => ['id' => 'gid://shopify/Location/1700', 'name' => $serviceName, 'address' => []], 'userErrors' => []]]),
    ]);

    [$ok, $service] = StoreFulfilmentService::run($channel);

    $create = ShopifyFake::calls('fulfillmentServiceCreate')[0]['variables'];
    expect($ok)->toBeTrue()
        ->and($service['id'])->toBe('gid://shopify/FulfillmentService/700')
        ->and($create['name'])->toBe($serviceName)
        ->and($create['callbackUrl'])->toBe('https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id)
        ->and($create['inventoryManagement'])->toBeTrue()
        ->and(ShopifyFake::calls('locationEdit')[0]['variables']['id'])->toBe('gid://shopify/Location/1700');

    $channel->refresh();
    $shopifyUser->refresh();
    expect($channel->platform_status)->toBeTrue()
        ->and($channel->exist_in_platform)->toBeTrue()
        ->and($channel->can_connect_to_platform)->toBeTrue()
        ->and($channel->name)->toBe('Ada Store')
        ->and($shopifyUser->shopify_fulfilment_service_id)->toBe('gid://shopify/FulfillmentService/700')
        ->and($shopifyUser->shopify_location_id)->toBe('gid://shopify/Location/1700')
        ->and($shopifyUser->shopify_shop_id)->toBe('gid://shopify/Shop/100')
        ->and($shopifyUser->data['shop']['company_name'])->toBe('Ada Ltd');

    ShopifyFake::fake([
        'fulfillmentServiceCreate' => ShopifyFake::graphql(['fulfillmentServiceCreate' => ['fulfillmentService' => null, 'userErrors' => [['field' => ['name'], 'message' => 'has already been taken']]]]),
    ]);
    [$ok, $message] = StoreFulfilmentService::run($channel);
    expect($ok)->toBeFalse()->and($message)->toContain('already been taken');
});

test('a store that cannot be read keeps the channel name and only marks it unreachable', function () {
    $shopifyUser = shopifyOrderChannel($this, 'service-unreachable');
    $channel     = $shopifyUser->customerSalesChannel;
    $channel->update(['name' => 'Kept Name', 'platform_status' => true, 'can_connect_to_platform' => true]);

    ShopifyFake::fake(['shop' => Http::response(['errors' => 'Invalid API key or access token'], 401)]);

    CheckShopifyChannel::run($channel);
    $channel->refresh();

    expect($channel->name)->toBe('Kept Name')
        ->and($channel->can_connect_to_platform)->toBeFalse()
        ->and($channel->platform_status)->toBeFalse();
});

test('deleting all fulfilment services only touches aiku services and adopting keeps the oldest one', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'service-adopt');
    $channel     = $shopifyUser->customerSalesChannel;
    $serviceName = 'aiku-'.$this->shop->slug.' ('.$channel->slug.')';
    $shopifyUser->update(['shopify_fulfilment_service_id' => 'gid://shopify/FulfillmentService/new']);

    $services = [
        shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/manual', 'Manual', 'gid://shopify/Location/1', '2024-01-01T00:00:00Z'),
        shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/new', $serviceName.'-1', 'gid://shopify/Location/3', '2026-08-30T00:00:00Z'),
        shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/old', $serviceName, 'gid://shopify/Location/2', '2025-09-23T00:00:00Z'),
    ];

    ShopifyFake::fake([
        'shop'                     => shopifyShopReply($services),
        'fulfillmentServiceDelete' => fn (array $variables) => ShopifyFake::graphql(['fulfillmentServiceDelete' => ['deletedId' => $variables['id'], 'userErrors' => []]]),
    ]);

    [$ok, $results] = DeleteAllFulfilmentServices::run($channel);
    expect($ok)->toBeTrue()
        ->and(collect($results)->pluck('id')->all())->toBe(['gid://shopify/FulfillmentService/new', 'gid://shopify/FulfillmentService/old'])
        ->and(collect(ShopifyFake::calls('fulfillmentServiceDelete'))->pluck('variables.id')->all())->not->toContain('gid://shopify/FulfillmentService/manual');

    ShopifyFake::fake([
        'shop'                     => shopifyShopReply($services),
        'fulfillmentServiceUpdate' => ShopifyFake::graphql(['fulfillmentServiceUpdate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/old', 'serviceName' => $serviceName, 'callbackUrl' => 'x'], 'userErrors' => []]]),
        'fulfillmentServiceDelete' => fn (array $variables) => ShopifyFake::graphql(['fulfillmentServiceDelete' => ['deletedId' => $variables['id'], 'userErrors' => []]]),
    ]);

    [$ok, $plan] = AdoptShopifyFulfilmentService::run($channel, true);
    expect($ok)->toBeTrue()->and($plan)->toContain('adopt '.$serviceName)->and(ShopifyFake::calls('fulfillmentServiceUpdate'))->toBe([]);

    [$ok] = AdoptShopifyFulfilmentService::run($channel);
    $shopifyUser->refresh();
    expect($ok)->toBeTrue()
        ->and(ShopifyFake::calls('fulfillmentServiceUpdate')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentService/old')
        ->and(ShopifyFake::calls('fulfillmentServiceUpdate')[0]['variables']['name'])->toBe($serviceName)
        ->and(ShopifyFake::calls('fulfillmentServiceDelete')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentService/new')
        ->and($shopifyUser->shopify_fulfilment_service_id)->toBe('gid://shopify/FulfillmentService/old')
        ->and($shopifyUser->shopify_location_id)->toBe('gid://shopify/Location/2');
    CheckShopifyPortfolios::assertPushed();
});

test('installing the app registers the uninstall webhook, reads the store and creates the fulfilment service', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'install-flow');
    $shopifyUser->update(['shopify_location_id' => null, 'shopify_fulfilment_service_id' => null]);
    $channel     = $shopifyUser->customerSalesChannel;
    $serviceName = 'aiku-'.$this->shop->slug.' ('.$channel->slug.')';

    ShopifyFake::fake([
        'webhookSubscriptionCreate' => fn (array $variables) => ShopifyFake::graphql(['webhookSubscriptionCreate' => ['webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1', 'topic' => $variables['topic'], 'endpoint' => ['__typename' => 'WebhookHttpEndpoint', 'callbackUrl' => $variables['webhookSubscription']['callbackUrl']], 'format' => 'JSON'], 'userErrors' => []]]),
        'shop'                      => Http::sequence()
            ->push(shopifyShopReply([]))
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700')]))
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700')])),
        'fulfillmentServiceCreate'  => ShopifyFake::graphql(['fulfillmentServiceCreate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/700', 'serviceName' => $serviceName, 'callbackUrl' => 'x', 'inventoryManagement' => true, 'trackingSupport' => false, 'fulfillmentOrdersOptIn' => true], 'userErrors' => []]]),
        'locationEdit'              => ShopifyFake::graphql(['locationEdit' => ['location' => ['id' => 'gid://shopify/Location/1700', 'name' => $serviceName, 'address' => []], 'userErrors' => []]]),
    ]);

    (new ShopifyAppInstalledListener())->handle(new AppInstalledEvent(ShopId::fromNative($shopifyUser->id)));

    $webhook = ShopifyFake::calls('webhookSubscriptionCreate');
    expect($webhook)->toHaveCount(1)
        ->and($webhook[0]['variables']['topic'])->toBe('APP_UNINSTALLED')
        ->and($webhook[0]['variables']['webhookSubscription']['callbackUrl'])->toBe('https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id.'/app-uninstalled')
        ->and($channel->refresh()->platform_status)->toBeTrue()
        ->and($shopifyUser->refresh()->shopify_location_id)->toBe('gid://shopify/Location/1700');
});

test('uninstalling the app closes the channel, removes aiku webhooks and fulfilment service and retires the login', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'uninstall-flow');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'webhookSubscriptions'      => ShopifyFake::graphql(['webhookSubscriptions' => ['edges' => [['node' => ['id' => 'gid://shopify/WebhookSubscription/1', 'topic' => 'APP_UNINSTALLED', 'endpoint' => ['__typename' => 'WebhookHttpEndpoint', 'callbackUrl' => 'x']]]]]]),
        'webhookSubscriptionDelete' => fn (array $variables) => ShopifyFake::graphql(['webhookSubscriptionDelete' => ['deletedWebhookSubscriptionId' => $variables['id'], 'userErrors' => []]]),
        'fulfillmentServiceDelete'  => fn (array $variables) => ShopifyFake::graphql(['fulfillmentServiceDelete' => ['deletedId' => $variables['id'], 'userErrors' => []]]),
    ]);

    WebhookUninstalledShopifyUser::run($shopifyUser);

    expect(ShopifyFake::calls('webhookSubscriptionDelete')[0]['variables']['id'])->toBe('gid://shopify/WebhookSubscription/1')
        ->and(ShopifyFake::calls('fulfillmentServiceDelete')[0]['variables']['id'])->toBe('gid://shopify/FulfillmentService/501')
        ->and($channel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($channel->closed_at)->not->toBeNull()
        ->and(ShopifyUser::withTrashed()->find($shopifyUser->id)->trashed())->toBeTrue()
        ->and(ShopifyUser::withTrashed()->find($shopifyUser->id)->data['original_data']['name'])->toBe('uninstall-flow.myshopify.com');

    WebhookUninstalledShopifyUser::run(ShopifyUser::withTrashed()->find($shopifyUser->id));
    expect(ShopifyFake::calls('webhookSubscriptions'))->toHaveCount(1);
});

test('resetting a channel rebuilds only aiku webhooks and fulfilment service on the store', function () {
    Queue::fake();
    $shopifyUser = shopifyOrderChannel($this, 'reset-flow');
    $channel     = $shopifyUser->customerSalesChannel;
    $serviceName = 'aiku-'.$this->shop->slug.' ('.$channel->slug.')';

    ShopifyFake::fake([
        'webhookSubscriptions'      => ShopifyFake::graphql(['webhookSubscriptions' => ['edges' => [['node' => ['id' => 'gid://shopify/WebhookSubscription/1', 'topic' => 'APP_UNINSTALLED', 'endpoint' => ['__typename' => 'WebhookHttpEndpoint', 'callbackUrl' => 'x']]]]]]),
        'webhookSubscriptionDelete' => fn (array $variables) => ShopifyFake::graphql(['webhookSubscriptionDelete' => ['deletedWebhookSubscriptionId' => $variables['id'], 'userErrors' => []]]),
        'shop'                      => Http::sequence()
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/1', 'Other App', 'gid://shopify/Location/1'), shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/501', $serviceName, 'gid://shopify/Location/1001')]))
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/1', 'Other App', 'gid://shopify/Location/1'), shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700')]))
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/1', 'Other App', 'gid://shopify/Location/1'), shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700')]))
            ->push(shopifyShopReply([shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/1', 'Other App', 'gid://shopify/Location/1'), shopifyFulfilmentServiceNode('gid://shopify/FulfillmentService/700', $serviceName, 'gid://shopify/Location/1700')])),
        'fulfillmentServiceDelete'  => fn (array $variables) => ShopifyFake::graphql(['fulfillmentServiceDelete' => ['deletedId' => $variables['id'], 'userErrors' => []]]),
        'fulfillmentServiceCreate'  => ShopifyFake::graphql(['fulfillmentServiceCreate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/700', 'serviceName' => $serviceName, 'callbackUrl' => 'x', 'inventoryManagement' => true, 'trackingSupport' => false, 'fulfillmentOrdersOptIn' => true], 'userErrors' => []]]),
        'locationEdit'              => ShopifyFake::graphql(['locationEdit' => ['location' => ['id' => 'gid://shopify/Location/1700', 'name' => $serviceName, 'address' => []], 'userErrors' => []]]),
        'webhookSubscriptionCreate' => fn (array $variables) => ShopifyFake::graphql(['webhookSubscriptionCreate' => ['webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/2', 'topic' => $variables['topic'], 'endpoint' => ['__typename' => 'WebhookHttpEndpoint', 'callbackUrl' => 'x'], 'format' => 'JSON'], 'userErrors' => []]]),
    ]);

    ResetShopifyChannel::run($channel);

    expect(collect(ShopifyFake::calls('fulfillmentServiceDelete'))->pluck('variables.id')->all())->toBe(['gid://shopify/FulfillmentService/501'])
        ->and(ShopifyFake::calls('webhookSubscriptionDelete'))->toHaveCount(1)
        ->and(ShopifyFake::calls('fulfillmentServiceCreate'))->toHaveCount(1)
        ->and(ShopifyFake::calls('webhookSubscriptionCreate'))->toHaveCount(1)
        ->and($shopifyUser->refresh()->shopify_fulfilment_service_id)->toBe('gid://shopify/FulfillmentService/700')
        ->and($channel->refresh()->platform_status)->toBeTrue();
});

test('closing the channel from aiku tears down the store side and a reconnect brings the portfolio back', function () {
    $shopifyUser = shopifyOrderChannel($this, 'close-reconnect');
    $channel     = $shopifyUser->customerSalesChannel;
    $portfolio   = shopifyPortfolioFor($this, $shopifyUser);

    ShopifyFake::fake([
        'webhookSubscriptions'     => ShopifyFake::graphql(['webhookSubscriptions' => ['edges' => []]]),
        'fulfillmentServiceDelete' => fn (array $variables) => ShopifyFake::graphql(['fulfillmentServiceDelete' => ['deletedId' => $variables['id'], 'userErrors' => []]]),
    ]);

    CloseCustomerSalesChannel::make()->handle($channel);
    expect(ShopifyFake::calls('fulfillmentServiceDelete'))->toHaveCount(1)
        ->and($portfolio->refresh()->status)->toBeFalse();

    $again = StoreShopifyUser::make()->handle($shopifyUser->customer, ['name' => 'close-reconnect']);
    expect($again->customer_sales_channel_id)->toBe($channel->id)
        ->and($channel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($portfolio->refresh()->status)->toBeTrue();
});
