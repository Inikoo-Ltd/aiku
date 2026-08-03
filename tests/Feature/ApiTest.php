<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2026 00:00:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeAll(function () {
    loadDB();
});

beforeEach(
    /**
     * @throws \Throwable
     */
    function () {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();
        $this->customer = createCustomer($this->shop);
        $this->group    = $this->organisation->group;

        Sanctum::actingAs($this->user);
    }
);

test('get api profile', function () {
    $response = getJson(route('grp.api.profile'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'username', 'contact_name', 'email'],
    ]);
});

test('get api profile unauthenticated', function () {
    $this->app['auth']->forgetGuards();

    $response = getJson(route('grp.api.profile'));
    $response->assertUnauthorized();
});

test('show api group', function () {
    $response = getJson(route('grp.api.group.show'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name'],
    ]);
});

test('index api organisations', function () {
    $response = getJson(route('grp.api.organisations.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'name', 'type', 'code']],
    ]);
});

test('show api organisation', function () {
    $response = getJson(route('grp.api.organisations.show', $this->organisation));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name', 'type'],
    ]);
});

test('index api shops in organisation', function () {
    $response = getJson(route('grp.api.organisations.show.shops.index', $this->organisation));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'type', 'state']],
    ]);
});

test('index api shops', function () {
    $response = getJson(route('grp.api.shops.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'type', 'state']],
    ]);
});

test('show api shop', function () {
    $response = getJson(route('grp.api.shops.show.', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name', 'state'],
    ]);
});

test('index api orders', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.orders.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state', 'net_amount', 'total_amount', 'date']],
    ]);
});

test('show api order', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.orders.show', [$this->shop, $order]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state', 'net_amount', 'total_amount', 'date', 'customer'],
    ]);
});

test('index api customers', function () {
    $response = getJson(route('grp.api.shops.show.customers.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'name', 'email']],
    ]);
});

test('show api customer', function () {
    $response = getJson(route('grp.api.shops.show.customers.show', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['slug', 'reference', 'name', 'email'],
    ]);
});

test('index api invoices in customer', function () {
    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.customers.invoices', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'total_amount', 'net_amount']],
    ]);
});

test('index api orders in customer', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.customers.orders', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state', 'net_amount', 'total_amount']],
    ]);
});

test('index api invoices', function () {
    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.invoices.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'total_amount', 'net_amount']],
    ]);
});

test('show api invoice', function () {
    $invoice = StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.invoices.show', [$this->shop, $invoice]));
    $response->assertOk();
    $response->assertJsonStructure([
        'slug', 'reference', 'total_amount', 'net_amount', 'currency_code',
    ]);
});
