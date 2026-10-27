<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateEbayCustomerSalesChannel;
use App\Actions\Dropshipping\Ebay\CallbackRetinaEbayUser;
use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Dropshipping\Ebay\CheckEbayUserAuthorized;
use App\Actions\Dropshipping\Ebay\DeleteEbayUser;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayOrders;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayOrdersOnCustomerActivity;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayUserOrders;
use App\Actions\Dropshipping\Ebay\Orders\FulfillOrderToEbay;
use App\Actions\Dropshipping\Ebay\Product\StoreBulkNewProductToCurrentEbay;
use App\Actions\Dropshipping\Ebay\Product\StoreEbayProduct;
use App\Actions\Dropshipping\Ebay\Product\StoreNewProductToCurrentEbay;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayOffer;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Actions\Dropshipping\Ebay\StoreEbayUser;
use App\Actions\Dropshipping\Ebay\UpdateEbayUserData;
use App\Actions\Dropshipping\Ebay\UpdateReturnPolicyEbayUser;
use App\Actions\Dropshipping\Ebay\UpdateShippingPolicyEbayUser;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Maintenance\Dropshipping\SyncEbayChannelPoliciesFromEbay;
use App\Actions\Retina\Ebay\PublishRetinaEbayPortfolio;
use App\Actions\Retina\Ebay\StoreRetinaNewProductToCurrentEbay;
use App\Events\UploadProductToSalesChannelProgressEvent;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Ordering\Order;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOutboxes;
use App\Actions\Comms\Email\RemindChannelOrdersOnHold;
use App\Actions\Comms\Email\SendChannelOrderOnHoldEmail;
use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user         = createAdminGuest($this->group)->getUser();

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

    $this->gb = Country::where('code', 'GB')->firstOrFail();
    $this->shop->update([
        'settings' => array_merge($this->shop->settings ?? [], [
            'ebay' => [
                'marketplace_id'    => 'EBAY_GB',
                'redirect_key'      => 'aiku-redirect',
                'warehouse_city'    => 'Sheffield',
                'warehouse_state'   => 'South Yorkshire',
                'warehouse_country' => $this->gb->id,
            ]
        ])
    ]);
    $this->shop->refresh();

    $this->customer = createCustomer($this->shop);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->warehouse = createWarehouse();

    $this->strayEbayRequests = [];

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );

    actingAs($this->user);
});

afterEach(function () {
    expect($this->strayEbayRequests)->toBe([]);
});

/**
 * Every eBay call goes through Http, so an unfaked URL is recorded and fails the test in afterEach
 * instead of being swallowed by the try/catch in WithEbayApiRequest::makeEbayRequest.
 * Each call starts a fresh Http factory, so Http::assertSent only sees requests made after the latest call.
 *
 * @param  array<string, array|callable>  $routes  URL substring => response body, or callable(Request): Response
 */
function fakeEbay($ctx, array $routes): void
{
    Http::swap(new Factory(app('events')));

    Http::fake(function (Request $request) use ($ctx, $routes) {
        $url = $request->url();

        foreach ($routes as $needle => $response) {
            if (str_contains($url, $needle)) {
                return is_callable($response) ? $response($request) : Http::response($response);
            }
        }

        $ctx->strayEbayRequests[] = $request->method().' '.$url;

        return Http::response(['errors' => [['message' => 'unfaked']]], 599);
    });
}

function ebayChannel($ctx, array $overrides = []): EbayUser
{
    $customer = StoreCustomer::make()->action($ctx->shop, array_merge(Customer::factory()->definition(), ['email' => 'ebay-'.Str::random(8).'@test.example']));
    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => 'ebay-'.Str::random(6)]);

    $ebayUser->update(array_merge([
        'settings'              => [
            'credentials' => [
                'ebay_access_token'     => 'access-token',
                'ebay_refresh_token'    => 'refresh-token',
                'ebay_token_expires_at' => now()->addHour()->toIso8601String(),
            ]
        ],
        'fulfillment_policy_id' => 'fp-1',
        'payment_policy_id'     => 'pp-1',
        'return_policy_id'      => 'rp-1',
        'location_key'          => 'aw-warehouse-gb',
    ], $overrides));

    $ebayUser->customerSalesChannel->update(['platform_status' => true]);

    return $ebayUser->refresh();
}

function listedEbayPortfolio($ctx, EbayUser $ebayUser, string $listingId = '123456789012', string $offerId = 'offer-1'): Portfolio
{
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $ctx->product, []);
    $portfolio->update([
        'platform_product_id'         => $offerId,
        'platform_product_variant_id' => $listingId,
        'platform_status'             => true,
    ]);

    return $portfolio->refresh();
}

function ebayOrder(array $overrides = []): array
{
    return array_replace_recursive([
        'orderId'                      => '12-'.random_int(10000, 99999).'-'.random_int(10000, 99999),
        'orderFulfillmentStatus'       => 'NOT_STARTED',
        'creationDate'                 => '2026-09-10T05:02:11.000Z',
        'cancelStatus'                 => ['cancelState' => 'NONE_REQUESTED'],
        'buyer'                        => ['username' => 'jane_buyer'],
        'fulfillmentStartInstructions' => [
            [
                'shippingStep' => [
                    'shipTo' => [
                        'fullName'       => 'Jane Buyer',
                        'email'          => 'jane@members.ebay.com',
                        'primaryPhone'   => ['phoneNumber' => '07700900000'],
                        'contactAddress' => [
                            'addressLine1'    => '1 High Street',
                            'addressLine2'    => 'Flat 2',
                            'city'            => 'Sheffield',
                            'stateOrProvince' => 'South Yorkshire',
                            'postalCode'      => 'S1 1AA',
                            'countryCode'     => 'GB',
                        ]
                    ]
                ]
            ]
        ],
        'lineItems'                    => [
            [
                'lineItemId'   => '1000000001',
                'legacyItemId' => '123456789012',
                'sku'          => 'abc-1',
                'title'        => 'Listed On Ebay',
                'quantity'     => 2,
            ]
        ],
    ], $overrides);
}

test('fetching orders creates the aiku order, its client and the address from the eBay shipTo block', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'orderfulfillmentstatus'));

    $order = Order::where('platform_order_id', $ebayOrder['orderId'])->firstOrFail();

    expect($order->customer_id)->toBe($ebayUser->customer_id)
        ->and($order->customer_sales_channel_id)->toBe($ebayUser->customer_sales_channel_id)
        ->and($order->customer_reference)->toBe($ebayOrder['orderId'])
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and(Arr::get($order->data, 'ebay_order.orderId'))->toBe($ebayOrder['orderId'])
        ->and($order->platform_order_created_at->toIso8601String())->toBe('2026-09-10T05:02:11+00:00')
        ->and($order->transactions()->count())->toBe(1);

    $transaction = $order->transactions()->first();
    expect((int) $transaction->quantity_ordered)->toBe(2)
        ->and($transaction->platform_transaction_id)->toBe('1000000001')
        ->and($transaction->model_id)->toBe($portfolio->item_id);

    $client = $order->customerClient;
    expect($client->email)->toBe('jane@members.ebay.com')
        ->and($client->contact_name)->toBe('Jane Buyer')
        ->and($client->phone)->toBe('07700900000')
        ->and($client->customer_sales_channel_id)->toBe($ebayUser->customer_sales_channel_id);

    expect($order->deliveryAddress->address_line_1)->toBe('1 High Street')
        ->and($order->deliveryAddress->address_line_2)->toBe('Flat 2')
        ->and($order->deliveryAddress->postal_code)->toBe('S1 1AA')
        ->and($order->deliveryAddress->locality)->toBe('Sheffield')
        ->and($order->deliveryAddress->country_id)->toBe($this->gb->id);

    expect($ebayUser->debugWebhooks()->count())->toBe(1);
});

test('fetching the same eBay order twice does not create a second aiku order', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);

    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);
    FetchEbayUserOrders::run($ebayUser);

    expect(Order::where('platform_order_id', $ebayOrder['orderId'])->count())->toBe(1)
        ->and($ebayUser->customer->clients()->count())->toBe(1)
        ->and($ebayUser->debugWebhooks()->count())->toBe(1);
});

test('customer activity on the website fetches their eBay orders at most once every ten minutes', function () {
    Queue::fake();

    $ebayUser = ebayChannel($this);
    $fetches  = fn ($action, array $parameters) => $parameters[0]->id === $ebayUser->id;

    FetchEbayOrdersOnCustomerActivity::run($ebayUser->customer);

    FetchEbayUserOrders::assertPushed(1, $fetches);

    Queue::pushed(\Lorisleiva\Actions\Decorators\UniqueJobDecorator::class)
        ->each(fn ($job) => (new \Illuminate\Bus\UniqueLock(Cache::driver()))->release($job));

    FetchEbayOrdersOnCustomerActivity::run($ebayUser->customer);

    FetchEbayUserOrders::assertPushed(1, $fetches);
});

test('channels with recent orders or newly connected are fetched apart from the rest', function () {
    Queue::fake();

    $recentOrders = ebayChannel($this);
    $recentOrders->customerSalesChannel->update(['created_at' => now()->subYear(), 'last_order_created_at' => now()->subDays(3)]);

    $newlyConnected = ebayChannel($this);

    $quiet = ebayChannel($this);
    $quiet->customerSalesChannel->update(['created_at' => now()->subYear(), 'last_order_created_at' => now()->subDays(60)]);

    $neverOrdered = ebayChannel($this);
    $neverOrdered->customerSalesChannel->update(['created_at' => now()->subYear(), 'last_order_created_at' => null]);

    $fetches = fn (EbayUser $ebayUser) => fn ($action, array $parameters) => $parameters[0]->id === $ebayUser->id;

    FetchEbayOrders::run(true);

    FetchEbayUserOrders::assertPushed($fetches($recentOrders));
    FetchEbayUserOrders::assertPushed($fetches($newlyConnected));
    FetchEbayUserOrders::assertNotPushed($fetches($quiet));
    FetchEbayUserOrders::assertNotPushed($fetches($neverOrdered));

    Queue::fake();
    FetchEbayOrders::run();

    FetchEbayUserOrders::assertPushed($fetches($quiet));
    FetchEbayUserOrders::assertPushed($fetches($neverOrdered));
    FetchEbayUserOrders::assertNotPushed($fetches($recentOrders));
    FetchEbayUserOrders::assertNotPushed($fetches($newlyConnected));
});

test('a second order from the same buyer reuses the customer client', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);

    $first  = ebayOrder();
    $second = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$first, $second], 'total' => 2],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    $orders = Order::whereIn('platform_order_id', [$first['orderId'], $second['orderId']])->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('customer_client_id')->unique())->toHaveCount(1)
        ->and($ebayUser->customer->clients()->count())->toBe(1);
});

test('cancelled eBay orders are never imported nor logged', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);

    $ebayOrder = ebayOrder(['cancelStatus' => ['cancelState' => 'CANCELED']]);
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    expect(Order::where('platform_order_id', $ebayOrder['orderId'])->exists())->toBeFalse()
        ->and($ebayUser->debugWebhooks()->count())->toBe(0);
});

test('an eBay order whose listing is not in the portfolio is skipped', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser, listingId: '999999999999');

    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    expect(Order::where('platform_order_id', $ebayOrder['orderId'])->exists())->toBeFalse()
        ->and($ebayUser->customer->clients()->count())->toBe(0);
});

test('an eBay order mixing a known and an unknown listing imports only the known line', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);

    $order = ebayOrder();
    $order['lineItems'][] = [
        'lineItemId'   => '1000000002',
        'legacyItemId' => '888888888888',
        'sku'          => 'not-ours',
        'title'        => 'Someone else product',
        'quantity'     => 1,
    ];

    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$order], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    $aikuOrder = Order::where('platform_order_id', $order['orderId'])->firstOrFail();

    expect($aikuOrder->transactions()->count())->toBe(1)
        ->and($aikuOrder->transactions()->first()->platform_transaction_id)->toBe('1000000001');
});

test('a multi-variation eBay listing sends each line to the product of its own sku, and an ambiguous line is kept aside', function () {
    $ebayUser       = ebayChannel($this);
    $firstVariation = listedEbayPortfolio($this, $ebayUser);
    $otherProduct   = secondProduct($this);
    $otherVariation = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $otherProduct, []);
    $otherVariation->update(['platform_product_variant_id' => '123456789012', 'platform_status' => true, 'sku' => 'amethyst-1kg']);
    $firstVariation->update(['sku' => 'rose-quartz-1kg']);

    $order              = ebayOrder();
    $order['lineItems'] = [
        ['lineItemId' => '1000000001', 'legacyItemId' => '123456789012', 'sku' => 'amethyst-1kg', 'title' => 'Amethyst', 'quantity' => 1],
        ['lineItemId' => '1000000002', 'legacyItemId' => '123456789012', 'sku' => 'rose-quartz-1kg', 'title' => 'Rose quartz', 'quantity' => 3],
        ['lineItemId' => '1000000003', 'legacyItemId' => '123456789012', 'sku' => 'renamed-on-ebay', 'title' => 'Jasper', 'quantity' => 1],
    ];

    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$order], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    $aikuOrder    = Order::where('platform_order_id', $order['orderId'])->firstOrFail();
    $transactions = $aikuOrder->transactions()->get()->keyBy('platform_transaction_id');

    expect($transactions)->toHaveCount(2)
        ->and($transactions['1000000001']->model_id)->toBe($otherProduct->id)
        ->and($transactions['1000000002']->model_id)->toBe($this->product->id)
        ->and(Arr::pluck($aikuOrder->data['ebay_unmatched_line_items'], 'lineItemId'))->toBe(['1000000003']);
});

test('an eBay listing id left on an old unlisted portfolio still imports to the live one', function () {
    $ebayUser     = ebayChannel($this);
    $otherProduct = secondProduct($this);
    StorePortfolio::make()->action($ebayUser->customerSalesChannel, $otherProduct, [])
        ->update(['platform_product_variant_id' => '123456789012', 'platform_status' => false, 'sku' => 'old-listing-sku']);
    listedEbayPortfolio($this, $ebayUser);

    $order = ebayOrder(['lineItems' => [['sku' => 'not-a-portfolio-sku']]]);
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$order], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    $aikuOrder = Order::where('platform_order_id', $order['orderId'])->firstOrFail();

    expect($aikuOrder->transactions()->first()->model_id)->toBe($this->product->id)
        ->and(Arr::has($aikuOrder->data, 'ebay_unmatched_line_items'))->toBeFalse();
});

test('an eBay line whose sku sits on two portfolios goes to the one on its own listing', function () {
    $ebayUser       = ebayChannel($this);
    $otherProduct   = secondProduct($this);
    $listed         = listedEbayPortfolio($this, $ebayUser);
    $listed->update(['sku' => 'hbaths-04']);
    StorePortfolio::make()->action($ebayUser->customerSalesChannel, $otherProduct, [])->update(['sku' => 'hbaths-04']);

    $order = ebayOrder(['lineItems' => [['sku' => 'hbaths-04']]]);
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$order], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    expect(Order::where('platform_order_id', $order['orderId'])->firstOrFail()->transactions()->first()->model_id)->toBe($this->product->id);
});

test('an eBay orders page that answers with an error imports nothing and does not throw', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);

    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => fn () => Http::response(['errors' => [['errorId' => 30500, 'message' => 'System error']]], 500),
    ]);

    FetchEbayUserOrders::run($ebayUser);

    expect($ebayUser->customer->orders()->count())->toBe(0)
        ->and($ebayUser->debugWebhooks()->count())->toBe(0);
});

function dispatchedEbayOrder($ctx, EbayUser $ebayUser, bool $withShipment = true, string $shipperName = 'Royal Mail', string $tradeAs = 'Royal Mail'): Order
{
    $ebayOrder = ebayOrder();
    fakeEbay($ctx, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);
    FetchEbayUserOrders::run($ebayUser);

    $order = Order::where('platform_order_id', $ebayOrder['orderId'])->firstOrFail();
    $order->transactions()->update(['quantity_dispatched' => 2]);

    $deliveryNote = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN'.Str::random(6),
        'state'            => DeliveryNoteStateEnum::DISPATCHED,
        'email'            => 'jane@members.ebay.com',
        'phone'            => '07700900000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $ctx->warehouse->id,
    ]);

    if ($withShipment) {
        $shipper = StoreShipper::make()->action($ctx->organisation, ['code' => 'RM'.Str::random(4), 'name' => $shipperName, 'trade_as' => $tradeAs]);
        StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'RM123456789GB']);
    }

    return $order->refresh();
}

test('dispatching an eBay order uploads the tracking number and dispatched line quantities', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser);

    fakeEbay($this, [
        '/shipping_fulfillment' => fn () => Http::response('', 201),
    ]);

    FulfillOrderToEbay::run($order);

    Http::assertSent(function (Request $request) use ($order) {
        if (!str_contains($request->url(), '/sell/fulfillment/v1/order/'.$order->platform_order_id.'/shipping_fulfillment')) {
            return false;
        }

        $body = $request->data();

        return $request->method() === 'POST'
            && $body['lineItems'] === [['lineItemId' => '1000000001', 'quantity' => 2]]
            && $body['trackingNumber'] === 'RM123456789GB'
            && $body['shippingCarrierCode'] === 'RoyalMail'
            && filled($body['shippedDate']);
    });
});

test('the eBay carrier code comes from the carrier the parcel travels with, not the shipper integration name', function (string $shipperName, string $tradeAs, string $carrierCode) {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser, shipperName: $shipperName, tradeAs: $tradeAs);

    fakeEbay($this, [
        '/shipping_fulfillment' => fn () => Http::response('', 201),
    ]);

    FulfillOrderToEbay::run($order);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/shipping_fulfillment')
        && $request->data()['shippingCarrierCode'] === $carrierCode);
})->with([
    'Royal Mail through ITD' => ['ITD (api)', 'Royal Mail', 'RoyalMail'],
    'APC overnight'          => ['APC (API)', 'APC overnight', 'APC'],
    'GLS'                    => ['GLS General Logistics Systems Slovakia s.r.o.', 'GLS', 'GLS'],
    'carrier eBay has no code for' => ['Paketa', 'Packeta', 'Packeta'],
]);

test('an eBay order dispatched without a shipment is fulfilled without tracking instead of crashing the dispatch', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser, withShipment: false);

    fakeEbay($this, [
        '/shipping_fulfillment' => fn () => Http::response('', 201),
    ]);

    FulfillOrderToEbay::run($order);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return str_contains($request->url(), '/shipping_fulfillment')
            && $body['lineItems'] === [['lineItemId' => '1000000001', 'quantity' => 2]]
            && !array_key_exists('trackingNumber', $body)
            && !array_key_exists('shippingCarrierCode', $body);
    });
});

test('an eBay order on a channel that lost its platform status is not pushed back to eBay', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser);

    $ebayUser->customerSalesChannel->update(['platform_status' => false]);

    Http::fake();

    expect(FulfillOrderToEbay::run($order->refresh()))->toBe([]);

    Http::assertNothingSent();
});

test('an eBay fulfilment rejected by eBay hands the error back to the caller', function () {
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser);

    fakeEbay($this, [
        '/shipping_fulfillment' => fn () => Http::response(['errors' => [['errorId' => 32100, 'message' => 'Invalid line item']]], 400),
    ]);

    $result = FulfillOrderToEbay::run($order);

    expect(Arr::get($result, 'errors.0.errorId'))->toBe(32100);
});

test('an eBay fulfilment eBay rejects is tried again later, and given up after the last attempt', function () {
    Queue::fake();
    $ebayUser = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $order = dispatchedEbayOrder($this, $ebayUser);

    fakeEbay($this, [
        '/shipping_fulfillment' => fn () => Http::response(['errors' => [['errorId' => 30500, 'message' => 'System error.']]], 500),
    ]);

    FulfillOrderToEbay::run($order);

    Queue::assertPushedOn('ebay', JobDecorator::class, fn (JobDecorator $job) => $job->getAction() instanceof FulfillOrderToEbay
        && $job->getParameters()[0]->is($order)
        && $job->getParameters()[1] === 2);

    Queue::fake();

    FulfillOrderToEbay::run($order, 4);

    Queue::assertNothingPushed();
});

/**
 * The offer resource answers three ways: GET /offer?sku= lists offers for a SKU, POST /offer creates one,
 * GET|PUT /offer/{offerId} reads or replaces one. A single callable keeps them apart by method and path.
 *
 * @param  array<int, array>  $offersForSku  what GET /offer?sku= returns, empty means eBay's 404 "not available"
 */
function ebayOfferRoutes(array $offersForSku = [], string $createdOfferId = 'offer-new', array $offerById = []): Closure
{
    return function (Request $request) use ($offersForSku, $createdOfferId, $offerById) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/sell/inventory/v1/offer' && $request->method() === 'POST') {
            return Http::response(['offerId' => $createdOfferId], 201);
        }

        if ($path === '/sell/inventory/v1/offer') {
            return $offersForSku
                ? Http::response(['offers' => $offersForSku, 'total' => count($offersForSku)])
                : Http::response(['errors' => [['errorId' => 25713, 'message' => 'This Offer is not available.']]], 404);
        }

        $offerId = basename($path);

        if ($request->method() === 'PUT') {
            return Http::response(['offerId' => $offerId], 200);
        }

        return Http::response(array_merge([
            'offerId'           => $offerId,
            'sku'               => 'sku-on-ebay',
            'status'            => 'PUBLISHED',
            'format'            => 'FIXED_PRICE',
            'availableQuantity' => 3,
            'categoryId'        => '31413',
            'listingDescription' => 'Old description on eBay',
            'marketplaceId'     => 'EBAY_GB',
            'merchantLocationKey' => 'aw-warehouse-gb',
            'pricingSummary'    => ['price' => ['value' => '9.99', 'currency' => 'GBP']],
            'listingPolicies'   => ['fulfillmentPolicyId' => 'fp-1', 'paymentPolicyId' => 'pp-1', 'returnPolicyId' => 'rp-1'],
            'listing'           => ['listingId' => '555000111', 'listingStatus' => 'ACTIVE'],
        ], $offerById));
    };
}

function ebayCatalogueRoutes(array $suggestion = ['categoryId' => '31413', 'categoryName' => 'Candles'], array $acceptedConditions = ['1000']): array
{
    return [
        '/get_category_suggestions'    => $suggestion
            ? ['categorySuggestions' => [['category' => $suggestion, 'categoryTreeNodeLevel' => 3, 'relevancy' => '90']], 'categoryTreeId' => '3']
            : ['categorySuggestions' => []],
        '/get_item_condition_policies' => [
            'itemConditionPolicies' => [[
                'categoryId'     => '31413',
                'itemConditions' => array_map(fn ($id) => ['conditionId' => $id, 'conditionDescription' => $id === '1000' ? 'New' : 'Used'], $acceptedConditions)
            ]]
        ],
        '/get_item_aspects_for_category' => [
            'aspects' => [[
                'localizedAspectName' => 'Type',
                'aspectConstraint'    => ['aspectRequired' => true, 'aspectDataType' => 'STRING', 'aspectMode' => 'SELECTION_ONLY', 'itemToAspectCardinality' => 'SINGLE'],
                'aspectValues'        => [['localizedValue' => 'Pillar']]
            ]]
        ],
        '/buy/browse/v1/item_summary/search' => ['itemSummaries' => [], 'total' => 0],
        '/inventory_item/' => fn (Request $request) => $request->method() === 'PUT'
            ? Http::response('', 204)
            : Http::response(['sku' => basename(parse_url($request->url(), PHP_URL_PATH)), 'product' => ['title' => 'Listed On Ebay', 'imageUrls' => ['https://i.ebayimg.com/x.jpg']]]),
        '/publish' => ['listingId' => '555000111'],
    ];
}

function sentEbayRequest(string $method, string $pathNeedle): ?Request
{
    $sent = null;
    Http::assertSent(function (Request $request) use ($method, $pathNeedle, &$sent) {
        $path    = parse_url($request->url(), PHP_URL_PATH);
        $matches = $path === $pathNeedle || (str_ends_with($pathNeedle, '/') && str_contains($path, $pathNeedle));

        if ($request->method() === $method && $matches) {
            $sent ??= $request;

            return true;
        }

        return false;
    });

    return $sent;
}

function secondProduct($ctx): Product
{
    $family = $ctx->shop->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->first();

    return StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $ctx->product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 50,
        ]
    ));
}

function lastPortfolioLog(Portfolio $portfolio): ?PlatformPortfolioLogs
{
    return PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->orderByDesc('id')->first();
}

test('uploading a product creates the inventory item, the offer and publishes it', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['customer_price' => 12.5]);
    $this->product->update(['available_quantity' => 7, 'units' => 3, 'marketing_weight' => 430, 'gross_weight' => 1371]);

    fakeEbay($this, ebayCatalogueRoutes() + ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    expect($portfolio->platform_product_id)->toBe('offer-new')
        ->and($portfolio->platform_product_variant_id)->toBe('555000111')
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->upload_warning)->toBeNull()
        ->and(Arr::get($portfolio->data, 'is_platform_draft'))->toBeFalse()
        ->and(Arr::get($portfolio->data, 'product.category'))->toBe(['id' => '31413', 'name' => 'Candles'])
        ->and(Arr::get($portfolio->data, 'ebay_product.name'))->toBe('Listed On Ebay')
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::OK)
        ->and(lastPortfolioLog($portfolio)->type)->toBe(PlatformPortfolioLogsTypeEnum::UPLOAD);

    $inventoryItem = sentEbayRequest('PUT', '/sell/inventory/v1/inventory_item/'.$portfolio->sku)->data();
    expect($inventoryItem['condition'])->toBe('NEW')
        ->and($inventoryItem['availability']['shipToLocationAvailability']['quantity'])->toBe(7)
        ->and($inventoryItem['availability']['shipToLocationAvailability']['availabilityDistributions'][0]['merchantLocationKey'])->toBe('aw-warehouse-gb')
        ->and($inventoryItem['product']['title'])->toBe($portfolio->customer_product_name)
        ->and($inventoryItem['product']['mpn'])->toBe($this->product->code)
        ->and($inventoryItem['product']['aspects']['Type'])->toBe(['Pillar'])
        ->and($inventoryItem['packageWeightAndSize']['weight'])->toBe(['unit' => 'KILOGRAM', 'value' => 1.371]);

    $offer = sentEbayRequest('POST', '/sell/inventory/v1/offer')->data();
    expect($offer['sku'])->toBe($portfolio->sku)
        ->and($offer['marketplaceId'])->toBe('EBAY_GB')
        ->and($offer['format'])->toBe('FIXED_PRICE')
        ->and($offer['availableQuantity'])->toBe(7)
        ->and($offer['pricingSummary']['price'])->toBe(['value' => 12.5, 'currency' => 'GBP'])
        ->and($offer['listingPolicies'])->toBe(['fulfillmentPolicyId' => 'fp-1', 'paymentPolicyId' => 'pp-1', 'returnPolicyId' => 'rp-1'])
        ->and($offer['categoryId'])->toBe('31413')
        ->and($offer['merchantLocationKey'])->toBe('aw-warehouse-gb');

    sentEbayRequest('POST', '/sell/inventory/v1/offer/offer-new/publish');
});

test('uploading as draft stores the offer id without publishing', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['upload_as_draft' => true]]);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, ebayCatalogueRoutes() + ['/sell/inventory/v1/offer' => ebayOfferRoutes(createdOfferId: 'offer-draft')]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/publish'));

    expect($portfolio->platform_product_id)->toBe('offer-draft')
        ->and($portfolio->platform_product_variant_id)->toBeNull()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->has_valid_platform_product_id)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and(Arr::get($portfolio->data, 'is_platform_draft'))->toBeTrue()
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::OK);
});

test('uploading caps the advertised quantity at the channel maximum', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['max_quantity_advertise' => 50]);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $this->product->update(['available_quantity' => 500]);

    fakeEbay($this, ebayCatalogueRoutes() + ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    StoreEbayProduct::run($ebayUser, $portfolio);

    expect(sentEbayRequest('PUT', '/inventory_item/')->data()['availability']['shipToLocationAvailability']['quantity'])->toBe(50)
        ->and(sentEbayRequest('POST', '/sell/inventory/v1/offer')->data()['availableQuantity'])->toBe(50);
});

test('an "Other" suggestion and a book category from the browse search both fall back to the safe category', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, [
        '/buy/browse/v1/item_summary/search' => ['itemSummaries' => [['itemId' => 'v1|1|0', 'categories' => [['categoryId' => '261186', 'categoryName' => 'Books']]]]],
    ] + ebayCatalogueRoutes(suggestion: ['categoryId' => '99', 'categoryName' => 'Other']) + ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    expect(sentEbayRequest('POST', '/sell/inventory/v1/offer')->data()['categoryId'])->toBe('29511')
        ->and(Arr::get($portfolio->data, 'product.category'))->toBe(['id' => '29511', 'name' => null])
        ->and($portfolio->platform_status)->toBeTrue();
});

test('a suggested category that does not accept new items is skipped in favour of the safe category', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, [
        '/get_item_condition_policies' => function (Request $request) {
            $isSuggested = str_contains(urldecode($request->url()), 'categoryIds:{12345}');

            return Http::response(['itemConditionPolicies' => [[
                'categoryId'     => $isSuggested ? '12345' : '29511',
                'itemConditions' => [['conditionId' => $isSuggested ? '3000' : '1000', 'conditionDescription' => $isSuggested ? 'Used' : 'New']]
            ]]]);
        },
    ] + ebayCatalogueRoutes(suggestion: ['categoryId' => '12345', 'categoryName' => 'Antiques']) + ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    StoreEbayProduct::run($ebayUser, $portfolio);

    expect(sentEbayRequest('POST', '/sell/inventory/v1/offer')->data()['categoryId'])->toBe('29511');
});

test('an offer rejected by eBay keeps the field it names and the message on the portfolio', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    $rejection = ['errors' => [[
        'errorId'    => 25002,
        'domain'     => 'API_INVENTORY',
        'category'   => 'REQUEST',
        'message'    => 'A user error has occurred. Invalid value for title.',
        'parameters' => [['name' => 'title', 'value' => '']]
    ]]];

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/sell/inventory/v1/offer' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response($rejection, 400)
            : ebayOfferRoutes()($request),
    ]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/publish'));

    expect($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->upload_warning)->toBe('A user error has occurred. Invalid value for title.')
        ->and($portfolio->errors_response)->toBe(['params' => 'title', 'message' => 'A user error has occurred. Invalid value for title.'])
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL)
        ->and(lastPortfolioLog($portfolio)->response)->toContain('Invalid value for title');
});

test('uploading to a channel that lost a listing policy stops before any offer is created', function () {
    $ebayUser  = ebayChannel($this, ['return_policy_id' => null]);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, ebayCatalogueRoutes() + ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/offer'));

    expect($portfolio->upload_warning)->toContain('return policy')
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);
});

test('uploading a sku that already has an offer on eBay replaces that offer instead of creating another', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['customer_price' => 20]);

    $existing = ['offerId' => 'offer-existing', 'sku' => $portfolio->sku, 'status' => 'UNPUBLISHED', 'availableQuantity' => 1, 'categoryId' => '31413', 'format' => 'FIXED_PRICE'];

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(offersForSku: [$existing], offerById: $existing),
    ]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/offer'));

    $replaced = sentEbayRequest('PUT', '/sell/inventory/v1/offer/offer-existing')->data();

    expect((float) $replaced['pricingSummary']['price']['value'])->toBe(20.0)
        ->and($replaced['pricingSummary']['price']['currency'])->toBe('GBP')
        ->and($replaced['categoryId'])->toBe('31413')
        ->and($replaced['listingPolicies']['returnPolicyId'])->toBe('rp-1')
        ->and($portfolio->platform_product_id)->toBe('offer-existing');

    sentEbayRequest('POST', '/sell/inventory/v1/offer/offer-existing/publish');
});

test('re-uploading a live listing that does not follow our price keeps the seller price and category on eBay', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['customer_price' => 20, 'settings' => ['pricing' => ['type' => 'not_follow', 'value' => null], 'pricing_opt_out' => true]]);

    $existing = ['offerId' => 'offer-live', 'sku' => $portfolio->sku, 'status' => 'PUBLISHED', 'categoryId' => '77777', 'format' => 'FIXED_PRICE', 'pricingSummary' => ['price' => ['value' => '9.99', 'currency' => 'GBP']]];

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(offersForSku: [$existing], offerById: $existing),
    ]);

    StoreEbayProduct::run($ebayUser, $portfolio->refresh());

    $replaced = sentEbayRequest('PUT', '/sell/inventory/v1/offer/offer-live')->data();

    expect($replaced['pricingSummary']['price']['value'])->toBe('9.99')
        ->and($replaced['categoryId'])->toBe('77777');
});

test('re-uploading a sku whose old offer on eBay has no price sends our price even when the channel does not update prices', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['do_not_update_prices' => true]]);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['customer_price' => 62.99]);

    $leftover = [
        'offerId'        => 'offer-leftover',
        'sku'            => $portfolio->sku,
        'status'         => 'UNPUBLISHED',
        'format'         => 'FIXED_PRICE',
        'pricingSummary' => [],
        'listing'        => ['listingId' => '287179414510', 'listingStatus' => 'ENDED'],
    ];

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(offersForSku: [$leftover], offerById: $leftover),
    ]);

    StoreEbayProduct::run($ebayUser, $portfolio->refresh());

    $replaced = sentEbayRequest('PUT', '/sell/inventory/v1/offer/offer-leftover')->data();

    expect((float) $replaced['pricingSummary']['price']['value'])->toBe(62.99)
        ->and($replaced['pricingSummary']['price']['currency'])->toBe('GBP');
});

test('uploading a sku whose offer on eBay points at an old postage policy moves the offer onto the channel policies', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    $existing = [
        'offerId'             => 'offer-existing',
        'sku'                 => $portfolio->sku,
        'status'              => 'UNPUBLISHED',
        'merchantLocationKey' => 'old-location',
        'listingPolicies'     => ['fulfillmentPolicyId' => 'fp-deleted', 'paymentPolicyId' => 'pp-1', 'returnPolicyId' => 'rp-1'],
    ];

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(offersForSku: [$existing], offerById: $existing),
    ]);

    StoreEbayProduct::run($ebayUser, $portfolio);

    $replaced = sentEbayRequest('PUT', '/sell/inventory/v1/offer/offer-existing')->data();

    expect($replaced['listingPolicies'])->toBe(['fulfillmentPolicyId' => 'fp-1', 'paymentPolicyId' => 'pp-1', 'returnPolicyId' => 'rp-1'])
        ->and($replaced['merchantLocationKey'])->toBe('aw-warehouse-gb');
});

function fulfilmentPolicyRefusal(): array
{
    return ['errors' => [[
        'errorId'  => 25007,
        'domain'   => 'API_INVENTORY',
        'category' => 'REQUEST',
        'message'  => 'The eBay listing associated with the inventory item, or the unpublished offer, has invalid data in the associated fulfilment policy. Please add at least one valid postage service option to your listing.',
    ]]];
}

function publishRefusedOnceForPolicy(int &$publishCalls): Closure
{
    return function () use (&$publishCalls) {
        $publishCalls++;

        return $publishCalls === 1
            ? Http::response(fulfilmentPolicyRefusal(), 400)
            : Http::response(['listingId' => '555000111']);
    };
}

test('a publish refused for a postage policy the seller deleted swaps in a usable policy and publishes', function () {
    $ebayUser     = ebayChannel($this, ['fulfillment_policy_id' => 'fp-deleted']);
    $portfolio    = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $publishCalls = 0;

    fakeEbay($this, ['/publish' => publishRefusedOnceForPolicy($publishCalls)] + ebayCatalogueRoutes() + Arr::only(ebayAccountRoutes(), ['/sell/account/v1/fulfillment_policy']) + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
    ]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    expect($ebayUser->refresh()->fulfillment_policy_id)->toBe('fp-1')
        ->and(sentEbayRequest('PUT', '/sell/inventory/v1/offer/offer-new')->data()['listingPolicies']['fulfillmentPolicyId'])->toBe('fp-1')
        ->and($publishCalls)->toBe(2)
        ->and($portfolio->platform_product_variant_id)->toBe('555000111')
        ->and($portfolio->upload_warning)->toBeNull();
});

test('a publish refused for a postage policy when the seller has none usable left recreates it from the channel postage settings', function () {
    $ebayUser = ebayChannel($this, ['fulfillment_policy_id' => 'fp-deleted']);
    $ebayUser->update(['settings' => array_merge($ebayUser->settings, ['shipping' => ['price' => '2.50', 'max_dispatch_time' => 3]])]);
    $portfolio    = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $publishCalls = 0;

    fakeEbay($this, ['/publish' => publishRefusedOnceForPolicy($publishCalls)] + ebayCatalogueRoutes() + [
        '/sell/account/v1/fulfillment_policy' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['fulfillmentPolicies' => [], 'total' => 0])
            : Http::response(['fulfillmentPolicyId' => 'fp-recreated'], 201),
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
    ]);

    StoreEbayProduct::run($ebayUser, $portfolio);

    $created = sentEbayRequest('POST', '/sell/account/v1/fulfillment_policy')->data();

    expect($ebayUser->refresh()->fulfillment_policy_id)->toBe('fp-recreated')
        ->and($created['shippingOptions'][0]['shippingServices'][0]['shippingCost']['value'])->toBe('2.50')
        ->and($created['handlingTime']['value'])->toBe(3)
        ->and($publishCalls)->toBe(2);
});

test('a publish refused for a postage policy eBay still lists as usable is left for the seller to fix', function () {
    $ebayUser     = ebayChannel($this);
    $portfolio    = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $publishCalls = 0;

    fakeEbay($this, ['/publish' => publishRefusedOnceForPolicy($publishCalls)] + ebayCatalogueRoutes() + Arr::only(ebayAccountRoutes(), ['/sell/account/v1/fulfillment_policy']) + [
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
    ]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/fulfillment_policy'));

    expect($ebayUser->refresh()->fulfillment_policy_id)->toBe('fp-1')
        ->and($publishCalls)->toBe(1)
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->upload_warning)->toContain('valid postage service');
});

test('uploading with a token eBay no longer accepts shows the authorisation error and never publishes', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/identity/v1/oauth2/token' => fn () => Http::response(['error' => 'invalid_grant', 'error_description' => 'the provided authorization refresh token is invalid'], 400),
        '/sell/inventory/v1/offer'  => fn () => Http::response(['errors' => [['errorId' => 1001, 'domain' => 'OAuth', 'category' => 'REQUEST', 'message' => 'Invalid access token']]], 401),
    ]);

    $portfolio = StoreEbayProduct::run($ebayUser, $portfolio)->refresh();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/publish'));

    expect($portfolio->upload_warning)->toBe('Invalid access token')
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);
});

test('bulk upload queues one upload job per selected active portfolio and counts progress', function () {
    Queue::fake();

    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    $inactive  = StorePortfolio::make()->action($ebayUser->customerSalesChannel, secondProduct($this), []);
    $inactive->update(['status' => false]);

    StoreBulkNewProductToCurrentEbay::run($ebayUser, ['portfolios' => [$portfolio->id, $inactive->id]]);

    StoreNewProductToCurrentEbay::assertPushed(1);
    StoreNewProductToCurrentEbay::assertPushedOn('ebay-upload', function ($action, $arguments, $job) use ($portfolio) {
        return $arguments[1]->id === $portfolio->id && $arguments[2]['total'] === 1 && $job->deleteWhenMissingModels === true;
    });
});

function ebayInventoryItemRoute(string $title = 'Listed On Ebay'): Closure
{
    return fn (Request $request) => $request->method() === 'PUT'
        ? Http::response(null, 204)
        : Http::response([
            'sku'          => 'sku-on-ebay',
            'condition'    => 'NEW',
            'availability' => ['shipToLocationAvailability' => ['quantity' => 3]],
            'product'      => ['title' => $title, 'description' => 'Old description on eBay', 'imageUrls' => ['https://img.example/1.jpg']],
        ]);
}

test('a price update sends only the price through the bulk endpoint and never a description, even to offers eBay holds without one', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    foreach (['Our own description', null] as $customerDescription) {
        $portfolio->update(['customer_price' => 14.25, 'customer_description' => $customerDescription]);

        fakeEbay($this, [
            '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
            '/sell/inventory/v1/offer'    => ebayOfferRoutes(offerById: ['listingDescription' => null]),
        ]);

        expect(UpdateEbayOffer::run($portfolio->refresh()))->toBeNull();

        expect(sentEbayRequest('POST', '/sell/inventory/v1/bulk_update_price_quantity')->data())->toBe(['requests' => [[
            'sku'    => 'sku-on-ebay',
            'offers' => [['offerId' => 'offer-1', 'price' => ['value' => '14.25', 'currency' => 'GBP']]],
        ]]])
            ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::OK);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT');
    }
});

test('a price update eBay refuses is reported back and logged as failed', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 400, 'errors' => [['errorId' => 25002, 'message' => 'Price is too low']]]]],
        '/sell/inventory/v1/offer'    => ebayOfferRoutes(),
    ]);

    expect(UpdateEbayOffer::run($portfolio->refresh()))->toContain('Price is too low')
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);
});

test('nothing is sent to eBay when the channel or the portfolio opted out of price updates', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['customer_price' => 14.25]);

    $ebayUser->customerSalesChannel->update(['settings' => ['do_not_update_prices' => true]]);
    fakeEbay($this, ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);
    expect(UpdateEbayOffer::run($portfolio->refresh()))->toBeNull();
    Http::assertNothingSent();

    $ebayUser->customerSalesChannel->update(['settings' => ['do_not_update_prices' => false]]);
    $portfolio->update(['settings' => ['pricing' => ['type' => 'not_follow']]]);
    fakeEbay($this, ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);
    expect(UpdateEbayOffer::run($portfolio->refresh()))->toBeNull();
    Http::assertNothingSent();
});

test('a title edited in aiku replaces the eBay title on the inventory item and keeps the rest of the item', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['customer_product_name' => 'My own title for this candle']);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer'    => ebayOfferRoutes(offerById: ['listingDescription' => null]),
        '/inventory_item/'            => ebayInventoryItemRoute(),
    ]);

    expect(UpdateEbayOffer::run($portfolio->refresh(), withTitle: true))->toBeNull();

    $item = sentEbayRequest('PUT', '/sell/inventory/v1/inventory_item/sku-on-ebay')->data();

    expect($item['product']['title'])->toBe('My own title for this candle')
        ->and($item['product']['imageUrls'])->toBe(['https://img.example/1.jpg'])
        ->and($item['condition'])->toBe('NEW')
        ->and($item['availability']['shipToLocationAvailability']['quantity'])->toBe(3)
        ->and($item['product']['description'])->toBe('Old description on eBay')
        ->and($item)->not->toHaveKey('sku')
        ->and(sentEbayRequest('POST', '/sell/inventory/v1/bulk_update_price_quantity'))->not->toBeNull();
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT' && str_contains($request->url(), '/offer/'));
});

test('an eBay refusal of the title is reported back instead of logged as synchronised', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['customer_product_name' => 'Rejected title']);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
        '/inventory_item/'         => fn (Request $request) => $request->method() === 'PUT'
            ? Http::response(['errors' => [['errorId' => 25001, 'message' => 'Title is not allowed']]], 400)
            : ebayInventoryItemRoute()($request),
    ]);

    expect(UpdateEbayOffer::run($portfolio->refresh(), withTitle: true))->toBe('Title is not allowed')
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL)
        ->and(sentEbayRequest('POST', '/sell/inventory/v1/bulk_update_price_quantity'))->not->toBeNull();
});

test('a title that cannot reach eBay is never logged as synchronised', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['customer_product_name' => 'Unreachable title']);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
        '/inventory_item/'         => fn () => throw new ConnectionException('cURL error 28: timed out'),
    ]);
    expect(UpdateEbayOffer::run($portfolio->refresh(), withTitle: true))->toContain('timed out')
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
        '/inventory_item/'         => fn () => Http::response(['sku' => 'sku-on-ebay', 'groupIds' => ['grp-1'], 'product' => ['title' => 'Group child']]),
    ]);
    expect(UpdateEbayOffer::run($portfolio->refresh(), withTitle: true))->toContain('Multi-variation');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT' && str_contains($request->url(), '/inventory_item/'));
});

test('updating an offer is skipped for drafts and for closed channels', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    Http::fake();

    $portfolio->update(['platform_status' => false]);
    UpdateEbayOffer::run($portfolio->refresh());

    $portfolio->update(['platform_status' => true]);
    $ebayUser->customerSalesChannel->update(['status' => CustomerSalesChannelStatusEnum::CLOSED]);
    UpdateEbayOffer::run($portfolio->refresh());

    Http::assertNothingSent();
});

test('publishing a draft records the listing id and switches the portfolio live', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['platform_status' => false, 'platform_product_variant_id' => null, 'data' => ['is_platform_draft' => true]]);

    fakeEbay($this, [
        '/publish'                 => ['listingId' => '777000111'],
        '/sell/inventory/v1/offer' => ebayOfferRoutes(),
        '/inventory_item/'         => ['sku' => 'sku-on-ebay', 'product' => ['title' => 'Listed On Ebay', 'imageUrls' => []]],
    ]);

    $portfolio = PublishRetinaEbayPortfolio::run($portfolio->refresh())->refresh();

    expect($portfolio->platform_product_variant_id)->toBe('777000111')
        ->and($portfolio->platform_status)->toBeTrue()
        ->and(Arr::get($portfolio->data, 'is_platform_draft'))->toBeFalse()
        ->and($portfolio->upload_warning)->toBeNull();
});

test('publishing a draft eBay refuses explains the seller registration problem', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $portfolio->update(['platform_status' => false]);

    fakeEbay($this, [
        '/publish' => fn () => Http::response(['errors' => [['errorId' => 25016, 'category' => 'REQUEST', 'message' => 'To list this item, you need to create a seller account.']]], 400),
    ]);

    $portfolio = PublishRetinaEbayPortfolio::run($portfolio->refresh())->refresh();

    expect($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->upload_warning)->toBe('To list this item, you need to create a seller account.')
        ->and(Arr::get($portfolio->errors_response, 'message'))->toBe('To list this item, you need to create a seller account.');
});

test('publishing a portfolio that never reached eBay explains itself without calling eBay', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    Http::fake();

    $portfolio = PublishRetinaEbayPortfolio::run($portfolio)->refresh();

    Http::assertNothingSent();
    expect($portfolio->upload_warning)->toContain('nothing to publish');
});

test('stock push sends the capped quantity through the bulk endpoint and records it', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['max_quantity_advertise' => 50, 'ban_stock_update_util' => now()->subMinute()]);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $this->product->update(['available_quantity' => 120]);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer'    => ebayOfferRoutes(),
    ]);

    UpdateEbayPortfolio::run($portfolio->id);

    $body = sentEbayRequest('POST', '/sell/inventory/v1/bulk_update_price_quantity')->data();
    expect($body['requests'][0]['sku'])->toBe('sku-on-ebay')
        ->and($body['requests'][0]['shipToLocationAvailability']['quantity'])->toBe(50)
        ->and($body['requests'][0]['offers'][0])->toBe(['offerId' => 'offer-1', 'availableQuantity' => 50]);

    $portfolio->refresh();
    expect($portfolio->last_stock_value)->toBe(50)
        ->and($portfolio->stock_last_updated_at)->not->toBeNull()
        ->and($ebayUser->customerSalesChannel->refresh()->ban_stock_update_util)->toBeNull()
        ->and(lastPortfolioLog($portfolio)->last_stock_value)->toBe(50);
});

test('stock push sends zero once stock falls to the channel threshold', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['stock_threshold' => 5]);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $this->product->update(['available_quantity' => 5]);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => ['responses' => [['statusCode' => 200, 'offerId' => 'offer-1', 'sku' => 'sku-on-ebay']]],
        '/sell/inventory/v1/offer'    => ebayOfferRoutes(),
    ]);

    UpdateEbayPortfolio::run($portfolio->id);

    expect(sentEbayRequest('POST', '/sell/inventory/v1/bulk_update_price_quantity')->data()['requests'][0]['offers'][0]['availableQuantity'])->toBe(0)
        ->and($portfolio->refresh()->last_stock_value)->toBe(0);
});

test('stock push is skipped while the channel is banned and when eBay already holds the quantity', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['ban_stock_update_util' => now()->addMinutes(5)]);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    Http::fake();
    UpdateEbayPortfolio::run($portfolio->id);
    Http::assertNothingSent();

    $ebayUser->customerSalesChannel->update(['ban_stock_update_util' => null]);
    $this->product->update(['available_quantity' => 3]);
    fakeEbay($this, ['/sell/inventory/v1/offer' => ebayOfferRoutes()]);

    UpdateEbayPortfolio::run($portfolio->id);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/bulk_update_price_quantity'));
    expect($portfolio->refresh()->last_stock_value)->toBe(3)
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::OK);
});

test('stock push to an offer eBay no longer knows logs the failure without banning the channel', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $this->product->update(['available_quantity' => 9]);

    fakeEbay($this, [
        '/sell/inventory/v1/offer/offer-1' => fn () => Http::response(['errors' => [['errorId' => 25713, 'message' => 'This Offer is not available.']]], 404),
    ]);

    UpdateEbayPortfolio::run($portfolio->id);

    $portfolio->refresh();
    expect($portfolio->stock_last_fail_updated_at)->not->toBeNull()
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($ebayUser->customerSalesChannel->refresh()->ban_stock_update_util)->toBeNull()
        ->and(lastPortfolioLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL)
        ->and(lastPortfolioLog($portfolio)->response)->toContain('25713');
});

test('stock push to an ended listing marks the portfolio broken', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);
    $this->product->update(['available_quantity' => 9]);

    fakeEbay($this, [
        '/bulk_update_price_quantity' => fn () => Http::response(['responses' => [
            ['statusCode' => 400, 'sku' => 'sku-on-ebay'],
            ['statusCode' => 400, 'sku' => 'sku-on-ebay', 'offerId' => 'offer-1', 'errors' => [['errorId' => 25002, 'message' => 'A user error has occurred. You are not allowed to revise an ended item "555000111".']]],
        ]], 207),
        '/sell/inventory/v1/offer'    => ebayOfferRoutes(),
    ]);

    UpdateEbayPortfolio::run($portfolio->id);

    $portfolio->refresh();
    expect($portfolio->platform_status)->toBeFalse()
        ->and(Arr::get($portfolio->errors_response, '0.errorId'))->toBe(25002)
        ->and($portfolio->stock_last_fail_updated_at)->not->toBeNull();
});

test('stock push bans the channel for a moment when eBay answers with nothing usable', function () {
    $ebayUser  = ebayChannel($this);
    $portfolio = listedEbayPortfolio($this, $ebayUser);

    fakeEbay($this, [
        '/sell/inventory/v1/offer/offer-1' => fn () => Http::response('<html>Bad gateway</html>', 502),
    ]);

    UpdateEbayPortfolio::run($portfolio->id);

    expect($ebayUser->customerSalesChannel->refresh()->ban_stock_update_util)->not->toBeNull()
        ->and($portfolio->refresh()->stock_last_fail_updated_at)->not->toBeNull();
});

function ebayAccountRoutes(array $overrides = []): array
{
    $usableFulfilmentPolicy = fn (string $id) => [
        'fulfillmentPolicyId' => $id,
        'name'                => 'Shipping',
        'marketplaceId'       => 'EBAY_GB',
        'categoryTypes'       => [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES', 'default' => true]],
        'handlingTime'        => ['unit' => 'DAY', 'value' => 1],
        'shippingOptions'     => [['costType' => 'FLAT_RATE', 'optionType' => 'DOMESTIC', 'shippingServices' => [['shippingCarrierCode' => 'RoyalMail', 'shippingServiceCode' => 'UK_RoyalMailNextDay', 'freeShipping' => false, 'shippingCost' => ['currency' => 'GBP', 'value' => '1.00']]]]],
    ];

    return $overrides + [
        '/commerce/identity/v1/user/'      => ['userId' => 'u-1', 'username' => 'aw_seller', 'accountType' => 'BUSINESS', 'status' => 'CONFIRMED', 'registrationMarketplaceId' => 'EBAY_GB'],
        '/sell/account/v1/privilege'       => ['sellerRegistrationCompleted' => true, 'sellingLimit' => ['amount' => ['currency' => 'GBP', 'value' => '1000.0'], 'quantity' => 100]],
        '/sell/account/v1/fulfillment_policy' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['fulfillmentPolicies' => [$usableFulfilmentPolicy('fp-1')], 'total' => 1])
            : Http::response(['fulfillmentPolicyId' => basename(parse_url($request->url(), PHP_URL_PATH)) === 'fulfillment_policy' ? 'fp-9' : basename(parse_url($request->url(), PHP_URL_PATH)), 'name' => 'Shipping'], 201),
        '/sell/account/v1/payment_policy'  => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['paymentPolicies' => [['paymentPolicyId' => 'pp-list', 'name' => 'Payment']], 'total' => 1])
            : Http::response(['paymentPolicyId' => 'pp-9', 'name' => 'Payment Policy'], 201),
        '/sell/account/v1/return_policy'   => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['returnPolicies' => [['returnPolicyId' => 'rp-list', 'name' => 'Return']], 'total' => 1])
            : Http::response(['returnPolicyId' => basename(parse_url($request->url(), PHP_URL_PATH)) === 'return_policy' ? 'rp-9' : basename(parse_url($request->url(), PHP_URL_PATH)), 'returnsAccepted' => true], 201),
        '/sell/account/v1/program/opt_in'  => fn () => Http::response('', 200),
        '/sell/inventory/v1/location'      => fn (Request $request) => $request->method() === 'POST'
            ? Http::response('', 204)
            : Http::response(['locations' => [['merchantLocationKey' => 'somewhere-else']], 'total' => 1]),
    ];
}

test('creating an eBay user opens a channel keyed to the shop marketplace', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => 'my-ebay-shop']);
    $channel  = $ebayUser->customerSalesChannel;

    expect($ebayUser->step)->toBe(EbayUserStepEnum::MARKETPLACE)
        ->and($ebayUser->marketplace)->toBe('EBAY_GB')
        ->and($ebayUser->platform_id)->toBe($this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->first()->id)
        ->and($channel->reference)->toBe('ebay-'.$customer->reference)
        ->and($channel->name)->toBe('my-ebay-shop')
        ->and($channel->platform_user_type)->toBe('EbayUser')
        ->and($channel->platform_user_id)->toBe($ebayUser->id)
        ->and($channel->platform_status)->toBeFalse()
        ->and($customer->refresh()->ebayUser->id)->toBe($ebayUser->id);
});

test('the OAuth callback exchanges the code, stores the tokens and hands the user to the channel check', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => 'callback-ebay']);

    fakeEbay($this, [
        'oauth2/token'                => ['access_token' => 'v^1.1#new-access', 'refresh_token' => 'v^1.1#new-refresh', 'expires_in' => 7200, 'refresh_token_expires_in' => 47304000, 'token_type' => 'User Access Token'],
        '/commerce/identity/v1/user/' => ['userId' => 'u-callback', 'username' => 'aw_seller', 'accountType' => 'BUSINESS'],
    ]);
    CheckEbayChannel::mock()->shouldReceive('handle')->once()->andReturn($ebayUser->customerSalesChannel);

    $url = CallbackRetinaEbayUser::make()->handle($customer, ['code' => 'v^1.1#auth-code', 'expires_in' => 299]);

    $ebayUser->refresh();
    expect($url)->toBe(route('retina.dropshipping.platform.ebay_callback.success'))
        ->and($ebayUser->step)->toBe(EbayUserStepEnum::AUTH)
        ->and(Arr::get($ebayUser->settings, 'credentials.ebay_access_token'))->toBe('v^1.1#new-access')
        ->and(Arr::get($ebayUser->settings, 'credentials.ebay_refresh_token'))->toBe('v^1.1#new-refresh')
        ->and(Arr::get($ebayUser->settings, 'credentials.ebay_token_expires_at'))->not->toBeNull()
        ->and(Arr::get($ebayUser->data, 'ebay_user'))->toBe(['userId' => 'u-callback', 'username' => 'aw_seller']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth2/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'v^1.1#auth-code'
        && $request['redirect_uri'] === 'aiku-redirect'
        && str_starts_with($request->header('Authorization')[0], 'Basic '));
});

test('the OAuth callback with no channel waiting for it just sends the customer to the success page', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $ebayUser = StoreEbayUser::make()->handle($customer, ['name' => 'done-ebay']);
    $ebayUser->update(['step' => EbayUserStepEnum::COMPLETED]);

    fakeEbay($this, ['oauth2/token' => ['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 7200]]);
    CheckEbayChannel::mock()->shouldReceive('handle')->never();

    expect(CallbackRetinaEbayUser::make()->handle($customer, ['code' => 'x']))->toBe(route('retina.dropshipping.platform.ebay_callback.success'))
        ->and($ebayUser->refresh()->step)->toBe(EbayUserStepEnum::COMPLETED);
});

test('the OAuth callback throws when eBay refuses the code', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    StoreEbayUser::make()->handle($customer, ['name' => 'refused-ebay']);

    fakeEbay($this, ['oauth2/token' => fn () => Http::response(['error' => 'invalid_grant', 'error_description' => 'the provided authorization grant code is invalid'], 400)]);

    expect(fn () => CallbackRetinaEbayUser::make()->handle($customer, ['code' => 'stale']))->toThrow(Exception::class, 'invalid_grant');
});

test('checking a fully provisioned channel completes it and records the seller registration state', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['platform_status' => false]);

    fakeEbay($this, ebayAccountRoutes(['/sell/account/v1/privilege' => ['sellerRegistrationCompleted' => false]]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($channel->platform_status)->toBeTrue()
        ->and($channel->can_connect_to_platform)->toBeTrue()
        ->and($channel->exist_in_platform)->toBeTrue()
        ->and($channel->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($ebayUser->refresh()->step)->toBe(EbayUserStepEnum::COMPLETED)
        ->and(Arr::get($ebayUser->data, 'seller_registration_completed'))->toBeFalse();

    fakeEbay($this, ebayAccountRoutes(['/sell/account/v1/privilege' => fn () => Http::response(['errors' => [['errorId' => 2003, 'message' => 'Internal error']]], 500)]));
    CheckEbayChannel::run($ebayUser);
    expect(Arr::get($ebayUser->refresh()->data, 'seller_registration_completed'))->toBeFalse();

    fakeEbay($this, ebayAccountRoutes(['/sell/account/v1/privilege' => fn () => Http::response('', 503)]));
    CheckEbayChannel::run($ebayUser);
    expect(Arr::get($ebayUser->refresh()->data, 'seller_registration_completed'))->toBeFalse();

    fakeEbay($this, ebayAccountRoutes(['/sell/account/v1/privilege' => fn () => Http::response([], 200)]));
    CheckEbayChannel::run($ebayUser);
    expect(Arr::get($ebayUser->refresh()->data, 'seller_registration_completed'))->toBeFalse();
});

test('an eBay outage while re-provisioning never wipes a location key that was usable', function () {
    $ebayUser = ebayChannel($this, ['return_policy_id' => null]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/inventory/v1/location' => fn () => Http::response(['errors' => [['errorId' => 2003, 'message' => 'Interner Fehler']]], 500),
    ]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($ebayUser->refresh()->location_key)->toBe('aw-warehouse-gb')
        ->and($ebayUser->return_policy_id)->toBe('rp-9')
        ->and($channel->platform_status)->toBeTrue();
});

test('checking a channel whose postage policy eBay no longer lists swaps in a usable one', function () {
    $ebayUser = ebayChannel($this, ['fulfillment_policy_id' => 'fp-gone']);

    fakeEbay($this, ebayAccountRoutes());

    $channel = CheckEbayChannel::run($ebayUser);

    expect($ebayUser->refresh()->fulfillment_policy_id)->toBe('fp-1')
        ->and($channel->platform_status)->toBeTrue();
});

test('checking a channel with an expired access token refreshes it once and carries on', function () {
    $ebayUser = ebayChannel($this);
    $identityCalls = 0;

    fakeEbay($this, ebayAccountRoutes([
        '/commerce/identity/v1/user/' => function (Request $request) use (&$identityCalls) {
            $identityCalls++;

            return $request->header('Authorization')[0] === 'Bearer refreshed-access'
                ? Http::response(['userId' => 'u-1', 'username' => 'aw_seller'])
                : Http::response(['errors' => [['errorId' => 1001, 'message' => 'Invalid access token']]], 401);
        },
        'oauth2/token' => ['access_token' => 'refreshed-access', 'expires_in' => 7200, 'token_type' => 'User Access Token'],
    ]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($identityCalls)->toBe(2)
        ->and($channel->platform_status)->toBeTrue()
        ->and(Arr::get($ebayUser->refresh()->settings, 'credentials.ebay_access_token'))->toBe('refreshed-access')
        ->and(Arr::get($ebayUser->settings, 'credentials.ebay_refresh_token'))->toBe('refresh-token');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth2/token') && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-token');
});

test('checking a channel whose eBay authorisation was revoked disconnects it and sends the seller back to authorise', function () {
    $ebayUser = ebayChannel($this);

    fakeEbay($this, ebayAccountRoutes([
        '/commerce/identity/v1/user/' => fn () => Http::response(['errors' => [['errorId' => 1001, 'message' => 'Invalid access token']]], 401),
        'oauth2/token'                => fn () => Http::response(['error' => 'invalid_grant', 'error_description' => 'the provided refresh token is invalid'], 400),
    ]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($channel->platform_status)->toBeFalse()
        ->and($channel->can_connect_to_platform)->toBeFalse()
        ->and($channel->exist_in_platform)->toBeFalse()
        ->and($ebayUser->refresh()->step)->toBe(EbayUserStepEnum::MARKETPLACE);
});

test('checking a channel while eBay is down leaves it exactly as it was', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->update(['step' => EbayUserStepEnum::COMPLETED]);

    fakeEbay($this, ebayAccountRoutes([
        '/commerce/identity/v1/user/' => fn () => Http::response('<html>503</html>', 503),
    ]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($channel->platform_status)->toBeTrue()
        ->and($ebayUser->refresh()->step)->toBe(EbayUserStepEnum::COMPLETED);
});

test('a freshly authorised channel gets its policies and warehouse location created on eBay', function () {
    $ebayUser = ebayChannel($this, ['fulfillment_policy_id' => null, 'payment_policy_id' => null, 'return_policy_id' => null, 'location_key' => 'mainWarehouse']);

    fakeEbay($this, ebayAccountRoutes());

    $ebayUser = UpdateEbayUserData::run($ebayUser);

    expect($ebayUser->fulfillment_policy_id)->toBe('fp-9')
        ->and($ebayUser->payment_policy_id)->toBe('pp-9')
        ->and($ebayUser->return_policy_id)->toBe('rp-9')
        ->and($ebayUser->location_key)->toBe($this->shop->slug.'-warehouse-GB')
        ->and($ebayUser->hasUsableLocationKey())->toBeTrue();

    sentEbayRequest('POST', '/sell/account/v1/program/opt_in');

    $postage = sentEbayRequest('POST', '/sell/account/v1/fulfillment_policy')->data();
    expect($postage['name'])->toBe('Shipping-'.$ebayUser->customerSalesChannel->slug)
        ->and($postage['marketplaceId'])->toBe('EBAY_GB')
        ->and($postage['categoryTypes'])->toBe([['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']])
        ->and($postage['handlingTime'])->toBe(['unit' => 'DAY', 'value' => 1])
        ->and($postage['shippingOptions'][0]['shippingServices'][0]['shippingCarrierCode'])->toBe('RoyalMail')
        ->and($postage['shippingOptions'][0]['shippingServices'][0]['shippingServiceCode'])->toBe('UK_RoyalMailNextDay')
        ->and($postage['shippingOptions'][0]['shippingServices'][0]['shippingCost'])->toBe(['currency' => 'GBP', 'value' => '1']);

    $return = sentEbayRequest('POST', '/sell/account/v1/return_policy')->data();
    expect($return['returnsAccepted'])->toBeTrue()
        ->and($return['returnShippingCostPayer'])->toBe('SELLER')
        ->and($return['returnPeriod'])->toBe(['value' => 30, 'unit' => 'DAY']);

    $location = sentEbayRequest('POST', '/sell/inventory/v1/location/'.$this->shop->slug.'-warehouse-GB')->data();
    expect($location['location']['address'])->toBe(['city' => 'Sheffield', 'stateOrProvince' => 'South Yorkshire', 'country' => 'GB'])
        ->and($location['merchantLocationStatus'])->toBe('ENABLED')
        ->and($location['locationTypes'])->toBe(['WAREHOUSE']);
});

test('provisioning falls back to the policies and location eBay already has when it refuses duplicates', function () {
    $ebayUser = ebayChannel($this, ['fulfillment_policy_id' => null, 'payment_policy_id' => null, 'return_policy_id' => null, 'location_key' => null]);
    $locationKey = $this->shop->slug.'-warehouse-GB';

    $duplicate = fn () => Http::response(['errors' => [['errorId' => 20400, 'message' => 'A policy with this name already exists.']]], 400);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/fulfillment_policy' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['fulfillmentPolicies' => [
                ['fulfillmentPolicyId' => 'fp-motors', 'categoryTypes' => [['name' => 'MOTORS_VEHICLES']], 'shippingOptions' => [['shippingServices' => [['shippingServiceCode' => 'UK_RoyalMailNextDay']]]]],
                ['fulfillmentPolicyId' => 'fp-no-service', 'categoryTypes' => [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']], 'shippingOptions' => []],
                ['fulfillmentPolicyId' => 'fp-usable', 'categoryTypes' => [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']], 'shippingOptions' => [['shippingServices' => [['shippingServiceCode' => 'UK_RoyalMailNextDay']]]]],
            ]])
            : $duplicate(),
        '/sell/account/v1/payment_policy'     => fn (Request $request) => $request->method() === 'GET' ? Http::response(['paymentPolicies' => [['paymentPolicyId' => 'pp-list']]]) : $duplicate(),
        '/sell/account/v1/return_policy'      => fn (Request $request) => $request->method() === 'GET' ? Http::response(['returnPolicies' => [['returnPolicyId' => 'rp-list']]]) : $duplicate(),
        '/sell/inventory/v1/location'         => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['errors' => [['errorId' => 25803, 'message' => 'Location Already Exists']]], 409)
            : Http::response(['locations' => [], 'total' => 0]),
    ]));

    $ebayUser = UpdateEbayUserData::run($ebayUser);

    expect($ebayUser->fulfillment_policy_id)->toBe('fp-usable')
        ->and($ebayUser->payment_policy_id)->toBe('pp-list')
        ->and($ebayUser->return_policy_id)->toBe('rp-list')
        ->and($ebayUser->location_key)->toBe($locationKey);
});

test('a location eBay rejects and does not list is not stored as if it existed, and policies eBay no longer lists are recreated', function () {
    $ebayUser = ebayChannel($this, ['location_key' => null]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/inventory/v1/location' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['errors' => [['errorId' => 25802, 'message' => 'Input error. Invalid country.', 'category' => 'REQUEST']]], 400)
            : Http::response(['locations' => [], 'total' => 0]),
    ]));

    $ebayUser = UpdateEbayUserData::run($ebayUser);

    expect($ebayUser->location_key)->toBeNull()
        ->and($ebayUser->fulfillment_policy_id)->toBe('fp-1')
        ->and($ebayUser->payment_policy_id)->toBe('pp-9')
        ->and($ebayUser->return_policy_id)->toBe('rp-9');

    fakeEbay($this, ebayAccountRoutes([
        '/sell/inventory/v1/location' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['errors' => [['errorId' => 25802, 'message' => 'Input error. Invalid country.']]], 400)
            : Http::response(['locations' => [], 'total' => 0]),
    ]));

    $channel = CheckEbayChannel::run($ebayUser);

    expect($channel->platform_status)->toBeFalse()
        ->and($channel->can_connect_to_platform)->toBeTrue()
        ->and($ebayUser->refresh()->step)->toBe(EbayUserStepEnum::AUTH);
});

test('updating the return policy pushes the channel settings to eBay and keeps the reply', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['return' => ['accepted' => true, 'payer' => 'BUYER', 'within' => 14, 'description' => 'Unused items only']]]);

    fakeEbay($this, [
        '/sell/account/v1/return_policy/rp-1' => ['returnPolicyId' => 'rp-1', 'name' => 'minimal return policy', 'returnsAccepted' => true, 'returnShippingCostPayer' => 'BUYER', 'returnPeriod' => ['value' => 14, 'unit' => 'DAY']],
    ]);

    UpdateReturnPolicyEbayUser::run($ebayUser->refresh(), []);

    $body = sentEbayRequest('PUT', '/sell/account/v1/return_policy/rp-1')->data();
    expect($body['returnsAccepted'])->toBeTrue()
        ->and($body['returnShippingCostPayer'])->toBe('BUYER')
        ->and($body['returnPeriod'])->toBe(['value' => 14, 'unit' => 'DAY'])
        ->and($body['description'])->toBe('Unused items only')
        ->and($body['marketplaceId'])->toBe('EBAY_GB');

    $ebayUser->refresh();
    expect(Arr::get($ebayUser->settings, 'return'))->toEqual(['accepted' => true, 'payer' => 'BUYER', 'within' => 14, 'description' => 'Unused items only'])
        ->and(Arr::get($ebayUser->data, 'return_policy.returnPolicyId'))->toBe('rp-1')
        ->and(Arr::get($ebayUser->settings, 'credentials.ebay_access_token'))->toBe('access-token');
});

test('updating the postage policy pushes the chosen service and marks free postage when the price is zero', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['shipping' => ['price' => '0', 'max_dispatch_time' => '3', 'carrier_code' => 'RoyalMail', 'carrier_name' => 'Royal Mail', 'service_code' => 'UK_RoyalMailNextDay', 'service_name' => 'Royal Mail']]]);

    fakeEbay($this, [
        '/sell/account/v1/fulfillment_policy/fp-1' => ['fulfillmentPolicyId' => 'fp-1', 'name' => 'Shipping'],
    ]);

    UpdateShippingPolicyEbayUser::run($ebayUser->refresh(), []);

    $service = sentEbayRequest('PUT', '/sell/account/v1/fulfillment_policy/fp-1')->data();
    expect($service['handlingTime'])->toBe(['unit' => 'DAY', 'value' => 3])
        ->and($service['shippingOptions'][0]['shippingServices'][0]['freeShipping'])->toBe('true')
        ->and($service['shippingOptions'][0]['shippingServices'][0]['shippingCost'])->toBe(['currency' => 'GBP', 'value' => '0'])
        ->and($service['shippingOptions'][0]['shippingServices'][0]['shippingCarrierCode'])->toBe('RoyalMail')
        ->and($service['shippingOptions'][0]['shippingServices'][0]['shippingServiceCode'])->toBe('UK_RoyalMailNextDay');

    $ebayUser->refresh();
    expect(Arr::get($ebayUser->settings, 'shipping.service_code'))->toBe('UK_RoyalMailNextDay')
        ->and(Arr::get($ebayUser->data, 'fulfillment_policy.fulfillmentPolicyId'))->toBe('fp-1');
});

test('saving postage and return settings from the channel page updates both eBay policies and rechecks the channel', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['platform_status' => false]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/fulfillment_policy/fp-1' => ['fulfillmentPolicyId' => 'fp-1'],
        '/sell/account/v1/return_policy/rp-1'      => ['returnPolicyId' => 'rp-1'],
    ]));

    $channel = UpdateEbayCustomerSalesChannel::make()->action($ebayUser->customerSalesChannel, [
        'shipping_service'           => 'UK_RoyalMailNextDay',
        'shipping_price'             => '2.50',
        'shipping_max_dispatch_time' => '2',
        'return_accepted'            => true,
        'return_payer'               => 'SELLER',
        'return_within'              => 30,
        'return_description'         => 'Returns welcome',
    ]);

    expect(sentEbayRequest('PUT', '/sell/account/v1/fulfillment_policy/fp-1')->data()['shippingOptions'][0]['shippingServices'][0]['shippingCost']['value'])->toBe('2.50')
        ->and(sentEbayRequest('PUT', '/sell/account/v1/return_policy/rp-1')->data()['returnShippingCostPayer'])->toBe('SELLER')
        ->and($channel->refresh()->platform_status)->toBeTrue()
        ->and(Arr::get($channel->settings, 'shipping.carrier_code'))->toBe('RoyalMail')
        ->and(Arr::get($channel->settings, 'return.within'))->toBe(30);
});

test('choosing the customer\'s own eBay postage and return policies links them without sending anything to eBay and shows what they hold, even with fields filled', function () {
    $ebayUser = ebayChannel($this, ['fulfillment_policy_id' => null, 'return_policy_id' => null]);
    $ebayUser->customerSalesChannel->update(['settings' => [
        'shipping' => ['price' => '3', 'max_dispatch_time' => '2', 'service_code' => 'UK_RoyalMailNextDay'],
        'return'   => ['accepted' => true, 'payer' => 'SELLER', 'within' => 60],
    ]]);

    fakeEbay($this, ebayAccountRoutes([]));

    $channel = UpdateEbayCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
        'fulfillment_policy_id'      => 'fp-1',
        'return_policy_id'           => 'rp-list',
        'shipping_price'             => '0',
        'shipping_max_dispatch_time' => '4',
        'return_accepted'            => true,
    ]);

    Http::assertNotSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '_policy/'));

    expect($ebayUser->refresh()->fulfillment_policy_id)->toBe('fp-1')
        ->and($ebayUser->return_policy_id)->toBe('rp-list')
        ->and(Arr::get($channel->refresh()->settings, 'shipping.price'))->toBe('1.00')
        ->and(Arr::get($channel->settings, 'shipping.max_dispatch_time'))->toBe('1')
        ->and(Arr::get($channel->settings, 'return.accepted'))->toBeFalse();
});

test('turning returns on by itself sends eBay a complete return policy', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['return' => ['accepted' => false]]]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/return_policy/rp-1' => ['returnPolicyId' => 'rp-1', 'returnsAccepted' => true],
    ]));

    $channel = UpdateEbayCustomerSalesChannel::run($ebayUser->customerSalesChannel, ['return_accepted' => true]);

    $body = sentEbayRequest('PUT', '/sell/account/v1/return_policy/rp-1')->data();
    expect($body['returnsAccepted'])->toBeTrue()
        ->and($body['returnShippingCostPayer'])->toBe('BUYER')
        ->and($body['returnPeriod'])->toBe(['value' => 30, 'unit' => 'DAY'])
        ->and(Arr::get($channel->refresh()->settings, 'return'))->toMatchArray(['accepted' => true, 'payer' => 'BUYER', 'within' => 30]);
});

test('a return policy eBay refuses is reported and the channel keeps its previous returns settings', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['return' => ['accepted' => false]]]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/return_policy/rp-1' => fn () => Http::response(['errors' => [['errorId' => 20403, 'message' => 'Invalid return period.']]], 400),
    ]));

    expect(fn () => UpdateEbayCustomerSalesChannel::run($ebayUser->customerSalesChannel, ['return_accepted' => true, 'return_payer' => 'BUYER', 'return_within' => 30]))
        ->toThrow(ValidationException::class, 'Invalid return period.');

    expect(Arr::get($ebayUser->customerSalesChannel->refresh()->settings, 'return.accepted'))->toBeFalse();
});

test('an eBay refusal shows the customer eBay\'s full reason, not just its one-word message', function () {
    $ebayUser = ebayChannel($this);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/fulfillment_policy/fp-1' => fn () => Http::response(['errors' => [[
            'errorId'     => 20403,
            'message'     => 'Invalid.',
            'longMessage' => 'A policy with the name Shipping already exists.',
            'parameters'  => [['name' => 'name', 'value' => 'Shipping']],
        ]]], 400),
    ]));

    expect(fn () => UpdateEbayCustomerSalesChannel::run($ebayUser->customerSalesChannel, ['shipping_price' => '4.50']))
        ->toThrow(ValidationException::class, 'Invalid. A policy with the name Shipping already exists. (name Shipping)');
});

test('a postage policy eBay refuses is reported and the channel keeps its previous postage', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => ['shipping' => ['price' => '3', 'carrier_code' => 'RoyalMail', 'service_code' => 'UK_RoyalMailNextDay']]]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/fulfillment_policy/fp-1' => fn () => Http::response(['errors' => [['errorId' => 20403, 'message' => 'Invalid shipping service.']]], 400),
    ]));

    expect(fn () => UpdateEbayCustomerSalesChannel::run($ebayUser->customerSalesChannel, ['shipping_price' => '4.50']))
        ->toThrow(ValidationException::class, 'Invalid shipping service.');

    expect(sentEbayRequest('PUT', '/sell/account/v1/fulfillment_policy/fp-1')->data()['shippingOptions'][0]['shippingServices'][0]['shippingCost']['value'])->toBe('4.50')
        ->and(Arr::get($ebayUser->customerSalesChannel->refresh()->settings, 'shipping.price'))->toBe('3');
});

test('a channel whose returns and postage differ from eBay takes what eBay holds, only with apply, and nothing is sent to eBay', function () {
    $ebayUser = ebayChannel($this);
    $ebayUser->customerSalesChannel->update(['settings' => [
        'return'   => ['accepted' => true, 'payer' => 'SELLER', 'within' => 60],
        'shipping' => ['price' => '4', 'max_dispatch_time' => '1', 'service_code' => 'UK_RoyalMailNextDay', 'service_name' => 'Royal Mail', 'carrier_code' => 'RoyalMail', 'carrier_name' => 'Royal Mail'],
        'upload_as_draft' => true,
    ]]);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/return_policy' => ['returnPolicies' => [['returnPolicyId' => 'rp-1', 'returnsAccepted' => false]]],
    ]));

    $differences = SyncEbayChannelPoliciesFromEbay::run($ebayUser, false);

    expect($differences['returns'])->toBe(['accepted' => ['aw' => true, 'ebay' => false]])
        ->and($differences['shipping'])->toBe(['price' => ['aw' => '4', 'ebay' => '1.00']])
        ->and(Arr::get($ebayUser->customerSalesChannel->refresh()->settings, 'return.accepted'))->toBeTrue();

    SyncEbayChannelPoliciesFromEbay::run($ebayUser->refresh(), true);

    $settings = $ebayUser->customerSalesChannel->refresh()->settings;
    expect($settings['return']['accepted'])->toBeFalse()
        ->and($settings['shipping']['price'])->toBe('1.00')
        ->and($settings['shipping']['service_code'])->toBe('UK_RoyalMailNextDay')
        ->and($settings['upload_as_draft'])->toBeTrue()
        ->and(SyncEbayChannelPoliciesFromEbay::run($ebayUser->refresh(), false))->toBe(['returns' => [], 'shipping' => []])
        ->and(collect(Http::recorded())->map(fn ($pair) => $pair[0]->method())->unique()->values()->all())->toBe(['GET']);
});

test('an eBay postage policy without a service code does not put the list of services into the channel settings', function () {
    $ebayUser = ebayChannel($this);

    fakeEbay($this, ebayAccountRoutes([
        '/sell/account/v1/return_policy'      => ['returnPolicies' => [['returnPolicyId' => 'rp-1', 'returnsAccepted' => true, 'returnShippingCostPayer' => 'BUYER', 'returnPeriod' => ['value' => 30, 'unit' => 'DAY']]]],
        '/sell/account/v1/fulfillment_policy' => ['fulfillmentPolicies' => [['fulfillmentPolicyId' => 'fp-1', 'handlingTime' => ['value' => 2, 'unit' => 'DAY'], 'shippingOptions' => []]]],
    ]));

    $differences = SyncEbayChannelPoliciesFromEbay::run($ebayUser, false);

    expect(array_keys($differences['shipping']))->toBe(['max_dispatch_time']);
});

test('deleting an eBay user closes its channel and soft deletes the user', function () {
    $ebayUser = ebayChannel($this);
    $channel  = $ebayUser->customerSalesChannel;

    DeleteEbayUser::run($ebayUser);

    expect($channel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and(EbayUser::find($ebayUser->id))->toBeNull()
        ->and(EbayUser::withTrashed()->find($ebayUser->id))->not->toBeNull();
});

test('the authorised check only passes when eBay identifies the seller', function () {
    $ebayUser = ebayChannel($this);

    fakeEbay($this, ['/commerce/identity/v1/user/' => ['userId' => 'u-1', 'username' => 'aw_seller']]);
    CheckEbayUserAuthorized::run($ebayUser);

    fakeEbay($this, ['/commerce/identity/v1/user/' => fn () => Http::response(['errors' => [['errorId' => 1001]]], 401), 'oauth2/token' => fn () => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(fn () => CheckEbayUserAuthorized::run($ebayUser))->toThrow(ValidationException::class);
});

test('an eBay order we cannot charge gets the on-hold notice and not an order confirmation', function () {
    /** The fixture customer has no saved card and no balance, so this is the HELP-3116 case: the
     * order submits unpaid. The customer must be told it is waiting, and must not be sent a
     * confirmation that says the opposite. */
    Queue::fake();
    $ebayUser  = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    FetchEbayUserOrders::run($ebayUser);

    $order = Order::where('platform_order_id', $ebayOrder['orderId'])->firstOrFail();
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->pay_status)->not->toBe(OrderPayStatusEnum::PAID)
        ->and($order->isPlacedOnAChannel())->toBeTrue();

    SendChannelOrderOnHoldEmail::assertPushed(1);
    SendChannelOrderOnHoldEmail::assertPushed(fn ($job, $arguments) => $arguments[0] === $order->id);
    SendNewOrderEmailToCustomer::assertNotPushed();
});

test('the on-hold notice goes out through the seeded channel_order_on_hold outbox in the shop language', function () {
    /** Seeding is idempotent: the first pass creates the outbox and its email from the dataset
     * template, the second sees a blade email in place and activates it, as it does for every shop. */
    SeedShopOutboxes::run($this->shop);
    SeedShopOutboxes::run($this->shop);
    $outbox = Outbox::where('shop_id', $this->shop->id)->where('code', OutboxCodeEnum::CHANNEL_ORDER_ON_HOLD)->firstOrFail();
    expect($outbox->state)->toBe(OutboxStateEnum::ACTIVE);

    $ebayUser  = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);

    /** The queue is sync under test, so the import itself sends the notice: this is the whole
     * path a real eBay order takes, from fetch to a dispatched email on the order. */
    FetchEbayUserOrders::run($ebayUser);
    $order = Order::where('platform_order_id', $ebayOrder['orderId'])->firstOrFail();

    $dispatchedEmail = $order->dispatchedEmails()->first();
    expect($order->dispatchedEmails()->count())->toBe(1)
        ->and($dispatchedEmail)->toBeInstanceOf(DispatchedEmail::class)
        ->and($dispatchedEmail->outbox_id)->toBe($outbox->id);

    app()->setLocale($this->shop->language->code);
    expect(SendChannelOrderOnHoldEmail::make()->generateBodyHtml($order))
        ->toContain($order->reference)
        ->toContain(__('We strongly recommend saving a payment card on your sales channel.'));
});

test('a channel order still unpaid a week later is reminded once, on the day it crosses the line', function () {
    Queue::fake();
    $ebayUser  = ebayChannel($this);
    listedEbayPortfolio($this, $ebayUser);
    $ebayOrder = ebayOrder();
    fakeEbay($this, [
        '/sell/fulfillment/v1/order' => ['orders' => [$ebayOrder], 'total' => 1],
    ]);
    FetchEbayUserOrders::run($ebayUser);
    $order = Order::where('platform_order_id', $ebayOrder['orderId'])->firstOrFail();

    /** The import itself sent the first notice */
    SendChannelOrderOnHoldEmail::assertPushed(1);

    /** Three days in: too soon, nothing more */
    DB::table('orders')->where('id', $order->id)->update(['submitted_at' => now()->subDays(3)]);
    expect(RemindChannelOrdersOnHold::run())->toBe([]);
    SendChannelOrderOnHoldEmail::assertPushed(1);

    /** Crossed the week: reminded, and only this order */
    DB::table('orders')->where('id', $order->id)->update(['submitted_at' => now()->subDays(RemindChannelOrdersOnHold::REMIND_AFTER_DAYS)->subHours(12)]);
    expect(RemindChannelOrdersOnHold::run())->toBe([$order->id]);
    SendChannelOrderOnHoldEmail::assertPushed(2);

    /** Well past the week: it had its turn, the standing pile is not re-mailed every morning */
    DB::table('orders')->where('id', $order->id)->update(['submitted_at' => now()->subDays(30)]);
    expect(RemindChannelOrdersOnHold::run())->toBe([]);
    SendChannelOrderOnHoldEmail::assertPushed(2);

    /** Paid in the meantime: nothing to remind about */
    DB::table('orders')->where('id', $order->id)->update([
        'submitted_at' => now()->subDays(RemindChannelOrdersOnHold::REMIND_AFTER_DAYS)->subHours(12),
        'pay_status'   => OrderPayStatusEnum::PAID->value,
    ]);
    expect(RemindChannelOrdersOnHold::run())->toBe([]);
    SendChannelOrderOnHoldEmail::assertPushed(2);
});

test('creating one product from retina queues the upload and the job reports the eBay refusal on the row', function () {
    Queue::fake();
    Event::fake([UploadProductToSalesChannelProgressEvent::class]);

    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);

    fakeEbay($this, ebayCatalogueRoutes() + [
        '/identity/v1/oauth2/token' => fn () => Http::response(['error' => 'invalid_grant', 'error_description' => 'the provided authorization refresh token is invalid'], 400),
        '/sell/inventory/v1/offer'  => fn () => Http::response(['errors' => [['errorId' => 1001, 'domain' => 'OAuth', 'category' => 'REQUEST', 'message' => 'Invalid access token']]], 401),
    ]);

    StoreRetinaNewProductToCurrentEbay::make()->handle($portfolio);

    Http::assertNothingSent();

    $arguments = null;
    StoreNewProductToCurrentEbay::assertPushed(function ($job, array $pushed) use ($portfolio, &$arguments) {
        $arguments = $pushed;

        return $pushed[1]->id === $portfolio->id && $pushed[2]['total'] === 1;
    });

    StoreNewProductToCurrentEbay::make()->handle($ebayUser, $portfolio, $arguments[2]);

    expect($portfolio->refresh()->platform_status)->toBeFalse()
        ->and($portfolio->upload_warning)->toBe('Invalid access token');
    Event::assertDispatched(UploadProductToSalesChannelProgressEvent::class, fn ($event) => $event->portfolio->id === $portfolio->id && $event->statistics === ['total' => 1, 'success' => 0, 'fail' => 1]);
});

test('the hourly eBay stock check probes every channel in parallel with the same outcome per channel as one at a time', function () {
    Queue::fake();

    $tokens = ['tok-a' => 'ok', 'tok-b' => 'expired', 'tok-b-new' => 'ok', 'tok-c' => 'down', 'tok-d' => 'timeout'];
    $routes = [
        '/identity/v1/oauth2/token'   => fn (Request $request) => $request['refresh_token'] === 'refresh-b'
            ? Http::response(['access_token' => 'tok-b-new', 'expires_in' => 7200])
            : Http::response(['error' => 'invalid_grant'], 400),
        '/commerce/identity/v1/user/' => fn (Request $request) => match ($tokens[Str::after($request->header('Authorization')[0], 'Bearer ')] ?? 'down') {
            'ok'      => Http::response(['userId' => 'u-1', 'username' => 'aw_seller']),
            'expired' => Http::response(['errors' => [['message' => 'Invalid access token']]], 401),
            'timeout' => throw new ConnectionException('cURL error 28: timed out'),
            default   => Http::response(['errors' => [['message' => 'down']]], 500),
        },
    ];

    $channels = collect(['a', 'b', 'c', 'd'])->mapWithKeys(function (string $name) {
        $ebayUser = ebayChannel($this, ['settings' => ['credentials' => [
            'ebay_access_token'     => 'tok-'.$name,
            'ebay_refresh_token'    => 'refresh-'.$name,
            'ebay_token_expires_at' => now()->addHour()->toIso8601String(),
        ]]]);
        $ebayUser->customerSalesChannel->update(['stock_update' => true, 'status' => CustomerSalesChannelStatusEnum::OPEN, 'ban_stock_update_util' => now()->subMinute()]);

        return [$name => [$ebayUser, listedEbayPortfolio($this, $ebayUser, '1234567890'.$name, 'offer-'.$name)]];
    });

    fakeEbay($this, $routes);
    UpdateInventoryInEbayPortfolio::make()->handle();

    $pushedPortfolioIds = [];
    UpdateEbayPortfolio::assertPushed(function ($job, array $arguments) use (&$pushedPortfolioIds) {
        $pushedPortfolioIds[] = $arguments[0];

        return true;
    });

    $bannedUntil = fn (string $name) => $channels[$name][0]->customerSalesChannel->refresh()->ban_stock_update_util;

    expect(array_intersect($channels->map(fn ($channel) => $channel[1]->id)->all(), $pushedPortfolioIds))->toBe(['a' => $channels['a'][1]->id, 'b' => $channels['b'][1]->id])
        ->and($bannedUntil('a'))->toBeNull()
        ->and($bannedUntil('b'))->toBeNull()
        ->and($bannedUntil('c')->isFuture())->toBeTrue()
        ->and($bannedUntil('d')->isFuture())->toBeTrue()
        ->and(Arr::get($channels['b'][0]->refresh()->settings, 'credentials.ebay_access_token'))->toBe('tok-b-new');

    $ebayUsers = $channels->map(fn ($channel) => $channel[0]->refresh())->all();
    fakeEbay($this, $routes);
    $pooled = EbayUser::getUsers($ebayUsers);

    expect($pooled)->toBe(array_map(fn (EbayUser $ebayUser) => $ebayUser->getUser(), $ebayUsers))
        ->and($pooled['a'])->toBe(['userId' => 'u-1', 'username' => 'aw_seller'])
        ->and($pooled['c'])->toBeNull()
        ->and($pooled['d'])->toBeNull();
});

test('a queued upload the queue kills still reports its failure so the waiting page completes', function () {
    Event::fake([UploadProductToSalesChannelProgressEvent::class]);

    $ebayUser  = ebayChannel($this);
    $portfolio = StorePortfolio::make()->action($ebayUser->customerSalesChannel, $this->product, []);
    Cache::put('upload_progress_ebay_killed_fail', 0, now()->addHour());

    StoreNewProductToCurrentEbay::make()->jobFailed(new \Illuminate\Queue\TimeoutExceededException('killed'), $ebayUser, $portfolio, ['cache_key' => 'upload_progress_ebay_killed', 'total' => 1]);

    expect($portfolio->refresh()->errors_response)->not->toBeNull();
    Event::assertDispatched(UploadProductToSalesChannelProgressEvent::class, fn ($event) => $event->portfolio->id === $portfolio->id && $event->statistics === ['total' => 1, 'success' => 0, 'fail' => 1]);
});

test('a bundle family is not used to find the eBay category, so bundles are not suggested into car cleaning kits', function () {
    $department = new ProductCategory(['code' => 'aroma', 'name' => 'Aromatherapy']);

    $bundle = new Product(['code' => 'BNDL-18', 'name' => 'Scented Home Bundle']);
    $bundle->setRelation('family', new ProductCategory(['code' => 'bundles', 'name' => 'Product Bundles']));
    $bundle->setRelation('subDepartment', null);
    $bundle->setRelation('department', $department);

    $oil = new Product(['code' => 'EO-01', 'name' => 'Lavender Oil']);
    $oil->setRelation('family', new ProductCategory(['code' => 'EO', 'name' => 'Essential Oils']));
    $oil->setRelation('subDepartment', null);
    $oil->setRelation('department', $department);

    $orphan = new Product(['code' => 'X-1', 'name' => 'Loose Item']);
    $orphan->setRelation('family', null);
    $orphan->setRelation('subDepartment', null);
    $orphan->setRelation('department', null);

    $unnamedFamily = new Product(['code' => 'X-2', 'name' => 'Loose Candle']);
    $unnamedFamily->setRelation('family', new ProductCategory(['code' => 'X', 'name' => null]));
    $unnamedFamily->setRelation('subDepartment', null);
    $unnamedFamily->setRelation('department', null);

    expect(StoreEbayProduct::categoryKeywords($bundle))->toBe(['Aromatherapy'])
        ->and(StoreEbayProduct::categoryKeywords($unnamedFamily))->toBe(['Loose Candle'])
        ->and(StoreEbayProduct::describingFamilyName($bundle))->toBeNull()
        ->and(StoreEbayProduct::categoryKeywords($oil))->toBe(['Essential Oils Aromatherapy', 'Essential Oils', 'Aromatherapy'])
        ->and(StoreEbayProduct::categoryKeywords($orphan))->toBe(['Loose Item']);
});

test('a family name is first sent to eBay with its department, so body brushes are not suggested into cleaning categories', function () {
    $brush = new Product(['code' => 'Scrub-20', 'name' => 'Body Brush']);
    $brush->setRelation('family', new ProductCategory(['code' => 'Scrub', 'name' => 'Brush Scrub & Scrape']));
    $brush->setRelation('subDepartment', new ProductCategory(['code' => 'facial', 'name' => 'Facial & Bath Accessories']));
    $brush->setRelation('department', new ProductCategory(['code' => 'bath', 'name' => 'Bath & Body']));

    expect(StoreEbayProduct::categoryKeywords($brush))->toBe(['Brush Scrub & Scrape Bath & Body', 'Brush Scrub & Scrape', 'Facial & Bath Accessories', 'Bath & Body']);
});
