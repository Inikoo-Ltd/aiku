<?php

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\StoreNewProductToCurrentWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Product\DispatchWooUploadQueue;
use Illuminate\Support\Facades\Redis;
use App\Jobs\BoundedUniqueJobDecorator;
use App\Actions\Dropshipping\WooCommerce\StoreWooCommerceUser;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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
/**
 * The ping walks every Woo channel in the database, including ones earlier tests left with blank
 * settings, whose check registers webhooks and reads the weight unit.
 */
function wooFakeForPing(array $handlers = []): void
{
    wooFake(array_merge([
        'POST webhooks'                                 => fn () => Http::response(['id' => random_int(100, 999)], 201),
        'GET settings/products/woocommerce_weight_unit' => Http::response(['value' => 'kg']),
    ], $handlers));
}

function wooFake(array $handlers = []): void
{
    $handlers = array_merge([
        'GET settings'                                     => Http::response(wooSettingsGroups()),
        'GET settings/products/woocommerce_dimension_unit' => Http::response(['value' => 'cm']),
    ], $handlers);

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

function wooVariation(int $id, string $sku, string $option, array $overrides = []): array
{
    return array_merge([
        'id'             => $id,
        'sku'            => $sku,
        'price'          => '5.50',
        'status'         => 'publish',
        'manage_stock'   => true,
        'stock_quantity' => 3,
        'attributes'     => [['id' => 1, 'name' => 'Scent', 'option' => $option]],
        'image'          => ['id' => $id, 'src' => WOO_STORE_URL.'/wp-content/uploads/v'.$id.'.jpg'],
    ], $overrides);
}

function wooClearUploadQueue(WooCommerceUser $wooCommerceUser): void
{
    $funnel = StoreNewProductToCurrentWooCommerce::funnelName($wooCommerceUser);

    Redis::del(DispatchWooUploadQueue::queueKey($wooCommerceUser), DispatchWooUploadQueue::reservedKey($wooCommerceUser), ...array_map(fn (int $slot) => $funnel.$slot, range(1, StoreNewProductToCurrentWooCommerce::MAX_CONCURRENT_CREATES_PER_STORE)));
}

function wooUploadPortfolios($test, CustomerSalesChannel $channel, int $count, string $skuPrefix): \Illuminate\Support\Collection
{
    return collect(range(1, $count))
        ->map(fn (int $number) => wooPortfolio($channel, $number === 1 ? $test->product : wooSecondProduct($test->shop, $test->product), null, $skuPrefix.'-'.$number));
}

/**
 * @return \Illuminate\Support\Collection<int, BoundedUniqueJobDecorator>
 */
function wooQueuedCreates(): \Illuminate\Support\Collection
{
    return Queue::pushed(BoundedUniqueJobDecorator::class, fn (BoundedUniqueJobDecorator $job) => $job->getAction() instanceof StoreNewProductToCurrentWooCommerce)->values();
}

function wooHoldCreateSlots(WooCommerceUser $wooCommerceUser, int $slots, Closure $whileHeld): void
{
    if ($slots === 0) {
        $whileHeld();

        return;
    }

    Redis::funnel(StoreNewProductToCurrentWooCommerce::funnelName($wooCommerceUser))
        ->limit(StoreNewProductToCurrentWooCommerce::MAX_CONCURRENT_CREATES_PER_STORE)
        ->releaseAfter(60)
        ->block(0)
        ->then(fn () => wooHoldCreateSlots($wooCommerceUser, $slots - 1, $whileHeld));
}
