<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Ordering\Order\SaveOrderModification;
use App\Actions\Ordering\Order\StoreFollowUpOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionProductQuantityOrdered;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
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
    $this->shop->updateQuietly(['is_aiku' => true, 'type' => ShopTypeEnum::B2B]);
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

test('a line added after submission is born submitted and reaches the warehouse', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);

    $lateTransaction = StoreTransaction::make()->action($order, $this->product->historicAsset, ['quantity_ordered' => 3], strict: false);

    $lateTransaction->refresh();
    expect($lateTransaction->state)->toEqual(TransactionStateEnum::SUBMITTED->value)
        ->and($lateTransaction->status)->toEqual(TransactionStatusEnum::PROCESSING->value)
        ->and((float)$lateTransaction->submitted_quantity_ordered)->toBe(3.0)
        ->and($lateTransaction->submitted_at)->not->toBeNull();

    $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), []);

    expect($deliveryNote->deliveryNoteItems()->where('transaction_id', $lateTransaction->id)->count())->toBeGreaterThan(0)
        ->and($lateTransaction->refresh()->state)->toEqual(TransactionStateEnum::IN_WAREHOUSE->value);
});

test('stuck creating lines on a submitted order still get sent to warehouse', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $transaction->updateQuietly(['state' => TransactionStateEnum::CREATING]);

    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    expect($deliveryNote->deliveryNoteItems()->count())->toBeGreaterThan(0)
        ->and($transaction->refresh()->state)->toEqual(TransactionStateEnum::IN_WAREHOUSE->value);
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

function productAddedLater(Product $product): Product
{
    $lateProduct = StoreProduct::make()->action($product->family, array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [
                [
                    'id'       => $product->tradeUnits->first()->id,
                    'quantity' => 1
                ]
            ],
            'price'       => 50,
        ]
    ));
    $lateProduct = UpdateProduct::make()->action($lateProduct, ['state' => ProductStateEnum::ACTIVE]);
    SyncProductOrgStocksFromTradeUnits::run($lateProduct);

    return $lateProduct->refresh();
}

test('a product added while the note is being picked reaches the picker and the order stops reading as paid', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::HANDLING]);
    $order->refresh();
    $order->updateQuietly(['pay_status' => OrderPayStatusEnum::PAID, 'payment_amount' => $order->total_amount]);
    $totalBefore = (float)$order->total_amount;

    $lateProduct = productAddedLater($this->product);

    $order = SaveOrderModification::make()->action($order, [
        'products' => [$lateProduct->id => ['quantity_ordered' => 3]]
    ], $this->user);

    $lateTransaction = $order->transactions()->where('model_id', $lateProduct->id)->first();
    $lateItems       = $deliveryNote->deliveryNoteItems()->where('transaction_id', $lateTransaction->id)->get();

    expect($lateTransaction->state)->toEqual(TransactionStateEnum::IN_WAREHOUSE->value)
        ->and((float)$lateTransaction->submitted_quantity_ordered)->toBe(3.0)
        ->and($lateItems)->not->toBeEmpty()
        ->and($lateItems->every(fn ($item) => $item->state == DeliveryNoteItemStateEnum::HANDLING))->toBeTrue()
        ->and((float)$order->total_amount)->toBeGreaterThan($totalBefore)
        ->and($order->pay_status)->not->toEqual(OrderPayStatusEnum::PAID)
        ->and($order->post_submit_modification_data)->toHaveCount(1);
});

test('adding a product the order already has raises that line instead of duplicating it', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    SaveOrderModification::make()->action($order->refresh(), [
        'products' => [$this->product->id => ['quantity_ordered' => 2]]
    ], $this->user);

    $orgStocks = $this->product->orgStocks->keyBy('id');

    expect($order->transactions()->where('model_id', $this->product->id)->count())->toBe(1)
        ->and((float)$transaction->refresh()->quantity_ordered)->toBe(12.0);

    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        expect((float)$item->quantity_required)->toBe((float)($orgStocks[$item->org_stock_id]->pivot->quantity * 12));
    }
});

test('a product cannot be added once the note is picked', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::PICKED]);

    $lateProduct = productAddedLater($this->product);

    expect(fn () => SaveOrderModification::make()->action($order->refresh(), [
        'products' => [$lateProduct->id => ['quantity_ordered' => 1]]
    ], $this->user))->toThrow(fn (HttpException $exception) => expect($exception->getStatusCode())->toBe(409));

    expect($order->transactions()->where('model_id', $lateProduct->id)->exists())->toBeFalse();
});

test('the chat panel offers adding items only to orders still ahead of picked', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);

    $addItems = fn () => collect(GetChatCustomerProfile::make()->contactAndLastOrders($this->customer->refresh())['last_orders'])
        ->firstWhere('reference', $order->reference)['add_items'];

    request()->setUserResolver(fn () => $this->user);

    expect($addItems())->toHaveKeys(['products', 'save']);

    $order->updateQuietly(['state' => OrderStateEnum::PICKED]);

    expect($addItems())->toBeNull();
});

test('a follow-up order tells the warehouse on both orders to send them together', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh()->updateQuietly(['state' => OrderStateEnum::PICKED, 'private_warehouse_note' => 'Fragile']);

    $followUpOrder = StoreFollowUpOrder::make()->action($order);

    expect($followUpOrder->state)->toEqual(OrderStateEnum::CREATING)
        ->and($followUpOrder->customer_id)->toBe($order->customer_id)
        ->and($followUpOrder->private_warehouse_note)->toContain($order->reference)
        ->and($order->refresh()->private_warehouse_note)->toContain('Fragile')->toContain($followUpOrder->reference)
        ->and($deliveryNote->refresh()->private_warehouse_note)->toContain($followUpOrder->reference);
});

test('a follow-up order is refused while items can still be added to the order', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);

    expect(fn () => StoreFollowUpOrder::make()->action($order))->toThrow(HttpException::class);
});
