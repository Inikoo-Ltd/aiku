<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 00:00:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\ApproveCustomer;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

beforeEach(
    /**
     * @throws \Throwable
     */
    function () {
        $this->organisation = createOrganisation();
        $this->group        = $this->organisation->group;

        $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        $dropshippingShop = Shop::where('type', ShopTypeEnum::DROPSHIPPING)->first();
        if (!$dropshippingShop) {
            $storeData = Shop::factory()->definition();
            data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);
            $dropshippingShop = StoreShop::make()->action($this->organisation, $storeData);
        }
        $this->dropshippingShop = UpdateShop::make()->action($dropshippingShop, ['state' => ShopStateEnum::OPEN]);

        $this->dropshippingCustomer = createCustomer($this->dropshippingShop);
        list($this->tradeUnit, $this->product) = createProduct($this->dropshippingShop);
        $this->product = UpdateProduct::make()->action($this->product, ['status' => ProductStatusEnum::FOR_SALE]);

        $this->dropshippingChannel = StoreCustomerSalesChannel::make()->action(
            $this->dropshippingCustomer,
            $platform,
            []
        );

        $this->warehouse = createWarehouse();
        $this->fulfilment = createFulfilment($this->organisation);
        $this->fulfilmentShop = UpdateShop::make()->action($this->fulfilment->shop, ['state' => ShopStateEnum::OPEN]);
        list($this->fulfilmentTradeUnit, $this->fulfilmentProduct) = createProduct($this->fulfilmentShop);

        $this->fulfilmentCustomer = createCustomer($this->fulfilmentShop);
        ApproveCustomer::make()->action($this->fulfilmentCustomer, []);
        $this->fulfilmentCustomer->refresh();

        $this->fulfilmentChannel = StoreCustomerSalesChannel::make()->action(
            $this->fulfilmentCustomer,
            $platform,
            []
        );
    }
);

// ---- Profile ----

test('retina api get profile', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $response = getJson(route('retina.api.profile'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'reference', 'name', 'email'],
    ]);
});

test('retina api get profile unauthenticated', function () {
    $response = getJson(route('retina.api.profile'));
    $response->assertUnauthorized();
});

// ---- Dropshipping: clients ----

test('retina api dropshipping store client', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $response = postJson(route('retina.api.dropshipping.clients.create'), CustomerClient::factory()->definition());
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);
});

test('retina api dropshipping clients flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $response = getJson(route('retina.api.dropshipping.clients.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'ulid', 'reference', 'name', 'email']],
    ]);

    $response = getJson(route('retina.api.dropshipping.clients.show', $client));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.clients.update', $client), [
        'contact_name' => 'Updated Contact',
    ]);
    $response->assertOk();
    expect($response->json('data.contact_name'))->toBe('Updated Contact');

    $response = deleteJson(route('retina.api.dropshipping.clients.delete', $client));
    $response->assertOk();
    expect($client->refresh()->status)->toBeFalse();
});

// ---- Dropshipping: orders ----

test('retina api dropshipping orders flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $response = postJson(route('retina.api.dropshipping.order.store', $client));
    $response->assertOk();
    $orderId = $response->json('data.id');
    $order   = Order::find($orderId);

    $response = getJson(route('retina.api.dropshipping.order.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state']],
    ]);

    $response = getJson(route('retina.api.dropshipping.order.show', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state', 'customer'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.order.update', $order), [
        'public_notes' => 'Please ship fast',
    ]);
    $response->assertOk();

    $response = deleteJson(route('retina.api.dropshipping.order.delete', $order));
    $response->assertOk();
});

test('retina api dropshipping order submit', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $order = StoreOrder::make()->action($client, [
        'platform_id'               => $this->dropshippingChannel->platform_id,
        'customer_sales_channel_id' => $this->dropshippingChannel->id,
    ]);

    $response = patchJson(route('retina.api.dropshipping.order.submit', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'state'],
    ]);
});

// ---- Dropshipping: products & portfolios ----

test('retina api dropshipping index products', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $response = getJson(route('retina.api.dropshipping.products.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'price']],
    ]);
});

test('retina api dropshipping portfolio flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $response = postJson(route('retina.api.dropshipping.products.my_product.store', $this->product));
    $response->assertCreated();
    $portfolioId = $response->json('data.id');

    $response = getJson(route('retina.api.dropshipping.products.my_product.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'name']],
    ]);

    $response = getJson(route('retina.api.dropshipping.products.my_product.show', $portfolioId));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'name'],
    ]);

    $response = patchJson(route('retina.api.dropshipping.products.my_product.update', $portfolioId), [
        'customer_product_name' => 'My Custom Product',
    ]);
    $response->assertOk();
    expect($response->json('data.customer_product_name'))->toBe('My Custom Product');

    $response = deleteJson(route('retina.api.dropshipping.products.my_product.delete', $portfolioId));
    $response->assertOk();
});

// ---- Dropshipping: order transactions ----

test('retina api dropshipping order transactions flow', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->dropshippingChannel,
        CustomerClient::factory()->definition()
    );

    $order = StoreOrder::make()->action($client, [
        'platform_id'               => $this->dropshippingChannel->platform_id,
        'customer_sales_channel_id' => $this->dropshippingChannel->id,
    ]);

    $portfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action(
        $this->dropshippingChannel,
        $this->product,
        []
    );

    $response = postJson(route('retina.api.dropshipping.order.transaction.store', [$order, $portfolio]), [
        'quantity_ordered' => 2,
    ]);
    $response->assertCreated();
    $transactionId = $response->json('data.id');

    $response = getJson(route('retina.api.dropshipping.order.transaction.index', $order));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'quantity_ordered']],
    ]);

    $response = patchJson(route('retina.api.dropshipping.transaction.update', $transactionId), [
        'quantity_ordered' => 3,
    ]);
    $response->assertOk();
    expect($response->json('data.quantity_ordered'))->toBe(3);

    $response = deleteJson(route('retina.api.dropshipping.transaction.delete', $transactionId));
    $response->assertOk();
});

test('retina api dropshipping data feed csv', function () {
    Sanctum::actingAs($this->dropshippingChannel, ['retina']);

    $response = getJson(route('retina.api.dropshipping.data_feed.csv'));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

// ---- Fulfilment: clients ----

test('retina api fulfilment clients flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->fulfilmentChannel,
        CustomerClient::factory()->definition()
    );

    $response = getJson(route('retina.api.fulfilment.clients.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'ulid', 'reference', 'name']],
    ]);

    $response = getJson(route('retina.api.fulfilment.clients.show', $client));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.clients.update', $client), [
        'contact_name' => 'Updated Fulfilment Contact',
    ]);
    $response->assertOk();
    expect($response->json('data.contact_name'))->toBe('Updated Fulfilment Contact');

    $response = deleteJson(route('retina.api.fulfilment.clients.delete', $client));
    $response->assertOk();
    expect($client->refresh()->status)->toBeFalse();
});

test('retina api fulfilment store client', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina']);

    $response = postJson(route('retina.api.fulfilment.clients.create'), CustomerClient::factory()->definition());
    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'ulid', 'reference', 'name'],
    ]);
});

// ---- Fulfilment: orders ----

test('retina api fulfilment orders flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina']);

    $client = StoreCustomerClient::make()->action(
        $this->fulfilmentChannel,
        CustomerClient::factory()->definition()
    );

    $response = postJson(route('retina.api.fulfilment.order.store', $client));
    $response->assertCreated();
    $palletReturnId = $response->json('data.id');
    $palletReturn    = \App\Models\Fulfilment\PalletReturn::find($palletReturnId);

    $response = getJson(route('retina.api.fulfilment.order.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state']],
    ]);

    $response = getJson(route('retina.api.fulfilment.order.show', $palletReturn));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.order.update', $palletReturn), [
        'customer_notes' => 'Handle with care',
    ]);
    $response->assertOk();

    $response = patchJson(route('retina.api.fulfilment.order.submit', $palletReturn));
    $response->assertOk();
    expect($response->json('data.state'))->not->toBeNull();

    $response = postJson(route('retina.api.fulfilment.order.cancel', $palletReturn));
    $response->assertOk();
});

// ---- Fulfilment: portfolios ----

test('retina api fulfilment portfolio flow', function () {
    Sanctum::actingAs($this->fulfilmentChannel, ['retina']);

    $response = postJson(route('retina.api.fulfilment.portfolios.store', $this->fulfilmentProduct));
    $response->assertCreated();
    $portfolioId = $response->json('data.id');

    $response = getJson(route('retina.api.fulfilment.portfolios.index'));
    $response->assertOk();

    $response = getJson(route('retina.api.fulfilment.portfolios.show', $portfolioId));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id'],
    ]);

    $response = patchJson(route('retina.api.fulfilment.portfolios.update', $portfolioId), [
        'customer_product_name' => 'My Fulfilment Product',
    ]);
    $response->assertOk();
    expect($response->json('data.customer_product_name'))->toBe('My Fulfilment Product');

    $response = deleteJson(route('retina.api.fulfilment.portfolios.delete', $portfolioId));
    $response->assertOk();
});
