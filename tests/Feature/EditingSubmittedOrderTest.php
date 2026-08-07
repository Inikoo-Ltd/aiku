<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionProductQuantityOrdered;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Transaction;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;
    $this->shop->updateQuietly(['is_aiku' => true]);
    $this->warehouse = createWarehouse();

    $this->customer = createCustomer($this->shop);

    list(, $this->product) = createProduct($this->shop);

    if ($this->product->orgStocks->isEmpty()) {
        $stock = $this->group->stocks()->first();
        SyncStockTradeUnits::run($stock, [
            $this->product->tradeUnits->first()->id => ['quantity' => 1]
        ]);
        SyncProductOrgStocksFromTradeUnits::run($this->product);
        $this->product->refresh();
    }

    actingAs($this->user);
});

function submittedOrderWithTransaction($customer, $product, float $quantity = 10): array
{
    $order = StoreOrder::make()->action($customer, []);
    $transaction = StoreTransaction::make()->action($order, $product->historicAsset, ['quantity_ordered' => $quantity]);
    $order = SubmitOrder::make()->action($order->refresh());

    return [$order, $transaction->refresh()];
}

test('quantity of a submitted order without delivery note can be changed', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);

    $oldNetAmount = (float)$order->net_amount;

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
        'quantity_ordered' => 4
    ], $this->user);

    $transaction->refresh();
    $order->refresh();

    expect((float)$transaction->quantity_ordered)->toBe(4.0)
        ->and((float)$order->net_amount)->toBeLessThan($oldNetAmount)
        ->and($order->post_submit_modification_data)->toHaveCount(1)
        ->and($order->post_submit_modification_data[0]['modified_by'])->toBe($this->user->username)
        ->and($order->post_submit_modification_data[0]['data']['transaction_id'])->toBe($transaction->id);
});

test('quantity change of an in-warehouse order updates delivery note items and flags them dirty', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);

    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();
    expect($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE)
        ->and($deliveryNote->deliveryNoteItems()->count())->toBeGreaterThan(0);

    $itemsBefore = $deliveryNote->deliveryNoteItems()->get()->keyBy('id');

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
        'quantity_ordered' => 6
    ], $this->user);

    $transaction->refresh();
    $orgStocks = $this->product->orgStocks->keyBy('id');

    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        $before        = $itemsBefore[$item->id];
        $expectedQty   = $orgStocks[$item->org_stock_id]->pivot->quantity * 6;

        expect((float)$item->quantity_required)->toBe((float)$expectedQty)
            ->and($item->is_dirty)->toBeTrue()
            ->and((float)$item->original_quantity_required)->toBe((float)$before->quantity_required);
    }
});

test('an unchanged quantity does not flag delivery note items dirty', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
        'quantity_ordered' => (float)$transaction->quantity_ordered
    ], $this->user);

    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        expect($item->is_dirty)->toBeFalse();
    }
});

test('setting quantity to zero on a submitted order deletes the transaction and its delivery note items', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->update(['state' => OrderStateEnum::SUBMITTED]);

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
        'quantity_ordered' => 0
    ], $this->user);

    expect(Transaction::find($transaction->id))->toBeNull()
        ->and($deliveryNote->deliveryNoteItems()->count())->toBe(0);
});

test('orders of external shops cannot be modified', function () {
    [, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $this->shop->updateQuietly(['type' => ShopTypeEnum::EXTERNAL]);

    expect(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction->refresh(), [
        'quantity_ordered' => 4
    ], $this->user))->toThrow(HttpException::class);
});

test('dispatched orders cannot be modified', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $order->updateQuietly(['state' => OrderStateEnum::DISPATCHED]);

    expect(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction->refresh(), [
        'quantity_ordered' => 4
    ], $this->user))->toThrow(HttpException::class);
});
