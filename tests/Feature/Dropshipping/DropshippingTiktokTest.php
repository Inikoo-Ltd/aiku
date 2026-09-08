<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:41:12 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Tiktok\Order\FulfillOrderToTiktok;
use App\Actions\Dropshipping\Tiktok\Order\GetTiktokOrdersApi;
use App\Actions\Dropshipping\Tiktok\Product\CheckTiktokPortfolio;
use App\Actions\Dropshipping\Tiktok\Product\MatchPortfolioToCurrentTiktokProduct;
use App\Actions\Dropshipping\Tiktok\Product\StoreProductToTiktok;
use App\Actions\Dropshipping\Tiktok\Product\UpdateInventoryTiktokProducts;
use App\Actions\Dropshipping\Tiktok\Product\UpdateTiktokInventory;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\Tiktok\User\CheckTiktokChannel;
use App\Actions\Dropshipping\Tiktok\User\StoreTiktokUser;
use App\Actions\Dropshipping\Tiktok\User\UpdateTiktokUser;
use App\Actions\Dropshipping\Tiktok\Webhooks\HandleOrderIncomingTiktok;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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

    $this->customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

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

/**
 * @return array{code: int, message: string, request_id: string, data: array}
 */
function tiktokOk(array $data = []): array
{
    return ['code' => 0, 'message' => 'Success', 'request_id' => Str::random(12), 'data' => $data];
}

/**
 * @return array{code: int, message: string, request_id: string}
 */
function tiktokError(int $code, string $message): array
{
    return ['code' => $code, 'message' => $message, 'request_id' => Str::random(12)];
}

/**
 * Every call leaves through the trait, so a route is the path fragment TikTok would see. An
 * unmatched URL is a stray request and fails the test.
 *
 * @param  array<string, array|callable>  $routes  path fragment => response array or callable(Request): array|null
 */
function fakeTiktok(array $routes): void
{
    app()->instance('tiktok-fake-routes', $routes);

    if (app()->bound('tiktok-fake-registered')) {
        return;
    }

    app()->instance('tiktok-fake-registered', true);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        foreach (app('tiktok-fake-routes') as $fragment => $response) {
            if (str_contains($request->url(), $fragment)) {
                $body = is_callable($response) ? $response($request) : $response;

                return $body === null ? null : Http::response($body);
            }
        }

        return null;
    });
}

function tiktokLastLog(Portfolio $portfolio): PlatformPortfolioLogs
{
    return PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->latest('id')->firstOrFail();
}

function tiktokShopFixtures(TiktokUser $tiktokUser): array
{
    return [
        '/authorization/' => tiktokOk(['shops' => [[
            'id' => $tiktokUser->tiktok_shop_id ?: '7000714532876273420',
            'name' => 'Maomao beauty shop',
            'region' => 'GB',
            'seller_type' => 'LOCAL',
            'cipher' => $tiktokUser->tiktok_shop_chiper ?: 'GCP_XF90igAAAABh00qsWgtvOiGFNqyubMt3',
            'code' => 'CNGBCBA4LLU8'
        ]]]),
        '/logistics/' => tiktokOk(['warehouses' => [
            ['id' => 'WH-OTHER', 'name' => 'Other', 'effect_status' => 'ENABLED', 'type' => 'SALES_WAREHOUSE', 'is_default' => false],
            ['id' => 'WH-DEFAULT', 'name' => 'Main', 'effect_status' => 'ENABLED', 'type' => 'SALES_WAREHOUSE', 'is_default' => true],
        ]]),
        '/event/' => tiktokOk([]),
    ];
}

function tiktokChannel(Customer $customer, array $overrides = []): TiktokUser
{
    $shopId = 'SHOP'.Str::upper(Str::random(6));

    $tiktokUser = StoreTiktokUser::make()->action($customer, array_merge([
        'tiktok_id' => 'open-'.Str::random(8),
        'name' => 'Maomao beauty shop',
        'username' => 'maomao-'.Str::random(4),
        'access_token' => 'access-token',
        'access_token_expire_in' => (string) now()->addDay()->timestamp,
        'refresh_token' => 'refresh-token',
        'refresh_token_expire_in' => (string) now()->addMonth()->timestamp,
        'tiktok_warehouse_id' => 'WH1',
        'tiktok_shop_id' => $shopId,
        'tiktok_shop_chiper' => 'CIPHER-'.$shopId,
    ], $overrides));

    $tiktokUser->update([
        'data' => ['authorized_shop' => [['id' => $tiktokUser->tiktok_shop_id, 'cipher' => $tiktokUser->tiktok_shop_chiper, 'name' => $tiktokUser->name]]]
    ]);

    UpdateCustomerSalesChannel::run($tiktokUser->customerSalesChannel, [
        'state' => CustomerSalesChannelStateEnum::READY,
        'platform_status' => true,
        'can_connect_to_platform' => true,
        'exist_in_platform' => true,
    ]);

    return $tiktokUser->refresh();
}

function tiktokListedPortfolio(TiktokUser $tiktokUser, $product, string $platformProductId, string $skuId = 'SKU1'): Portfolio
{
    fakeTiktok(['/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0])]);

    $portfolio = StorePortfolio::make()->action($tiktokUser->customerSalesChannel, $product, []);
    $portfolio->update([
        'platform_product_id' => $platformProductId,
        'platform_product_variant_id' => $platformProductId,
        'platform_status' => true,
        'exist_in_platform' => true,
        'has_valid_platform_product_id' => true,
        'data' => ['tiktok_product' => ['id' => $platformProductId, 'skus' => [['id' => $skuId]]]]
    ]);

    return $portfolio->refresh();
}

/**
 * One line item per unit sold, as TikTok sends them.
 *
 * @param  array<int, array{product_id: string, units?: int}>  $lines
 */
function tiktokOrder(string $orderId, array $lines, array $overrides = []): array
{
    $lineItems = [];
    foreach ($lines as $index => $line) {
        for ($unit = 0; $unit < Arr::get($line, 'units', 1); $unit++) {
            $lineItems[] = [
                'id' => $orderId.'-'.$index.'-'.$unit,
                'sku_id' => 'SKU-'.$line['product_id'],
                'product_id' => $line['product_id'],
                'product_name' => 'Listed product '.$line['product_id'],
                'seller_sku' => 'seller-'.$line['product_id'],
                'sale_price' => '9.99',
                'currency' => 'GBP',
                'display_status' => 'TO_SHIP',
                'package_status' => 'TO_FULFILL',
            ];
        }
    }

    return array_merge([
        'id' => $orderId,
        'status' => 'AWAITING_SHIPMENT',
        'user_id' => '7021436810468230477',
        'buyer_email' => 'v2b2V5@chat.seller.tiktok.com',
        'shipping_type' => 'SELLER',
        'delivery_option_id' => '7091146663229654785',
        'payment' => ['currency' => 'GBP', 'total_amount' => '19.98'],
        'recipient_address' => [
            'full_address' => '5800 Bristol Pkwy, Suite 100, Ribbleton PR1 5AA',
            'phone_number' => '(+44)7***1234',
            'name' => 'David Kong',
            'first_name' => 'David',
            'last_name' => 'Kong',
            'region_code' => 'GB',
            'postal_code' => 'PR1 5AA',
            'post_town' => 'Ribbleton',
            'address_line1' => '5800 Bristol Pkwy',
            'address_line2' => 'Suite 100',
            'district_info' => [['address_level_name' => 'Country', 'address_name' => 'United Kingdom', 'address_level' => 'L0']],
        ],
        'packages' => [['id' => 'PKG-'.$orderId]],
        'line_items' => $lineItems,
    ], $overrides);
}

function tiktokWebhookPayload(TiktokUser $tiktokUser, string $orderId, string $status = 'AWAITING_SHIPMENT'): array
{
    return [
        'type' => 1,
        'tts_notification_id' => Str::random(10),
        'shop_id' => $tiktokUser->tiktok_shop_id,
        'timestamp' => now()->timestamp,
        'data' => ['order_id' => $orderId, 'order_status' => $status, 'is_on_hold_order' => false, 'update_time' => now()->timestamp],
    ];
}

/**
 * @param  array<string, array>  $tiktokProducts  product id => get product data
 */
function fakeTiktokOrderIntake(array $orders, array $tiktokProducts = []): void
{
    $routes = [];
    foreach ($tiktokProducts as $productId => $product) {
        $routes['/product/202309/products/'.$productId] = tiktokOk(array_merge(['id' => $productId, 'status' => 'ACTIVATE'], $product));
    }
    $routes['/product/202309/products/'] = tiktokError(12052901, 'product not found');
    $routes['/order/202309/orders/search'] = tiktokOk(['orders' => array_values($orders), 'total_count' => count($orders), 'next_page_token' => '']);
    $routes['/order/202309/orders'] = function (Request $request) use ($orders) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $wanted = explode(',', (string) Arr::get($query, 'ids'));

        return tiktokOk(['orders' => array_values(array_filter($orders, fn ($order) => in_array($order['id'], $wanted)))]);
    };

    fakeTiktok($routes);
}

test('webhook for an awaiting shipment order creates the client, the address and one transaction per product', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-LISTED');
    $orderId    = '5764614130387857'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [tiktokOrder($orderId, [['product_id' => 'TT-LISTED', 'units' => 2]])],
        ['TT-LISTED' => ['external_product_id' => (string) $portfolio->id]]
    );

    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId));

    $order = Order::where('customer_id', $this->customer->id)->where('platform_order_id', $orderId)->first();

    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), '/product/202309/products/TT-LISTED'))->count())->toBe(2)
        ->and($order)->toBeInstanceOf(Order::class)
        ->and($order->customer_sales_channel_id)->toBe($tiktokUser->customer_sales_channel_id)
        ->and($order->platform_id)->toBe($tiktokUser->platform_id)
        ->and($order->is_shipping_by_external)->toBeFalse()
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and(Arr::get($order->data, 'tiktok_order.id'))->toBe($orderId)
        ->and($order->transactions()->count())->toBe(1)
        ->and((float) $order->transactions()->first()->quantity_ordered)->toBe(2.0)
        ->and($order->transactions()->first()->model_id)->toBe($this->product->id)
        ->and($order->transactions()->first()->platform_transaction_id)->toBe($orderId.'-0-0')
        ->and($order->customerClient->contact_name)->toBe('David Kong')
        ->and($order->customerClient->email)->toBe('v2b2V5@chat.seller.tiktok.com')
        ->and($order->customerClient->address->country_code)->toBe('GB')
        ->and($order->customerClient->address->locality)->toBe('Ribbleton')
        ->and($order->deliveryAddress->postal_code)->toBe('PR1 5AA')
        ->and($tiktokUser->debugWebhooks()->count())->toBe(1);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/order/202309/orders')
        && str_contains($request->url(), 'ids='.$orderId)
        && str_contains($request->url(), 'shop_cipher='.$tiktokUser->tiktok_shop_chiper)
        && str_contains($request->url(), 'sign=')
        && $request->hasHeader('x-tts-access-token', 'access-token'));

    return [$tiktokUser, $order];
});

test('the same webhook delivered twice does not create a second order', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-TWICE');
    $orderId    = '5764614130387858'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [tiktokOrder($orderId, [['product_id' => 'TT-TWICE']])],
        ['TT-TWICE' => ['external_product_id' => (string) $portfolio->id]]
    );

    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId));
    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId));
    GetTiktokOrdersApi::make()->handle($tiktokUser);

    expect(Order::where('customer_id', $this->customer->id)->where('platform_order_id', $orderId)->count())->toBe(1)
        ->and($tiktokUser->debugWebhooks()->count())->toBe(3);
});

test('an order whose products are not in the portfolio is not imported and unknown lines are dropped from a mixed order', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-KNOWN');
    $unknownId  = '5764614130387859'.random_int(10, 99);
    $mixedId    = '5764614130387860'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [
            tiktokOrder($unknownId, [['product_id' => 'TT-UNKNOWN']]),
            tiktokOrder($mixedId, [['product_id' => 'TT-UNKNOWN'], ['product_id' => 'TT-KNOWN', 'units' => 3]]),
        ],
        [
            'TT-KNOWN' => ['external_product_id' => (string) $portfolio->id],
            'TT-UNKNOWN' => ['external_product_id' => 'seller-own-ref'],
        ]
    );

    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $unknownId));
    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $mixedId));

    $mixed = Order::where('customer_id', $this->customer->id)->where('platform_order_id', $mixedId)->first();

    expect(Order::where('customer_id', $this->customer->id)->where('platform_order_id', $unknownId)->exists())->toBeFalse()
        ->and($mixed)->toBeInstanceOf(Order::class)
        ->and($mixed->transactions()->count())->toBe(1)
        ->and((float) $mixed->transactions()->first()->quantity_ordered)->toBe(3.0);
});

test('a numeric external product id pointing at another channel portfolio never imports that channel product', function () {
    $tiktokUser = tiktokChannel($this->customer);

    $otherCustomer  = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $otherUser      = tiktokChannel($otherCustomer);
    $otherPortfolio = tiktokListedPortfolio($otherUser, $this->product, 'TT-OTHER');
    $orderId        = '5764614130387861'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [tiktokOrder($orderId, [['product_id' => 'TT-SELLER-LISTED']])],
        ['TT-SELLER-LISTED' => ['external_product_id' => (string) $otherPortfolio->id]]
    );

    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId));

    expect(Order::where('platform_order_id', $orderId)->exists())->toBeFalse();
});

test('a cancelled webhook cancels the imported order and leaves a note', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-CANCEL');
    $orderId    = '5764614130387862'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [tiktokOrder($orderId, [['product_id' => 'TT-CANCEL']])],
        ['TT-CANCEL' => ['external_product_id' => (string) $portfolio->id]]
    );
    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId));

    $order = Order::where('customer_id', $this->customer->id)->where('platform_order_id', $orderId)->firstOrFail();
    expect($order->state)->toBe(OrderStateEnum::SUBMITTED);

    fakeTiktokOrderIntake([tiktokOrder($orderId, [['product_id' => 'TT-CANCEL']], ['status' => 'CANCELLED', 'cancel_reason' => 'Buyer changed mind'])]);
    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId, 'CANCELLED'));

    $order->refresh();
    expect($order->state)->toBe(OrderStateEnum::CANCELLED)
        ->and($order->internal_notes)->toBe('Order cancelled by Tiktok');

    HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, $orderId, 'CANCELLED'));
    expect($order->refresh()->state)->toBe(OrderStateEnum::CANCELLED);
});

test('polling the order list only asks for awaiting shipment orders and imports them', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-POLL');
    $orderId    = '5764614130387863'.random_int(10, 99);

    fakeTiktokOrderIntake(
        [tiktokOrder($orderId, [['product_id' => 'TT-POLL']])],
        ['TT-POLL' => ['external_product_id' => (string) $portfolio->id]]
    );

    GetTiktokOrdersApi::make()->handle($tiktokUser);

    expect(Order::where('customer_id', $this->customer->id)->where('platform_order_id', $orderId)->exists())->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/order/202309/orders/search')
        && str_contains($request->url(), 'page_size=100')
        && $request->data() === ['order_status' => 'AWAITING_SHIPMENT']);
});

test('dispatch sends the tracking number with the shipping provider matched on the shipper', function (array $fixtures) {
    [$tiktokUser, $order] = $fixtures;

    fakeTiktok([
        '/shipping_providers' => tiktokOk(['shipping_providers' => [
            ['id' => '7117858858072016686', 'name' => 'Royal Mail'],
            ['id' => '7117858858072016687', 'name' => 'DPD UK'],
        ]]),
        '/shipping_info/update' => tiktokOk([]),
    ]);

    $shipment = new Shipment(['tracking' => 'TRK-0001', 'trade_as' => 'DPD']);

    $response = FulfillOrderToTiktok::make()->fulfillBySeller($tiktokUser, $order->platform_order_id, $order, $shipment);

    expect(Arr::get($response, 'message'))->toBe('Success');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/delivery_options/7091146663229654785/shipping_providers'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/fulfillment/202309/orders/'.$order->platform_order_id.'/shipping_info/update')
        && $request->data() === ['tracking_number' => 'TRK-0001', 'shipping_provider_id' => '7117858858072016687']);
})->depends('webhook for an awaiting shipment order creates the client, the address and one transaction per product');

test('dispatch with an unknown shipper sends the tracking without a provider and a tiktok rejection comes back as an error', function (array $fixtures) {
    [$tiktokUser, $order] = $fixtures;

    fakeTiktok([
        '/shipping_providers' => tiktokOk(['shipping_providers' => [['id' => '7117858858072016686', 'name' => 'Royal Mail']]]),
        '/shipping_info/update' => tiktokError(21001001, 'shipping provider id is invalid'),
    ]);

    $response = FulfillOrderToTiktok::make()->fulfillBySeller($tiktokUser, $order->platform_order_id, $order, new Shipment(['tracking' => 'TRK-0002']));

    expect(Arr::get($response, 'error'))->toBeTrue()
        ->and(Arr::get($response, 'data'))->toBe('shipping provider id is invalid')
        ->and(FulfillOrderToTiktok::pickShippingProvider([['id' => '1', 'name' => 'Royal Mail']], null))->toBeNull()
        ->and(FulfillOrderToTiktok::pickShippingProvider([['id' => '1', 'name' => 'Royal Mail']], 'royal'))->toBe(['id' => '1', 'name' => 'Royal Mail']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/shipping_info/update')
        && $request->data() === ['tracking_number' => 'TRK-0002', 'shipping_provider_id' => null]);
})->depends('webhook for an awaiting shipment order creates the client, the address and one transaction per product');

test('dispatch without a delivery option or a shipment sends what it can and never throws', function (array $fixtures) {
    [$tiktokUser, $order] = $fixtures;

    fakeTiktok(['/shipping_info/update' => tiktokOk([])]);
    $orderWithoutOption = clone $order;
    $orderWithoutOption->data = ['tiktok_order' => ['id' => $order->platform_order_id]];

    $shipment = new Shipment(['tracking' => 'TRK-0003']);
    $shipment->setRelation('shipper', new Shipper(['name' => 'Royal Mail', 'trade_as' => 'RM']));

    $response = FulfillOrderToTiktok::make()->fulfillBySeller($tiktokUser, $order->platform_order_id, $orderWithoutOption, $shipment);
    expect(Arr::get($response, 'message'))->toBe('Success');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/shipping_info/update')
        && $request->data() === ['tracking_number' => 'TRK-0003', 'shipping_provider_id' => null]);

    expect($order->deliveryNotes()->count())->toBe(0)
        ->and(FulfillOrderToTiktok::run($order))->toBeNull();
    Http::assertSentCount(1);

    $order->update(['is_shipping_by_external' => true]);
    expect(FulfillOrderToTiktok::run($order->refresh()))->toBeNull();
    Http::assertSentCount(1);
    $order->update(['is_shipping_by_external' => false]);
})->depends('webhook for an awaiting shipment order creates the client, the address and one transaction per product');

test('uploading a portfolio creates the tiktok product from the category rules and saves the listing', function () {
    $tiktokUser = tiktokChannel($this->customer);

    fakeTiktok(['/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0])]);
    $portfolio = StorePortfolio::make()->action($tiktokUser->customerSalesChannel, $this->product, []);
    $portfolio->update([
        'sku' => 'CROCHET-1',
        'customer_price' => 12.5,
        'customer_product_name' => 'Crochet winter set',
        'customer_description' => 'Warm and cosy',
    ]);
    expect($portfolio->platform_status)->toBeFalse();

    $productId = '17295929697122070'.random_int(10, 99);

    fakeTiktok([
        '/categories/recommend' => tiktokOk(['leaf_category_id' => '852312', 'categories' => [['id' => '852312', 'name' => 'Knitwear', 'is_leaf' => true]]]),
        '/categories/852312/rules' => tiktokOk(['product_certifications' => [['id' => 'CERT-OPT', 'name' => 'Optional cert', 'is_required' => false]], 'responsible_person' => ['is_required' => false]]),
        '/categories/852312/attributes' => tiktokOk(['attributes' => [
            ['id' => '100392', 'name' => 'Pattern', 'is_required' => true, 'is_customizable' => true, 'values' => [['id' => '7001', 'name' => 'Plain'], ['id' => '7002', 'name' => 'No']]],
            ['id' => '100393', 'name' => 'Occasion', 'is_required' => false, 'values' => [['id' => '7003', 'name' => 'Party']]],
        ]]),
        '/product/202309/products/'.$productId => tiktokOk([
            'id' => $productId,
            'status' => 'ACTIVATE',
            'external_product_id' => (string) $portfolio->id,
            'audit' => ['status' => 'AUDITING'],
            'skus' => [['id' => '1729592969712207012', 'seller_sku' => 'CROCHET-1', 'inventory' => [['warehouse_id' => 'WH1', 'quantity' => 10]]]],
        ]),
        '/product/202309/products' => tiktokOk([
            'product_id' => $productId,
            'skus' => [['id' => '1729592969712207012', 'seller_sku' => 'CROCHET-1']],
            'warnings' => [['message' => 'The [brand_id] field is incorrect and has been automatically cleared by the system.']],
        ]),
    ]);

    $portfolio = StoreProductToTiktok::run($portfolio);

    expect($portfolio->platform_product_id)->toBe($productId)
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->errors_response)->toBeNull()
        ->and($portfolio->upload_warning)->toContain('[brand_id]')
        ->and(Arr::get($portfolio->data, 'tiktok_product.skus.0.id'))->toBe('1729592969712207012')
        ->and($portfolio->number_platform_possible_matches)->toBe(0)
        ->and(tiktokLastLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::OK);

    Http::assertSent(function (Request $request) use ($portfolio) {
        if (!str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/product/202309/products') || $request->method() !== 'POST') {
            return false;
        }

        $body = $request->data();

        return $body['title'] === 'Crochet winter set'
            && $body['description'] === 'Warm and cosy'
            && $body['category_id'] === '852312'
            && $body['external_product_id'] === (string) $portfolio->id
            && $body['product_attributes'] === [['id' => '100392', 'values' => [['id' => '7002', 'name' => 'No']]]]
            && $body['product_certifications'] === []
            && $body['skus'][0]['seller_sku'] === 'CROCHET-1'
            && $body['skus'][0]['price'] === ['amount' => '12.5', 'currency' => $this->shop->currency->code]
            && $body['skus'][0]['inventory'][0]['warehouse_id'] === 'WH1'
            && $body['package_dimensions']['unit'] === 'CENTIMETER';
    });
});

test('a rejected upload keeps the portfolio unlisted with the tiktok message as the error', function () {
    $tiktokUser = tiktokChannel($this->customer);

    fakeTiktok(['/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0])]);
    $portfolio = StorePortfolio::make()->action($tiktokUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'REJECTED-1', 'customer_price' => 5]);

    fakeTiktok([
        '/categories/recommend' => tiktokOk(['leaf_category_id' => '600009']),
        '/rules' => tiktokOk(['product_certifications' => []]),
        '/attributes' => tiktokOk(['attributes' => []]),
        '/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0]),
        '/product/202309/products' => tiktokError(12052901, 'The weight of the product cannot be 0'),
    ]);

    $portfolio = StoreProductToTiktok::run($portfolio);

    expect($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->exist_in_platform)->toBeFalse()
        ->and(Arr::get($portfolio->errors_response, 'message'))->toBe('The weight of the product cannot be 0')
        ->and(tiktokLastLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);
});

test('a portfolio without a listing is matched by seller sku in the shape the retina table expects and can be adopted', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $productId  = '17295929697122071'.random_int(10, 99);

    fakeTiktok([
        '/product/202309/products/search' => tiktokOk(['products' => [
            ['id' => $productId, 'title' => 'Short Boat Invisible Socks', 'status' => 'ACTIVATE', 'skus' => [['id' => 'SKU-A', 'seller_sku' => 'SOCKS-1']]],
            ['id' => 'GONE', 'title' => 'Deleted listing', 'status' => 'DELETED', 'skus' => [['id' => 'SKU-B', 'seller_sku' => 'SOCKS-1']]],
        ], 'total_count' => 2]),
        '/product/202309/products/'.$productId => tiktokOk(['id' => $productId, 'title' => 'Short Boat Invisible Socks', 'status' => 'ACTIVATE', 'external_product_id' => 'seller-ref', 'skus' => [['id' => 'SKU-A', 'seller_sku' => 'SOCKS-1']]]),
    ]);

    $portfolio = StorePortfolio::make()->action($tiktokUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['sku' => 'SOCKS-1']);
    $portfolio = CheckTiktokPortfolio::run($portfolio)->refresh();

    expect($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->has_valid_platform_product_id)->toBeFalse()
        ->and($portfolio->number_platform_possible_matches)->toBe(1)
        ->and(Arr::get($portfolio->platform_possible_matches, 'matches_labels'))->toBe(['Short Boat Invisible Socks'])
        ->and(Arr::get($portfolio->platform_possible_matches, 'raw_data'))->toBe([['id' => $productId, 'name' => 'Short Boat Invisible Socks', 'images' => []]]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/products/search') && $request->data() === ['seller_skus' => ['SOCKS-1']]);

    MatchPortfolioToCurrentTiktokProduct::run($portfolio, ['platform_product_id' => Arr::get($portfolio->platform_possible_matches, 'raw_data.0.id')]);
    $portfolio->refresh();

    expect($portfolio->platform_product_id)->toBe($productId)
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->number_platform_possible_matches)->toBe(0)
        ->and(Arr::get($portfolio->data, 'tiktok_product.skus.0.id'))->toBe('SKU-A');
});

test('a listing tiktok no longer knows is marked broken and searched again by sku, a failed review keeps the listing with the reasons', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-STALE');
    $portfolio->update(['sku' => 'STALE-1']);

    fakeTiktok([
        '/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0]),
        '/product/202309/products/TT-STALE' => tiktokError(12052901, 'product not found'),
    ]);
    $portfolio = CheckTiktokPortfolio::run($portfolio)->refresh();

    expect($portfolio->has_valid_platform_product_id)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeFalse()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->number_platform_possible_matches)->toBe(0);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/products/search') && $request->data() === ['seller_skus' => ['STALE-1']]);

    fakeTiktok([
        '/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0]),
        '/product/202309/products/TT-STALE' => tiktokOk(['id' => 'TT-STALE', 'status' => 'DELETED', 'skus' => [['id' => 'SKU1']]]),
    ]);
    Http::assertSentCount(2);
    $portfolio = CheckTiktokPortfolio::run($portfolio)->refresh();
    expect($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->platform_status)->toBeFalse();

    fakeTiktok([
        '/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0]),
        '/product/202309/products/TT-STALE' => tiktokOk([
            'id' => 'TT-STALE',
            'status' => 'FAILED',
            'audit' => ['status' => 'FAILED'],
            'audit_failed_reasons' => [['position' => 'product', 'reasons' => ['violate listing rules'], 'suggestions' => ['Please check and resubmit.']]],
            'skus' => [['id' => 'SKU1']],
        ]),
    ]);
    $portfolio = CheckTiktokPortfolio::run($portfolio)->refresh();
    expect($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->upload_warning)->toBe('violate listing rules Please check and resubmit.');
});

test('a never uploaded tiktok portfolio counts as broken on the channel', function () {
    $tiktokUser = tiktokChannel($this->customer);

    fakeTiktok(['/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0])]);
    StorePortfolio::make()->action($tiktokUser->customerSalesChannel, $this->product, []);

    $channel = $tiktokUser->customerSalesChannel->refresh();
    expect($channel->number_portfolios)->toBe(1)
        ->and($channel->number_portfolio_broken)->toBe(1);
});

test('the stock push sends the capped quantity for the listed sku at the channel warehouse and records failures', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $channel    = $tiktokUser->customerSalesChannel;
    $portfolio  = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-STOCK', 'SKU-STOCK');
    $this->product->update(['available_quantity' => 250, 'available_quantity_updated_at' => now()->subDay()]);
    $channel->update(['max_quantity_advertise' => 80, 'stock_threshold' => 5, 'stock_update' => true]);

    fakeTiktok(['/inventory/update' => tiktokOk(['errors' => []])]);
    UpdateTiktokInventory::run($portfolio, $channel);
    $portfolio->refresh();

    expect($portfolio->last_stock_value)->toBe(80)
        ->and($portfolio->stock_last_updated_at)->not->toBeNull()
        ->and($channel->refresh()->ban_stock_update_util)->toBeNull();
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/product/202309/products/TT-STOCK/inventory/update')
        && $request->data() === ['skus' => [['id' => 'SKU-STOCK', 'inventory' => [['warehouse_id' => 'WH1', 'quantity' => 80]]]]]);

    $this->product->update(['available_quantity' => 60]);
    fakeTiktok(['/inventory/update' => tiktokOk(['errors' => [['code' => 12052990, 'message' => 'Check failed', 'detail' => ['sku_id' => 'SKU-STOCK', 'extra_errors' => [['warehouse_id' => 'WH1', 'code' => 12052097, 'message' => 'The warehouse does not exist']]]]]])]);
    UpdateTiktokInventory::run($portfolio, $channel);
    $portfolio->refresh();

    expect($portfolio->last_stock_value)->toBe(80)
        ->and($portfolio->stock_last_fail_updated_at)->not->toBeNull()
        ->and($channel->refresh()->ban_stock_update_util?->gt(now()))->toBeTrue()
        ->and(tiktokLastLog($portfolio)->response)->toContain('Check failed');

    $portfolio->update(['stock_last_fail_updated_at' => null]);
    fakeTiktok(['/inventory/update' => tiktokError(105002, 'Access token is invalid')]);
    UpdateTiktokInventory::run($portfolio, $channel);
    $portfolio->refresh();

    expect($portfolio->last_stock_value)->toBe(80)
        ->and($portfolio->stock_last_fail_updated_at)->not->toBeNull()
        ->and(tiktokLastLog($portfolio)->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL)
        ->and(tiktokLastLog($portfolio)->response)->toContain('Access token is invalid');
});

test('the hourly stock sync only queues listed portfolios of open channels tiktok still authorises', function () {
    Queue::fake();
    $tiktokUser = tiktokChannel($this->customer);
    $channel    = $tiktokUser->customerSalesChannel;
    $channel->update(['stock_update' => true]);
    $this->product->update(['available_quantity' => 250, 'available_quantity_updated_at' => now()->subDay()]);

    $listed = tiktokListedPortfolio($tiktokUser, $this->product, 'TT-SYNC');

    fakeTiktok(tiktokShopFixtures($tiktokUser));
    UpdateInventoryTiktokProducts::run($channel);
    UpdateTiktokInventory::assertPushed(fn (UpdateTiktokInventory $job, array $arguments) => $arguments[0]->id === $listed->id);
    UpdateTiktokInventory::assertPushed(1);

    $listed->update(['platform_status' => false]);
    UpdateInventoryTiktokProducts::run($channel);
    UpdateTiktokInventory::assertPushed(1);
    $listed->update(['platform_status' => true]);

    fakeTiktok(['/authorization/' => tiktokError(105002, 'Access token is invalid')]);
    UpdateInventoryTiktokProducts::run($channel);
    UpdateTiktokInventory::assertPushed(1);
    expect($channel->refresh()->ban_stock_update_util?->gt(now()))->toBeTrue();

    $channel->update(['ban_stock_update_util' => null, 'status' => CustomerSalesChannelStatusEnum::CLOSED]);
    fakeTiktok(tiktokShopFixtures($tiktokUser));
    UpdateInventoryTiktokProducts::run($channel);
    UpdateTiktokInventory::assertPushed(1);
});

test('the oauth callback stores the seller with its shop and default warehouse and a repeat authorisation reuses the channel', function () {
    $tokenResponse = fn () => ['code' => 0, 'message' => 'success', 'request_id' => 'r', 'data' => [
        'access_token' => 'fresh-access',
        'access_token_expire_in' => now()->addDay()->timestamp,
        'refresh_token' => 'fresh-refresh',
        'refresh_token_expire_in' => now()->addMonth()->timestamp,
        'open_id' => 'OPEN-'.$this->customer->id,
        'seller_name' => 'Maomao beauty shop',
        'seller_base_region' => 'GB',
        'user_type' => 0,
    ]];

    fakeTiktok(array_merge(['/api/v2/token/get' => $tokenResponse()], tiktokShopFixtures(new TiktokUser())));

    $response = AuthenticateTiktokAccount::make()->handle(['code' => 'auth-code', 'state' => base64_encode($this->customer->id)]);

    $tiktokUser = TiktokUser::where('customer_id', $this->customer->id)->firstOrFail();
    $channel    = $tiktokUser->customerSalesChannel;

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toContain('tiktok/onboarding?tiktok_code=')
        ->and($tiktokUser->tiktok_id)->toBe('OPEN-'.$this->customer->id)
        ->and($tiktokUser->access_token)->toBe('fresh-access')
        ->and($tiktokUser->tiktok_warehouse_id)->toBe('WH-DEFAULT')
        ->and($tiktokUser->tiktok_shop_id)->toBeNull()
        ->and(Arr::get($tiktokUser->data, 'authorized_shop.0.cipher'))->toBe('GCP_XF90igAAAABh00qsWgtvOiGFNqyubMt3')
        ->and($channel->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($channel->can_connect_to_platform)->toBeTrue()
        ->and($channel->platform_status)->toBeFalse()
        ->and($channel->name)->toBe('Maomao beauty shop');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/event/202309/webhooks')
        && $request->method() === 'PUT'
        && Arr::get($request->data(), 'event_type') === 'ORDER_STATUS_CHANGE');

    fakeTiktok(['/product/202309/products/search' => tiktokOk(['products' => [], 'total_count' => 0])]);
    $portfolio = StorePortfolio::make()->action($channel, $this->product, []);

    fakeTiktok(array_merge(['/api/v2/token/get' => $tokenResponse()], tiktokShopFixtures($tiktokUser)));
    AuthenticateTiktokAccount::make()->handle(['code' => 'auth-code-2', 'state' => base64_encode($this->customer->id)]);

    expect(TiktokUser::withTrashed()->where('customer_id', $this->customer->id)->count())->toBe(1)
        ->and($this->customer->customerSalesChannels()->count())->toBe(1);

    CloseCustomerSalesChannel::make()->handle($channel);
    expect($channel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($tiktokUser->fresh()->trashed())->toBeTrue()
        ->and($portfolio->refresh()->status)->toBeFalse();

    fakeTiktok(array_merge(['/api/v2/token/get' => $tokenResponse()], tiktokShopFixtures($tiktokUser)));
    AuthenticateTiktokAccount::make()->handle(['code' => 'auth-code-3', 'state' => base64_encode($this->customer->id)]);
    $channel->refresh();

    expect(TiktokUser::withTrashed()->where('customer_id', $this->customer->id)->count())->toBe(1)
        ->and($this->customer->customerSalesChannels()->count())->toBe(1)
        ->and($tiktokUser->fresh()->trashed())->toBeFalse()
        ->and($channel->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($channel->closed_at)->toBeNull()
        ->and($channel->name)->toBe('Maomao beauty shop')
        ->and($channel->user?->id)->toBe($tiktokUser->id)
        ->and($portfolio->refresh()->status)->toBeTrue();
});

test('picking the shop makes the channel ready and the check reads the shop name from the authorised list', function () {
    $tiktokUser = tiktokChannel($this->customer, ['tiktok_shop_id' => null, 'tiktok_shop_chiper' => null]);
    UpdateCustomerSalesChannel::run($tiktokUser->customerSalesChannel, ['state' => CustomerSalesChannelStateEnum::AUTHENTICATED, 'platform_status' => false]);

    fakeTiktok(tiktokShopFixtures($tiktokUser));
    $channel = CheckTiktokChannel::run($tiktokUser);
    expect($channel->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($channel->platform_status)->toBeFalse()
        ->and($channel->can_connect_to_platform)->toBeTrue();

    $tiktokUser = UpdateTiktokUser::make()->action($tiktokUser, ['tiktok_shop_id' => '7000714532876273420', 'tiktok_shop_chiper' => 'GCP_XF90igAAAABh00qsWgtvOiGFNqyubMt3']);
    $channel    = $tiktokUser->customerSalesChannel->refresh();

    expect($channel->state)->toBe(CustomerSalesChannelStateEnum::READY)
        ->and($channel->platform_status)->toBeTrue()
        ->and($channel->name)->toBe('Maomao beauty shop');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/logistics/202309/warehouses')
        && str_contains($request->url(), 'shop_cipher=GCP_XF90igAAAABh00qsWgtvOiGFNqyubMt3'));
});

test('an expired access token is refreshed before the call and a failed refresh keeps the current tokens', function () {
    $tiktokUser = tiktokChannel($this->customer, ['access_token_expire_in' => (string) now()->subMinute()->timestamp]);

    fakeTiktok(array_merge([
        '/api/v2/token/refresh' => ['code' => 0, 'message' => 'success', 'request_id' => 'r', 'data' => [
            'access_token' => 'renewed-access',
            'access_token_expire_in' => now()->addDay()->timestamp,
            'refresh_token' => 'renewed-refresh',
            'refresh_token_expire_in' => now()->addMonth()->timestamp,
            'open_id' => $tiktokUser->tiktok_id,
            'seller_name' => 'Maomao beauty shop',
        ]],
    ], tiktokShopFixtures($tiktokUser)));

    $shops = $tiktokUser->getAuthorizedShop();
    $tiktokUser->refresh();

    expect(Arr::get($shops, 'data.shops.0.id'))->toBe($tiktokUser->tiktok_shop_id)
        ->and($tiktokUser->access_token)->toBe('renewed-access')
        ->and($tiktokUser->refresh_token)->toBe('renewed-refresh')
        ->and((int) $tiktokUser->access_token_expire_in)->toBeGreaterThan(now()->timestamp);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v2/token/refresh') && str_contains($request->url(), 'refresh_token=refresh-token'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/authorization/202309/shops') && $request->hasHeader('x-tts-access-token', 'renewed-access'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/authorization/202309/shops') && $request->hasHeader('x-tts-access-token', 'access-token'));

    $tiktokUser->update(['access_token_expire_in' => (string) now()->subMinute()->timestamp]);
    fakeTiktok(array_merge([
        '/api/v2/token/refresh' => ['code' => 36004004, 'message' => 'refresh token is invalid or expired', 'request_id' => 'r', 'data' => null],
        '/authorization/' => tiktokError(105002, 'Access token is invalid'),
    ]));

    $shops = $tiktokUser->getAuthorizedShop();
    $tiktokUser->refresh();

    expect(Arr::get($shops, 'error'))->toBeTrue()
        ->and($tiktokUser->access_token)->toBe('renewed-access')
        ->and($tiktokUser->refresh_token)->toBe('renewed-refresh');

    $tiktokUser->update(['refresh_token_expire_in' => (string) now()->subMinute()->timestamp]);
    $tiktokUser->getAuthorizedShop();
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), '/api/v2/token/refresh'))->count())->toBe(2);
});

test('closing the channel soft deletes the tiktok user and stops the webhook from finding the shop', function () {
    $tiktokUser = tiktokChannel($this->customer);
    $channel    = $tiktokUser->customerSalesChannel;

    CloseCustomerSalesChannel::make()->handle($channel);

    expect($channel->refresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($tiktokUser->fresh()->trashed())->toBeTrue()
        ->and(TiktokUser::where('tiktok_shop_id', $tiktokUser->tiktok_shop_id)->exists())->toBeFalse();

    fakeTiktok([]);
    expect(fn () => HandleOrderIncomingTiktok::run(tiktokWebhookPayload($tiktokUser, '1')))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    Http::assertNothingSent();
});
