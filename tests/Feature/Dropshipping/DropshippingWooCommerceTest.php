<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 11:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\Order\RetryOrderImport;
use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\WooCommerce\CallbackRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\CheckTemporaryWooUserApiKeys;
use App\Actions\Dropshipping\WooCommerce\CheckWooChannel;
use App\Actions\Dropshipping\WooCommerce\Clients\GetRetinaCustomerClientFromWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Orders\FetchWooUserOrders;
use App\Actions\Dropshipping\WooCommerce\Orders\FulfillOrderToWooCommerce;
use App\Actions\Dropshipping\WooCommerce\PingActiveWooChannel;
use App\Actions\Dropshipping\WooCommerce\Product\CheckWooPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\GetProductForWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Product\GetWooListedSkus;
use App\Actions\Dropshipping\WooCommerce\Product\MatchBulkNewProductToCurrentWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Product\StoreNewProductToCurrentWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooProduct;
use App\Actions\Dropshipping\WooCommerce\ReviveInActiveWooChannel;
use App\Actions\Dropshipping\WooCommerce\StoreTemporaryWooUser;
use App\Actions\Dropshipping\WooCommerce\StoreWooCommerceUser;
use App\Actions\Maintenance\Dropshipping\RepairWooChannelReconnects;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Dropshipping\OrderImportRetryStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

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

const WOO_STORE_URL = 'https://shop.example.test';

function wooCustomer(Shop $shop): Customer
{
    return StoreCustomer::make()->action($shop, Customer::factory()->definition());
}

function wooConnect(Customer $customer, array $data = []): WooCommerceUser
{
    return StoreWooCommerceUser::run($customer, array_merge([
        'name'            => 'woo-'.Str::lower(Str::random(6)),
        'store_url'       => WOO_STORE_URL,
        'consumer_key'    => 'ck_test',
        'consumer_secret' => 'cs_test',
    ], $data));
}

function wooSettingsGroups(): array
{
    return [
        ['id' => 'general', 'label' => 'General', 'description' => '', 'parent_id' => '', 'sub_groups' => []],
        ['id' => 'products', 'label' => 'Products', 'description' => '', 'parent_id' => '', 'sub_groups' => []],
    ];
}

function wooError(string $code, string $message, int $status): \GuzzleHttp\Promise\PromiseInterface
{
    return Http::response(['code' => $code, 'message' => $message, 'data' => ['status' => $status]], $status);
}

function wooProduct(int $id, array $overrides = []): array
{
    return array_merge([
        'id'             => $id,
        'name'           => 'Listed product '.$id,
        'slug'           => 'listed-product-'.$id,
        'permalink'      => WOO_STORE_URL.'/product/listed-product-'.$id.'/',
        'type'           => 'simple',
        'status'         => 'publish',
        'sku'            => 'SKU-'.$id,
        'price'          => '21.99',
        'regular_price'  => '21.99',
        'manage_stock'   => true,
        'stock_quantity' => 3,
        'stock_status'   => 'instock',
        'images'         => [['id' => 10 + $id, 'src' => WOO_STORE_URL.'/wp-content/uploads/'.$id.'.jpg', 'name' => 'p', 'alt' => '']],
    ], $overrides);
}

function wooOrder(int $id, array $lineItems, array $overrides = []): array
{
    return array_replace_recursive([
        'id'        => $id,
        'parent_id' => 0,
        'number'    => (string) $id,
        'order_key' => 'wc_order_key'.$id,
        'status'    => 'processing',
        'currency'  => 'GBP',
        'total'     => '29.35',
        'billing'   => [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'company'    => 'Acme Ltd',
            'address_1'  => '1 Billing Road',
            'address_2'  => '',
            'city'       => 'Sheffield',
            'state'      => '',
            'postcode'   => 'S1 1AA',
            'country'    => 'GB',
            'email'      => 'john.doe@example.com',
            'phone'      => '(0114) 555-5555',
        ],
        'shipping' => [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'company'    => 'Acme Ltd',
            'address_1'  => '2 Shipping Street',
            'address_2'  => 'Unit 4',
            'city'       => 'Sheffield',
            'state'      => '',
            'postcode'   => 'S2 2BB',
            'country'    => 'GB',
            'phone'      => '',
        ],
        'payment_method' => 'bacs',
        'date_paid'      => '2026-09-01T10:00:00',
        'date_paid_gmt'  => '2026-09-01T09:00:00',
        'meta_data'      => [],
        'line_items'     => $lineItems,
    ], $overrides);
}

function wooLineItem(int $id, int $productId, int $quantity = 1, int $variationId = 0): array
{
    return [
        'id'           => $id,
        'name'         => 'Line '.$id,
        'product_id'   => $productId,
        'variation_id' => $variationId,
        'quantity'     => $quantity,
        'sku'          => '',
        'price'        => 3,
        'meta_data'    => [],
    ];
}

/**
 * Both probes the connection check makes answer with the same failure.
 */
function wooDown(\GuzzleHttp\Promise\PromiseInterface $response): array
{
    return ['GET settings' => $response, 'GET orders' => $response];
}

/**
 * Handlers are keyed by "METHOD path" with the path relative to /wp-json/wc/v3/. Anything the test
 * did not fake is a stray request and fails it.
 */
function wooFake(array $handlers = []): void
{
    $handlers = array_merge(['GET settings' => Http::response(wooSettingsGroups())], $handlers);

    Http::swap(new Factory(app(Dispatcher::class)));
    Http::fake(function (Request $request) use ($handlers) {
        $path = Str::after((string) parse_url($request->url(), PHP_URL_PATH), '/wp-json/wc/v3/');
        $key  = $request->method().' '.$path;

        if (!array_key_exists($key, $handlers)) {
            return null;
        }

        $handler = $handlers[$key];

        return is_callable($handler) ? $handler($request) : $handler;
    });

    Http::preventStrayRequests();
}

/**
 * @return \Illuminate\Support\Collection<int, Request>
 */
function wooSent(string $method, string $path)
{
    return Http::recorded()
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $request) => $request->method() === $method
            && Str::after((string) parse_url($request->url(), PHP_URL_PATH), '/wp-json/wc/v3/') === $path)
        ->values();
}

function wooQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

function wooPortfolio(CustomerSalesChannel $customerSalesChannel, Product $product, ?string $platformProductId = null, string $sku = 'aw-sku-1'): Portfolio
{
    $portfolio = StorePortfolio::make()->action($customerSalesChannel, $product, []);

    $portfolio->update([
        'sku'                 => $sku,
        'platform_product_id' => $platformProductId,
        'platform_status'     => (bool) $platformProductId,
    ]);

    return $portfolio->refresh();
}

function wooSecondProduct(Shop $shop, Product $product): Product
{
    $family = $shop->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->first();

    return StoreProduct::make()->action($family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [['id' => $product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 50,
        ]
    ));
}

test('authorising a store stores the keys, checks the connection and registers the webhooks', function () {
    $customer = wooCustomer($this->shop);

    wooFake([
        'POST webhooks' => Http::sequence()
            ->push(['id' => 142, 'name' => 'Order created', 'status' => 'active', 'topic' => 'order.created'], 201)
            ->push(['id' => 143, 'name' => 'Product deleted', 'status' => 'active', 'topic' => 'product.deleted'], 201),
        'GET settings/products/woocommerce_weight_unit' => Http::response(['id' => 'woocommerce_weight_unit', 'value' => 'lbs', 'group_id' => 'products']),
    ]);

    StoreTemporaryWooUser::run($customer, ['name' => 'My Woo Shop', 'url' => WOO_STORE_URL.'/']);
    CallbackRetinaWooCommerceUser::make()->handle($customer, ['consumer_key' => 'ck_live', 'consumer_secret' => 'cs_live']);
    CheckTemporaryWooUserApiKeys::make()->handle($customer);

    $wooCommerceUser = $customer->wooCommerceUser()->first();
    $channel         = $wooCommerceUser->customerSalesChannel;

    expect($wooCommerceUser->consumer_key)->toBe('ck_live')
        ->and($wooCommerceUser->consumer_secret)->toBe('cs_live')
        ->and($wooCommerceUser->store_url)->toBe(WOO_STORE_URL)
        ->and($channel->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($channel->platform_status)->toBeTrue()
        ->and(Arr::get($wooCommerceUser->settings, 'webhooks'))->toBe(['order_created' => 142, 'product_deleted' => 143])
        ->and(Arr::get($wooCommerceUser->settings, 'weight_option'))->toBe('lbs');

    $webhooks = wooSent('POST', 'webhooks');
    expect($webhooks)->toHaveCount(2)
        ->and($webhooks[0]->data()['topic'])->toBe('order.created')
        ->and($webhooks[0]->data()['delivery_url'])->toContain('/woocommerce/'.$wooCommerceUser->id.'/orders/catch')
        ->and($webhooks[1]->data()['topic'])->toBe('product.deleted')
        ->and(wooSent('GET', 'settings')->first()->hasHeader('Authorization'))->toBeTrue();
});

test('authorisation is refused when the store rejects the keys', function () {
    $customer = wooCustomer($this->shop);

    wooFake(wooDown(wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 401)));

    StoreTemporaryWooUser::run($customer, ['name' => 'Bad Keys', 'url' => WOO_STORE_URL]);
    CallbackRetinaWooCommerceUser::make()->handle($customer, ['consumer_key' => 'ck_bad', 'consumer_secret' => 'cs_bad']);

    expect(fn () => CheckTemporaryWooUserApiKeys::make()->handle($customer))->toThrow(ValidationException::class)
        ->and($customer->wooCommerceUser()->count())->toBe(0);
});

test('a forbidden reply or an html page is not a working connection', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));

    wooFake(wooDown(wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 403)));
    expect($wooCommerceUser->checkConnection())->toBeFalse();

    wooFake(wooDown(Http::response('<!DOCTYPE html><html><head><title>Maintenance</title></head><body>Back soon</body></html>', 200, ['Content-Type' => 'text/html'])));
    expect($wooCommerceUser->checkConnection())->toBeFalse();

    wooFake(wooDown(Http::response(['code' => 'rest_no_route', 'message' => 'No route was found matching the URL and request method.', 'data' => ['status' => 404]], 404)));
    expect($wooCommerceUser->checkConnection())->toBeFalse();

    wooFake();
    expect($wooCommerceUser->checkConnection())->toBeTrue();
});

test('a key that cannot read settings but can read orders still counts as connected', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));

    wooFake([
        'GET settings' => wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 403),
        'GET orders'   => Http::response([wooOrder(1, [])]),
    ]);
    expect($wooCommerceUser->checkConnection())->toBeTrue()
        ->and(wooQuery(wooSent('GET', 'orders')->first())['per_page'])->toBe('1');

    wooFake([
        'GET settings' => Http::response(['data' => wooSettingsGroups()]),
        'GET orders'   => Http::response([]),
    ]);
    expect($wooCommerceUser->checkConnection())->toBeTrue();

    wooFake();
    expect($wooCommerceUser->checkConnection())->toBeTrue()
        ->and(wooSent('GET', 'orders'))->toHaveCount(0);
});

test('checking a channel marks it not ready while the store is down and authenticated once it answers again', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $wooCommerceUser->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);

    wooFake(wooDown(Http::response('Internal Server Error', 500)));
    $channel = CheckWooChannel::run($wooCommerceUser);

    expect($channel->platform_status)->toBeFalse()
        ->and($channel->state)->toBe(CustomerSalesChannelStateEnum::NOT_READY);

    $channel->update(['ban_stock_update_util' => now()->addMinute()]);

    wooFake();
    $channel = CheckWooChannel::run($wooCommerceUser->refresh());

    expect($channel->platform_status)->toBeTrue()
        ->and($channel->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($channel->ban_stock_update_util)->toBeNull()
        ->and(wooSent('POST', 'webhooks'))->toHaveCount(0);
});

test('connecting the same store again reuses the channel and, after a close, brings it back with its portfolio', function () {
    $customer = wooCustomer($this->shop);

    $first   = wooConnect($customer, ['name' => 'reused-store', 'store_url' => 'https://Shop.Example.test/', 'consumer_key' => 'ck_1']);
    $channel = $first->customerSalesChannel;
    expect($first->store_url)->toBe(WOO_STORE_URL);

    $portfolio = wooPortfolio($channel, $this->product, '794');

    $second = wooConnect($customer, ['name' => 'reused-store', 'store_url' => WOO_STORE_URL, 'consumer_key' => 'ck_2']);

    expect($second->id)->toBe($first->id)
        ->and($second->consumer_key)->toBe('ck_2')
        ->and($second->customer_sales_channel_id)->toBe($channel->id)
        ->and($customer->customerSalesChannels()->count())->toBe(1)
        ->and($customer->wooCommerceUser()->count())->toBe(1);

    CloseCustomerSalesChannel::make()->handle($channel);

    expect($channel->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($first->fresh()->trashed())->toBeTrue()
        ->and($portfolio->fresh()->status)->toBeFalse();

    $third = wooConnect($customer, ['name' => 'reused-store', 'store_url' => WOO_STORE_URL, 'consumer_key' => 'ck_3']);
    $channel->refresh();

    expect($third->id)->toBe($first->id)
        ->and($third->trashed())->toBeFalse()
        ->and($customer->customerSalesChannels()->count())->toBe(1)
        ->and($channel->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($channel->closed_at)->toBeNull()
        ->and($channel->name)->toBe('reused-store')
        ->and($channel->user?->id)->toBe($first->id)
        ->and($portfolio->fresh()->status)->toBeTrue();

    $other = wooConnect($customer, ['name' => 'other-store', 'store_url' => 'https://other.example.test']);
    expect($other->id)->not->toBe($first->id)
        ->and($customer->customerSalesChannels()->count())->toBe(2);
});

test('reviving a closed channel restores its user and checks the store', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $wooCommerceUser->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);
    $channel = $wooCommerceUser->customerSalesChannel;

    CloseCustomerSalesChannel::make()->handle($channel);
    expect($wooCommerceUser->fresh()->trashed())->toBeTrue();

    wooFake();
    ReviveInActiveWooChannel::make()->handle($channel->refresh());

    expect($wooCommerceUser->fresh()->trashed())->toBeFalse()
        ->and($channel->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and($channel->fresh()->platform_status)->toBeTrue();
});

test('re-authorisation writes the new keys on the existing user', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $wooCommerceUser->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);

    wooFake();
    CallbackRetinaWooCommerceUser::make()->handleReAuthorization($wooCommerceUser, ['consumer_key' => 'ck_new', 'consumer_secret' => 'cs_new']);

    expect($wooCommerceUser->fresh()->consumer_key)->toBe('ck_new')
        ->and($wooCommerceUser->fresh()->consumer_secret)->toBe('cs_new')
        ->and($wooCommerceUser->customerSalesChannel->fresh()->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED);
});

test('the order webhook only queues a fetch, its payload is never trusted', function () {
    Queue::fake();
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));

    postJson(route('webhooks.woo.orders.catch', ['wooCommerceUser' => $wooCommerceUser->id]), [
        'id'         => 999,
        'order_key'  => 'wc_order_forged',
        'line_items' => [wooLineItem(1, 794)],
    ])->assertSuccessful();

    FetchWooUserOrders::assertPushed(fn (FetchWooUserOrders $job, array $arguments) => $arguments[0]->id === $wooCommerceUser->id);
    expect(Order::where('platform_order_id', 'wc_order_forged')->exists())->toBeFalse();
});

test('fetching orders imports a paid processing order with its client, address and lines', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    $portfolio       = wooPortfolio($channel, $this->product, '93');

    wooFake([
        'GET orders' => Http::response([wooOrder(727, [wooLineItem(315, 93, 2)])]),
    ]);

    FetchWooUserOrders::run($wooCommerceUser);

    $query = wooQuery(wooSent('GET', 'orders')->first());
    expect($query['status'])->toBe('processing')
        ->and($query['per_page'])->toBe('100')
        ->and($query)->toHaveKey('after');

    $order = $channel->orders()->first();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->platform_order_id)->toBe('wc_order_key727')
        ->and($order->customer_reference)->toBe('727')
        ->and(Arr::get($order->data, 'woo_order.id'))->toBe(727)
        ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
        ->and($order->transactions()->count())->toBe(1)
        ->and((int) $order->transactions()->first()->quantity_ordered)->toBe(2)
        ->and($order->transactions()->first()->platform_transaction_id)->toBe('315')
        ->and($order->transactions()->first()->historic_asset_id)->toBe($portfolio->item->currentHistoricProduct->id)
        ->and($order->deliveryAddress->address_line_1)->toBe('2 Shipping Street')
        ->and($order->deliveryAddress->postal_code)->toBe('S2 2BB')
        ->and($order->deliveryAddress->country_code)->toBe('GB');

    $client = $order->customerClient;
    expect($client->reference)->toBe('John Doe Acme Ltd'.$channel->id)
        ->and($client->email)->toBe('john.doe@example.com')
        ->and($client->contact_name)->toBe('John Doe')
        ->and($client->company_name)->toBe('Acme Ltd')
        ->and($client->address->address_line_1)->toBe('1 Billing Road')
        ->and($wooCommerceUser->debugWebhooks()->count())->toBe(1);
});

test('fetching the same order again does not import it twice and a returning client is updated not duplicated', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    wooPortfolio($channel, $this->product, '93');

    wooFake(['GET orders' => Http::response([wooOrder(727, [wooLineItem(315, 93)])])]);
    FetchWooUserOrders::run($wooCommerceUser);
    FetchWooUserOrders::run($wooCommerceUser);

    expect($channel->orders()->count())->toBe(1);

    wooFake(['GET orders' => Http::response([wooOrder(728, [wooLineItem(316, 93)], ['billing' => ['email' => 'new.mail@example.com'], 'shipping' => ['phone' => '+44 (0)114 000 111']])])]);
    FetchWooUserOrders::run($wooCommerceUser);

    expect($channel->orders()->count())->toBe(2)
        ->and($channel->clients()->count())->toBe(1)
        ->and($channel->clients()->first()->email)->toBe('new.mail@example.com')
        ->and($channel->clients()->first()->phone)->toBe('+440114000111');
});

test('unpaid, cancelled and address-less orders are skipped', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    wooPortfolio($channel, $this->product, '93');

    wooFake([
        'GET orders' => Http::response([
            wooOrder(730, [wooLineItem(1, 93)], ['date_paid' => null, 'date_paid_gmt' => null]),
            wooOrder(731, [wooLineItem(2, 93)], ['status' => 'cancelled']),
            wooOrder(732, [wooLineItem(3, 93)], ['status' => 'refunded']),
            wooOrder(733, [wooLineItem(4, 93)], ['shipping' => ['country' => '']]),
        ]),
    ]);

    FetchWooUserOrders::run($wooCommerceUser);

    expect($channel->orders()->count())->toBe(0)
        ->and($wooCommerceUser->debugWebhooks()->count())->toBe(4);
});

test('an order with no portfolio line is ignored and lines outside the portfolio are dropped from a mixed order', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    wooPortfolio($channel, $this->product, '93');

    wooFake([
        'GET orders' => Http::response([
            wooOrder(740, [wooLineItem(1, 999)]),
            wooOrder(741, [wooLineItem(2, 93, 3), wooLineItem(3, 999, 5)]),
        ]),
    ]);

    FetchWooUserOrders::run($wooCommerceUser);

    $orders = $channel->orders()->get();
    expect($orders)->toHaveCount(1)
        ->and($orders[0]->platform_order_id)->toBe('wc_order_key741')
        ->and($orders[0]->transactions()->count())->toBe(1)
        ->and((int) $orders[0]->transactions()->first()->quantity_ordered)->toBe(3);
});

test('a variation line resolves through the variation id first and through its parent product otherwise', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    $parent          = wooPortfolio($channel, $this->product, '22', 'parent-sku');
    $variation       = wooPortfolio($channel, wooSecondProduct($this->shop, $this->product), '23', 'variation-sku');

    wooFake([
        'GET orders' => Http::response([
            wooOrder(750, [wooLineItem(1, 22, 1, 23)]),
            wooOrder(751, [wooLineItem(2, 22, 1, 24)]),
        ]),
    ]);

    FetchWooUserOrders::run($wooCommerceUser);

    $byVariation = $channel->orders()->where('platform_order_id', 'wc_order_key750')->first();
    $byParent    = $channel->orders()->where('platform_order_id', 'wc_order_key751')->first();

    expect($byVariation->transactions()->first()->historic_asset_id)->toBe($variation->item->currentHistoricProduct->id)
        ->and($byParent->transactions()->first()->historic_asset_id)->toBe($parent->item->currentHistoricProduct->id);
});

test('an error reply from the orders endpoint imports nothing and does not blow up', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '93');

    wooFake(['GET orders' => wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 401)]);
    FetchWooUserOrders::run($wooCommerceUser);

    wooFake(['GET orders' => Http::response('<!DOCTYPE html><html><head><title>503</title></head><body></body></html>', 503)]);
    FetchWooUserOrders::run($wooCommerceUser);

    expect($wooCommerceUser->customerSalesChannel->orders()->count())->toBe(0);
});

test('a retry by the numeric order id recognises an order already imported under its order key', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    wooPortfolio($channel, $this->product, '93');

    wooFake(['GET orders' => Http::response([wooOrder(760, [wooLineItem(1, 93)])])]);
    FetchWooUserOrders::run($wooCommerceUser);

    $result = RetryOrderImport::run($channel, '760', true);

    expect($result['status'])->toBe(OrderImportRetryStatusEnum::ALREADY_IMPORTED)
        ->and($channel->orders()->count())->toBe(1)
        ->and(wooSent('GET', 'orders/760'))->toHaveCount(0);
});

test('dispatching a woo order completes it on the store with the tracking and a customer note', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    wooPortfolio($channel, $this->product, '93');

    wooFake(['GET orders' => Http::response([wooOrder(770, [wooLineItem(1, 93)])])]);
    FetchWooUserOrders::run($wooCommerceUser);

    $order        = $channel->orders()->first();
    $deliveryNote = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN-WOO-770',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+441140000000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => createWarehouse()->id,
    ]);

    $shipment = Shipment::create([
        'organisation_id'    => $order->organisation_id,
        'group_id'           => $order->group_id,
        'shop_id'            => $order->shop_id,
        'tracking'           => 'TRK123',
        'trade_as'           => 'Royal Mail',
        'combined_label_url' => 'https://labels.example.test/TRK123.pdf',
    ]);
    $deliveryNote->shipments()->attach($shipment, ['model_type' => $deliveryNote->getMorphClass()]);

    wooFake([
        'PUT orders/770'        => Http::response(array_merge(wooOrder(770, []), ['status' => 'completed'])),
        'POST orders/770/notes' => Http::response(['id' => 281, 'note' => 'x', 'customer_note' => true], 201),
    ]);

    FulfillOrderToWooCommerce::run($order->refresh());

    $update = wooSent('PUT', 'orders/770')->first()->data();
    expect($update['status'])->toBe('completed')
        ->and($update['meta_data'][0]['key'])->toBe('_wc_shipment_tracking_items')
        ->and($update['meta_data'][0]['value'][0]['tracking_number'])->toBe('TRK123')
        ->and($update['meta_data'][0]['value'][0]['tracking_provider'])->toBe('Royal Mail')
        ->and($update['meta_data'][0]['value'][0]['custom_tracking_link'])->toBe('https://labels.example.test/TRK123.pdf');

    $note = wooSent('POST', 'orders/770/notes')->first()->data();
    expect($note['customer_note'])->toBeTrue()
        ->and($note['note'])->toContain('TRK123');

    $order->update(['is_bypass_platform_update' => true]);
    wooFake();
    FulfillOrderToWooCommerce::run($order->refresh());
    expect(wooSent('PUT', 'orders/770'))->toHaveCount(0);
});

test('uploading a portfolio creates a simple product shaped for the store and saves what came back', function () {
    $customer        = wooCustomer($this->shop);
    $wooCommerceUser = wooConnect($customer);
    $wooCommerceUser->update(['settings' => ['weight_option' => 'kg']]);
    $channel = $wooCommerceUser->customerSalesChannel;
    $channel->update(['max_quantity_advertise' => 10, 'stock_threshold' => 2]);

    $this->product->update(['available_quantity' => 64, 'gross_weight' => 1500, 'marketing_ingredients' => null]);
    expect($this->product->refresh()->isSellableThroughSalesChannels())->toBeTrue();

    $portfolio = wooPortfolio($channel, $this->product, null, 'aw-upload-1');
    $portfolio->update(['customer_product_name' => 'Customer facing name', 'customer_price' => 12.5]);

    wooFake([
        'POST products'    => Http::response(wooProduct(794, ['sku' => 'aw-upload-1']), 201),
        'GET products/794' => Http::response(wooProduct(794, ['sku' => 'aw-upload-1'])),
    ]);

    StoreNewProductToCurrentWooCommerce::run($wooCommerceUser, $portfolio);
    $portfolio->refresh();

    $body = wooSent('POST', 'products')->first()->data();
    expect($body['type'])->toBe('simple')
        ->and($body['name'])->toBe('Customer facing name')
        ->and($body['sku'])->toBe('aw-upload-1')
        ->and($body['regular_price'])->toBe('12.5')
        ->and($body['manage_stock'])->toBeTrue()
        ->and($body['stock_quantity'])->toBe(10)
        ->and($body['stock_status'])->toBe('instock')
        ->and($body['weight'])->toBe('1.5')
        ->and(collect($body['attributes'])->pluck('name'))->not->toContain('Ingredients');

    expect($portfolio->platform_product_id)->toBe('794')
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and($portfolio->errors_response)->toBeNull()
        ->and(Arr::get($portfolio->data, 'woo_product.id'))->toBe(794)
        ->and(PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->latest('id')->first()->status)->toBe(PlatformPortfolioLogsStatusEnum::OK);
});

test('an upload the store refuses leaves the error on the portfolio and an empty match list in the table shape', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, null, 'aw-refused');

    wooFake([
        'POST products' => wooError('woocommerce_rest_cannot_create', 'Sorry, you are not allowed to create resources.', 401),
        'GET products'  => Http::response([]),
    ]);

    StoreNewProductToCurrentWooCommerce::run($wooCommerceUser, $portfolio);
    $portfolio->refresh();

    expect($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->platform_product_id)->toBeNull()
        ->and($portfolio->errors_response['message'])->toBe('Sorry, you are not allowed to create resources.')
        ->and($portfolio->platform_possible_matches)->toEqual(['number_matches' => 0, 'matches_labels' => [], 'raw_data' => []])
        ->and(PlatformPortfolioLogs::where('portfolio_id', $portfolio->id)->latest('id')->first()->status)->toBe(PlatformPortfolioLogsStatusEnum::FAIL);
});

test('an upload that collides with a listed sku adopts the listed product', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, null, 'dup-sku');

    wooFake([
        'POST products'    => Http::response(['code' => 'product_invalid_sku', 'message' => 'Invalid or duplicated SKU.', 'data' => ['status' => 400, 'resource_id' => 555, 'unique_sku' => 'dup-sku-1']], 400),
        'GET products'     => fn (Request $request) => Http::response(wooQuery($request)['sku'] === 'dup-sku' ? [wooProduct(555, ['sku' => 'dup-sku'])] : []),
        'GET products/555' => Http::response(wooProduct(555, ['sku' => 'dup-sku'])),
    ]);

    StoreNewProductToCurrentWooCommerce::run($wooCommerceUser, $portfolio);
    $portfolio->refresh();

    expect($portfolio->platform_product_id)->toBe('555')
        ->and($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->errors_response)->toBeNull();
});

test('a listing the store no longer has is reported missing and the sku match is offered in the table shape', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794', 'gone-sku');

    wooFake([
        'GET products/794' => wooError('woocommerce_rest_product_invalid_id', 'Invalid ID.', 404),
        'GET products'     => fn (Request $request) => Http::response(wooQuery($request)['sku'] === 'gone-sku' ? [wooProduct(800, ['sku' => 'gone-sku', 'name' => 'Relisted'])] : []),
    ]);

    $portfolio = CheckWooPortfolio::run($portfolio)->refresh();

    expect($portfolio->exist_in_platform)->toBeFalse()
        ->and($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->number_platform_possible_matches)->toBe(1)
        ->and($portfolio->platform_possible_matches['number_matches'])->toBe(1)
        ->and($portfolio->platform_possible_matches['matches_labels'])->toBe(['Relisted'])
        ->and($portfolio->platform_possible_matches['raw_data'][0]['id'])->toBe(800)
        ->and($portfolio->platform_possible_matches['raw_data'][0]['name'])->toBe('Relisted')
        ->and($portfolio->platform_possible_matches['raw_data'][0]['images'][0]['src'])->toBe(WOO_STORE_URL.'/wp-content/uploads/800.jpg')
        ->and(Arr::get($portfolio->data, 'woo_product'))->toBeNull();
});

test('an error while searching the catalogue offers no matches instead of the error body', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, null, 'err-sku');

    wooFake(['GET products' => Http::response('Service Unavailable', 503)]);

    $portfolio = CheckWooPortfolio::run($portfolio)->refresh();

    expect($portfolio->platform_status)->toBeFalse()
        ->and($portfolio->platform_possible_matches)->toEqual(['number_matches' => 0, 'matches_labels' => [], 'raw_data' => []]);
});

test('bulk matching links portfolios to listed products by sku and pushes their stock', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    $channel->update(['max_quantity_advertise' => 5]);
    $this->product->update(['available_quantity' => 64]);

    $matched = wooPortfolio($channel, $this->product, null, 'Listed-Sku');
    $ignored = wooPortfolio($channel, wooSecondProduct($this->shop, $this->product), null, 'unlisted-sku');

    wooFake([
        'GET products'     => fn (Request $request) => Http::response(Arr::has(wooQuery($request), 'page')
            ? [wooProduct(801, ['sku' => 'listed-sku']), wooProduct(802, ['sku' => 'other'])]
            : []),
        'GET products/801' => Http::response(wooProduct(801, ['sku' => 'listed-sku'])),
        'PUT products/801' => Http::response(wooProduct(801, ['stock_quantity' => 5])),
    ]);

    $result = MatchBulkNewProductToCurrentWooCommerce::make()->handle($channel, []);

    expect($result)->toBe(['matched' => 1, 'ignored' => 1])
        ->and($matched->refresh()->platform_product_id)->toBe('801')
        ->and($matched->platform_status)->toBeTrue()
        ->and($ignored->refresh()->platform_product_id)->toBeNull();

    $push = wooSent('PUT', 'products/801')->first()->data();
    expect($push['manage_stock'])->toBeTrue()
        ->and($push['stock_quantity'])->toBe(5)
        ->and($push['stock_status'])->toBe('instock');
});

test('listed skus stop at an error page and page through a full catalogue', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));

    wooFake(['GET products' => wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 401)]);
    expect(GetWooListedSkus::run($wooCommerceUser))->toBe([]);

    wooFake([
        'GET products' => fn (Request $request) => Http::response(match ((int) wooQuery($request)['page']) {
            1 => array_map(fn ($i) => wooProduct($i, ['sku' => 'p'.$i]), range(1, 100)),
            2 => [wooProduct(101, ['sku' => 'P101']), wooProduct(102, ['sku' => ''])],
        }),
    ]);

    $listed = GetWooListedSkus::run($wooCommerceUser);
    expect($listed)->toHaveCount(101)
        ->and($listed['p101'])->toBe('101')
        ->and(wooSent('GET', 'products'))->toHaveCount(2);
});

test('the product picker returns published products as id, name, code and images and filters by query', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));

    wooFake([
        'GET products' => fn (Request $request) => Http::response(wooQuery($request)['status'] === 'publish'
            ? [wooProduct(1, ['name' => 'Lavender Candle', 'sku' => 'LAV-1']), wooProduct(2, ['name' => 'Rose Soap', 'sku' => 'ROS-2'])]
            : []),
    ]);

    $picked = GetProductForWooCommerce::run($wooCommerceUser, 'rose');

    expect($picked)->toHaveCount(1)
        ->and($picked[0]['id'])->toBe(2)
        ->and($picked[0]['name'])->toBe('Rose Soap')
        ->and($picked[0]['code'])->toBe('ROS-2')
        ->and($picked[0]['images'][0]['src'])->toBe(WOO_STORE_URL.'/wp-content/uploads/2.jpg')
        ->and(GetProductForWooCommerce::run($wooCommerceUser, 'LAV'))->toHaveCount(1);
});

test('editing a portfolio pushes its name, price and description to the listed product', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794');
    $portfolio->update(['customer_product_name' => 'Renamed', 'customer_price' => 9.99, 'customer_description' => 'New text']);

    wooFake(['PUT products/794' => Http::response(wooProduct(794, ['name' => 'Renamed']))]);

    UpdateWooProduct::run($portfolio->refresh());

    $body = wooSent('PUT', 'products/794')->first()->data();
    expect($body)->toBe(['regular_price' => '9.99', 'name' => 'Renamed', 'description' => 'New text']);
});

test('the product deleted webhook drops the portfolio only once the store confirms the product is gone', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794');
    $route           = route('webhooks.woo.products.delete', ['wooCommerceUser' => $wooCommerceUser->id]);

    wooFake(['GET products/794' => Http::response(wooProduct(794))]);
    postJson($route, ['id' => 794])->assertSuccessful();
    expect($portfolio->fresh())->not->toBeNull();

    wooFake(['GET products/794' => wooError('woocommerce_rest_product_invalid_id', 'Invalid ID.', 404)]);
    postJson($route, ['id' => 794])->assertSuccessful();
    expect($portfolio->fresh())->toBeNull()
        ->and(wooSent('DELETE', 'products/794'))->toHaveCount(0);
});

test('removing a portfolio deletes the product on the store', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794');

    wooFake(['DELETE products/794' => Http::response(wooProduct(794, ['status' => 'trash']))]);

    DeletePortfolio::run($portfolio);

    expect($portfolio->fresh())->toBeNull()
        ->and(wooSent('DELETE', 'products/794')->first()->data()['force'])->toBeTrue();
});

test('the stock push batches the channel portfolios and records each product result', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    $channel->update(['stock_threshold' => 2, 'max_quantity_advertise' => 20, 'ban_stock_update_util' => now()->subMinute()]);

    $this->product->update(['available_quantity' => 64]);
    $second = wooSecondProduct($this->shop, $this->product);
    $second->update(['available_quantity' => 1]);

    $live = wooPortfolio($channel, $this->product, '794', 'live-sku');
    $dead = wooPortfolio($channel, $second, '795', 'dead-sku');
    $off  = wooPortfolio($channel, wooSecondProduct($this->shop, $this->product), '796', 'off-sku');
    $off->update(['platform_status' => false]);

    wooFake([
        'POST products/batch' => Http::response([
            'create' => [],
            'update' => [
                wooProduct(794, ['stock_quantity' => 20]),
                ['id' => 795, 'error' => ['code' => 'woocommerce_rest_product_invalid_id', 'message' => 'Invalid ID.', 'data' => ['status' => 404]]],
            ],
            'delete' => [],
        ]),
    ]);

    UpdateWooCustomerSalesChannelPortfolio::run($channel, true);

    $batch = wooSent('POST', 'products/batch')->first()->data();
    expect($batch['update'])->toBe([
        ['id' => '794', 'manage_stock' => true, 'stock_quantity' => 20],
        ['id' => '795', 'manage_stock' => true, 'stock_quantity' => 0],
    ]);

    expect((int) $live->refresh()->last_stock_value)->toBe(20)
        ->and($live->stock_last_updated_at)->not->toBeNull()
        ->and($dead->refresh()->stock_last_fail_updated_at)->not->toBeNull()
        ->and($dead->last_stock_value)->toBeNull()
        ->and($channel->refresh()->ban_stock_update_util)->toBeNull();
});

test('a rejected batch bans the channel briefly unless the store says the request itself was wrong', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $channel         = $wooCommerceUser->customerSalesChannel;
    $this->product->update(['available_quantity' => 3]);
    wooPortfolio($channel, $this->product, '794');

    wooFake(['POST products/batch' => Http::response('<!DOCTYPE html><html><head><title>502</title></head></html>', 502)]);
    UpdateWooCustomerSalesChannelPortfolio::run($channel, true);
    expect($channel->refresh()->ban_stock_update_util)->not->toBeNull();

    $channel->update(['ban_stock_update_util' => null]);
    wooFake(['POST products/batch' => wooError('rest_invalid_param', 'Invalid parameter(s): update', 400)]);
    UpdateWooCustomerSalesChannelPortfolio::run($channel, true);
    expect($channel->refresh()->ban_stock_update_util)->toBeNull();

    wooFake(wooDown(wooError('woocommerce_rest_authentication_error', 'Consumer key is invalid.', 401)));
    UpdateWooCustomerSalesChannelPortfolio::run($channel, true);
    expect($channel->refresh()->ban_stock_update_util)->not->toBeNull()
        ->and(wooSent('POST', 'products/batch'))->toHaveCount(0);
});

test('the inventory scheduler skips banned, closed and manual-stock channels', function () {
    Queue::fake();
    $open   = wooConnect(wooCustomer($this->shop))->customerSalesChannel;
    $banned = wooConnect(wooCustomer($this->shop))->customerSalesChannel;
    $manual = wooConnect(wooCustomer($this->shop))->customerSalesChannel;

    $open->update(['platform_status' => true, 'stock_update' => true]);
    $banned->update(['platform_status' => true, 'stock_update' => true, 'ban_stock_update_util' => now()->addMinute()]);
    $manual->update(['platform_status' => true, 'stock_update' => false]);

    UpdateInventoryInWooPortfolio::run();

    UpdateWooCustomerSalesChannelPortfolio::assertPushed(fn ($job, array $arguments) => $arguments[0]->id === $open->id);
    UpdateWooCustomerSalesChannelPortfolio::assertNotPushed(fn ($job, array $arguments) => in_array($arguments[0]->id, [$banned->id, $manual->id]));
});

test('quantity to send follows the channel threshold and cap', function () {
    $channel = wooConnect(wooCustomer($this->shop))->customerSalesChannel;
    $product = $this->product;

    $product->update(['available_quantity' => 5]);
    $channel->update(['stock_threshold' => 5, 'max_quantity_advertise' => 0]);
    expect(UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product->refresh(), $channel->refresh()))->toBe(0);

    $product->update(['available_quantity' => 50]);
    $channel->update(['stock_threshold' => 5, 'max_quantity_advertise' => 10]);
    expect(UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product->refresh(), $channel->refresh()))->toBe(10);

    $channel->update(['stock_threshold' => 0, 'max_quantity_advertise' => 0]);
    expect(UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product->refresh(), $channel->refresh()))->toBe(50);
});

test('fetching store customers copes with a missing shipping phone and with an error reply', function () {
    $customer        = wooCustomer($this->shop);
    $wooCommerceUser = wooConnect($customer);
    $channel         = $wooCommerceUser->customerSalesChannel;

    $wooCustomer = [
        'id'         => 25,
        'email'      => 'jane@example.com',
        'first_name' => 'Jane',
        'last_name'  => 'Roe',
        'billing'    => ['first_name' => 'Jane', 'last_name' => 'Roe', 'company' => '', 'address_1' => '9 High St', 'address_2' => '', 'city' => 'Leeds', 'state' => '', 'postcode' => 'LS1 1AA', 'country' => 'GB', 'email' => 'jane@example.com', 'phone' => '0113 000 000'],
        'shipping'   => ['first_name' => 'Jane', 'last_name' => 'Roe', 'company' => '', 'address_1' => '9 High St', 'address_2' => '', 'city' => 'Leeds', 'state' => '', 'postcode' => 'LS1 1AA', 'country' => 'GB'],
    ];

    wooFake(['GET customers' => Http::response([$wooCustomer])]);
    GetRetinaCustomerClientFromWooCommerce::make()->handle($wooCommerceUser);

    expect($channel->clients()->count())->toBe(1)
        ->and($channel->clients()->first()->email)->toBe('jane@example.com')
        ->and($channel->clients()->first()->address->locality)->toBe('Leeds');

    wooFake(['GET customers' => wooError('woocommerce_rest_cannot_view', 'Sorry, you cannot list resources.', 401)]);
    GetRetinaCustomerClientFromWooCommerce::make()->handle($wooCommerceUser);

    expect($channel->clients()->count())->toBe(1);
});

test('the reconnect repair merges closed channels of the same store into the live one and closes empty open duplicates', function () {
    $customer = wooCustomer($this->shop);

    $oldUser = wooConnect($customer, ['name' => 'trail-store', 'store_url' => 'https://Trail.Example.test/']);
    $old     = $oldUser->customerSalesChannel;
    $shared  = wooPortfolio($old, $this->product, '11', 'shared-sku');
    $only    = wooPortfolio($old, wooSecondProduct($this->shop, $this->product), '12', 'only-old-sku');
    $client  = StoreCustomerClient::make()->action($old, CustomerClient::factory()->definition());
    CloseCustomerSalesChannel::make()->handle($old);

    $keepUser = wooConnect($customer, ['name' => 'trail-store', 'store_url' => 'https://tmp-keep.example.test']);
    $keepUser->update(['store_url' => 'https://trail.example.test']);
    $keep = $keepUser->customerSalesChannel;
    wooPortfolio($keep, $this->product, '21', 'shared-sku-again');

    $emptyUser = wooConnect($customer, ['name' => 'trail-store', 'store_url' => 'https://tmp-empty.example.test']);
    $emptyUser->update(['store_url' => 'https://trail.example.test/']);
    $empty = $emptyUser->customerSalesChannel;

    $stranger = wooConnect(wooCustomer($this->shop), ['store_url' => 'https://trail.example.test']);

    expect(RepairWooChannelReconnects::channelsToKeep()->pluck('id')->all())->toBe([$keep->id]);

    wooFake(wooDown(wooError('woocommerce_rest_authentication_error', 'Consumer key is invalid.', 401)));
    expect(RepairWooChannelReconnects::run($keep, true)['closed_duplicates'])->toBe(0)
        ->and(RepairWooChannelReconnects::run($keep, true)['skipped'])->toBe(1);

    wooFake();
    $plan = RepairWooChannelReconnects::run($keep, true);
    expect($plan)->toBe(['portfolios' => 1, 'clients' => 1, 'orders' => 0, 'predecessors' => 1, 'closed_duplicates' => 1, 'skipped' => 0])
        ->and($only->fresh()->customer_sales_channel_id)->toBe($old->id)
        ->and($empty->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN);

    RepairWooChannelReconnects::run($keep);

    expect($only->fresh()->customer_sales_channel_id)->toBe($keep->id)
        ->and($only->fresh()->status)->toBeTrue()
        ->and($shared->fresh()->customer_sales_channel_id)->toBe($old->id)
        ->and($client->fresh()->customer_sales_channel_id)->toBe($keep->id)
        ->and($keep->fresh()->number_portfolios)->toBe(2)
        ->and($old->fresh()->number_portfolios)->toBe(0)
        ->and($empty->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::CLOSED)
        ->and($emptyUser->fresh()->trashed())->toBeTrue()
        ->and($stranger->customerSalesChannel->fresh()->status)->toBe(CustomerSalesChannelStatusEnum::OPEN)
        ->and(RepairWooChannelReconnects::run($keep, true)['predecessors'])->toBe(2);
});

test('a store error while checking a listed portfolio leaves it untouched instead of marking it missing', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794', 'flaky-sku');
    $portfolio->update(['exist_in_platform' => true]);

    wooFake(['GET products/794' => Http::response('<!DOCTYPE html><html><head><title>502</title></head></html>', 502)]);
    $portfolio = CheckWooPortfolio::run($portfolio)->refresh();

    expect($portfolio->platform_status)->toBeTrue()
        ->and($portfolio->exist_in_platform)->toBeTrue()
        ->and(wooSent('GET', 'products'))->toHaveCount(0);
});

test('the product deleted webhook keeps the portfolio and answers ok when the store cannot be asked', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $portfolio       = wooPortfolio($wooCommerceUser->customerSalesChannel, $this->product, '794');
    $route           = route('webhooks.woo.products.delete', ['wooCommerceUser' => $wooCommerceUser->id]);

    wooFake(['GET products/794' => Http::response('Bad Gateway', 502)]);
    postJson($route, ['id' => 794])->assertSuccessful();
    expect($portfolio->fresh())->not->toBeNull();

    wooFake(['GET products/794' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28')]);
    postJson($route, ['id' => 794])->assertSuccessful();
    expect($portfolio->fresh())->not->toBeNull();
});

test('store customers without a phone become separate clients and a legacy phone-less client is matched by name', function () {
    $customer        = wooCustomer($this->shop);
    $wooCommerceUser = wooConnect($customer);
    $channel         = $wooCommerceUser->customerSalesChannel;

    $legacy = StoreCustomerClient::make()->action($channel, array_merge(CustomerClient::factory()->definition(), ['contact_name' => 'Old Row', 'phone' => null]));

    $wooCustomer = fn (int $id, string $first, ?string $phone) => [
        'id'         => $id,
        'email'      => strtolower($first).'@example.com',
        'first_name' => $first,
        'last_name'  => 'Row',
        'billing'    => ['first_name' => $first, 'last_name' => 'Row', 'company' => '', 'address_1' => '9 High St', 'address_2' => '', 'city' => 'Leeds', 'state' => '', 'postcode' => 'LS1 1AA', 'country' => 'GB', 'email' => strtolower($first).'@example.com', 'phone' => (string) $phone],
        'shipping'   => ['first_name' => $first, 'last_name' => 'Row', 'company' => '', 'address_1' => '9 High St', 'address_2' => '', 'city' => 'Leeds', 'state' => '', 'postcode' => 'LS1 1AA', 'country' => 'GB', 'phone' => ''],
    ];

    wooFake(['GET customers' => Http::response([$wooCustomer(1, 'Ann', null), $wooCustomer(2, 'Bob', null), $wooCustomer(3, 'Old', '0113 111 222')])]);
    GetRetinaCustomerClientFromWooCommerce::make()->handle($wooCommerceUser);

    expect($channel->clients()->count())->toBe(3)
        ->and($channel->clients()->where('contact_name', 'Ann Row')->first()->email)->toBe('ann@example.com')
        ->and($channel->clients()->where('contact_name', 'Bob Row')->first()->email)->toBe('bob@example.com')
        ->and($legacy->fresh()->phone)->toBe('0113 111 222');
});

test('reconnecting a closed store forgets its webhook ids so the check registers fresh ones', function () {
    $customer        = wooCustomer($this->shop);
    $wooCommerceUser = wooConnect($customer, ['name' => 'hooks-store', 'store_url' => 'https://hooks.example.test']);
    $wooCommerceUser->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);

    CloseCustomerSalesChannel::make()->handle($wooCommerceUser->customerSalesChannel);
    $again = wooConnect($customer, ['name' => 'hooks-store', 'store_url' => 'https://hooks.example.test']);

    expect($again->id)->toBe($wooCommerceUser->id)
        ->and(Arr::get($again->settings, 'webhooks'))->toBeNull()
        ->and(Arr::get($again->settings, 'weight_option'))->toBe('kg');

    wooFake([
        'POST webhooks' => Http::sequence()->push(['id' => 7, 'topic' => 'order.created'], 201)->push(['id' => 8, 'topic' => 'product.deleted'], 201),
    ]);
    CheckWooChannel::run($again);

    expect(Arr::get($again->fresh()->settings, 'webhooks'))->toBe(['order_created' => 7, 'product_deleted' => 8]);
});

test('a parked channel whose store answers again is reported on the first run of the day but not revived', function () {
    $parked = wooConnect(wooCustomer($this->shop))->customerSalesChannel;
    $parked->update(['ping_error_count' => PingActiveWooChannel::PARKED_AFTER_FAILURES, 'platform_status' => false, 'state' => CustomerSalesChannelStateEnum::NOT_READY]);

    wooFake();

    Carbon::setTestNow(Carbon::parse('2026-09-06 15:00:00'));
    Artisan::call('woo:ping_active_channel');
    expect(Artisan::output())->not->toContain($parked->slug);

    Carbon::setTestNow(Carbon::parse('2026-09-07 00:10:00'));
    Artisan::call('woo:ping_active_channel');
    Carbon::setTestNow();

    expect(Artisan::output())->toContain('answers again')->toContain($parked->slug)
        ->and($parked->fresh()->ping_error_count)->toBe(PingActiveWooChannel::PARKED_AFTER_FAILURES)
        ->and($parked->fresh()->platform_status)->toBeFalse()
        ->and($parked->fresh()->state)->toBe(CustomerSalesChannelStateEnum::NOT_READY);

    $parked->user->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);
    Artisan::call('woo:check', ['customerSalesChannel' => $parked->slug]);

    expect($parked->fresh()->platform_status)->toBeTrue()
        ->and($parked->fresh()->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and($parked->fresh()->ping_error_count)->toBe(0);
});

test('a parked channel stays parked when the customer has already connected the same store again', function () {
    $customer = wooCustomer($this->shop);
    $parked   = wooConnect($customer, ['name' => 'old-life', 'store_url' => 'https://again.example.test'])->customerSalesChannel;
    $parked->update(['ping_error_count' => PingActiveWooChannel::PARKED_AFTER_FAILURES, 'platform_status' => false, 'state' => CustomerSalesChannelStateEnum::NOT_READY]);
    $parked->user->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);

    $replacement = wooConnect($customer, ['name' => 'new-life', 'store_url' => 'https://tmp.example.test'])->customerSalesChannel;
    $replacement->user->update(['store_url' => 'https://Again.example.test/', 'settings' => ['webhooks' => ['order_created' => 3, 'product_deleted' => 4], 'weight_option' => 'kg']]);

    wooFake();
    Carbon::setTestNow(Carbon::parse('2026-09-07 00:10:00'));
    Artisan::call('woo:ping_active_channel');
    Carbon::setTestNow();

    expect(Artisan::output())->not->toContain($parked->slug)
        ->and($parked->fresh()->ping_error_count)->toBe(PingActiveWooChannel::PARKED_AFTER_FAILURES)
        ->and($parked->fresh()->platform_status)->toBeFalse()
        ->and($replacement->fresh()->platform_status)->toBeTrue();
});

test('staff get a week-long reconnect link for a dark woo channel and the callback on it reconnects the same user', function () {
    $wooCommerceUser = wooConnect(wooCustomer($this->shop));
    $wooCommerceUser->update(['settings' => ['webhooks' => ['order_created' => 1, 'product_deleted' => 2], 'weight_option' => 'kg']]);
    $channel = $wooCommerceUser->customerSalesChannel;

    $channel->update(['platform_status' => true, 'state' => CustomerSalesChannelStateEnum::AUTHENTICATED]);
    expect(ShowCustomerSalesChannel::make()->getReconnectLink($channel->refresh()))->toBeNull();

    $channel->update(['platform_status' => false, 'state' => CustomerSalesChannelStateEnum::NOT_READY]);
    $link = ShowCustomerSalesChannel::make()->getReconnectLink($channel->refresh());

    expect($link)->toStartWith(WOO_STORE_URL.'/wc-auth/v1/authorize?');
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
    expect($query['scope'])->toBe('read_write')
        ->and($query['callback_url'])->toBe(route('webhooks.woo.callback'));

    Carbon::setTestNow(now()->addDays(6));
    $payload = CallbackRetinaWooCommerceUser::make()->getWooAuthorizationTokenPayload($query['user_id']);
    expect(Arr::get($payload, 'woo_commerce_user_id'))->toBe($wooCommerceUser->id);

    wooFake();
    CallbackRetinaWooCommerceUser::make()->handleReAuthorization($wooCommerceUser, ['consumer_key' => 'ck_again', 'consumer_secret' => 'cs_again']);
    Carbon::setTestNow();

    expect($wooCommerceUser->fresh()->consumer_key)->toBe('ck_again')
        ->and($channel->fresh()->platform_status)->toBeTrue()
        ->and($channel->fresh()->state)->toBe(CustomerSalesChannelStateEnum::AUTHENTICATED)
        ->and(ShowCustomerSalesChannel::make()->getReconnectLink($channel->fresh()))->toBeNull();
});
