<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\CountOpenOrdersAffectedByUnitsChange;
use App\Actions\Catalogue\Product\UI\EditProduct;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);
});

function orderWithProduct($customer, $product): Order
{
    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($modelData, 'delivery_address', new Address(Address::factory()->definition()));

    $order = StoreOrder::make()->action($customer, $modelData);

    StoreTransaction::make()->action(
        $order,
        $product->historicAsset,
        Transaction::factory()->definition()
    );

    return $order->refresh();
}

test('no confirmation is asked when the product has no open orders', function () {
    expect(CountOpenOrdersAffectedByUnitsChange::run($this->product))->toBe(0)
        ->and(EditProduct::make()->getUnitsChangeConfirmation($this->product))->toBeNull();
});

test('changing units asks for confirmation naming the open orders it re-means', function () {
    orderWithProduct($this->customer, $this->product);

    expect(CountOpenOrdersAffectedByUnitsChange::run($this->product))->toBe(1);

    $confirmation = EditProduct::make()->getUnitsChangeConfirmation($this->product);

    expect($confirmation)->toBeArray()
        ->and($confirmation['title'])->toContain('1 open order')
        ->and($confirmation['description'])->toContain('1 order');
});

test('a submitted order still counts, it is the one the warehouse can still get wrong', function () {
    $order   = orderWithProduct($this->customer, $this->product);
    $counted = CountOpenOrdersAffectedByUnitsChange::run($this->product);

    SubmitOrder::make()->action($order);

    expect(CountOpenOrdersAffectedByUnitsChange::run($this->product->refresh()))->toBe($counted);
});
