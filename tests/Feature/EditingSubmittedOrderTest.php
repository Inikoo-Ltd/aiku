<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Dispatching\CrossDispatch\GetCrossDispatchCoverage;
use App\Actions\Dispatching\CrossDispatch\ResolveCrossDispatch;
use App\Actions\Dispatching\CrossDispatch\UpdateCrossDispatchSettings;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Production\JobOrder\FillToOrder;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Country;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\PreOrder;
use App\Models\Production\Artefact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Ordering\Order\SaveOrderModification;
use App\Actions\Ordering\Order\StoreFollowUpOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UI\GetEcomOrderActions;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionProductQuantityOrdered;
use App\Actions\Ordering\UpcomingTransaction\StoreUpcomingTransaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ProcurementHelpers.php';

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

test('quantity can be changed when the only delivery note was cancelled', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    CancelDeliveryNote::make()->action($deliveryNote, $this->user);
    $order->updateQuietly(['state' => OrderStateEnum::SUBMITTED]);

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
        'quantity_ordered' => 9
    ], $this->user);

    expect((float)$transaction->refresh()->quantity_ordered)->toBe(9.0);
});

test('quantity change is refused when the order is still in the warehouse but its only delivery note was cancelled', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    CancelDeliveryNote::make()->action($deliveryNote, $this->user, false);

    expect($order->refresh()->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
            'quantity_ordered' => 9
        ], $this->user))->toThrow(fn (HttpException $exception) => expect($exception->getStatusCode())->toBe(409));
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
        ->and($lateItems->every(fn ($item) => $item->is_dirty))->toBeTrue()
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

test('a product added once the note is picked walks the note and the order back to picking', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::PICKED]);
    $order->refresh()->updateQuietly(['state' => OrderStateEnum::PICKED]);

    $lateProduct = productAddedLater($this->product);

    $order = SaveOrderModification::make()->action($order->refresh(), [
        'products' => [$lateProduct->id => ['quantity_ordered' => 1]]
    ], $this->user);

    $lateTransaction = $order->transactions()->where('model_id', $lateProduct->id)->first();
    $lateItems       = $deliveryNote->deliveryNoteItems()->where('transaction_id', $lateTransaction->id)->get();

    expect($deliveryNote->refresh()->state)->toEqual(DeliveryNoteStateEnum::HANDLING)
        ->and($order->state)->toEqual(OrderStateEnum::HANDLING)
        ->and($lateItems)->not->toBeEmpty()
        ->and($lateItems->every(fn ($item) => $item->state == DeliveryNoteItemStateEnum::HANDLING && $item->is_dirty))->toBeTrue();
});

test('a product cannot be added once the note is being packed', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::PACKING]);

    $lateProduct = productAddedLater($this->product);

    expect(fn () => SaveOrderModification::make()->action($order->refresh(), [
        'products' => [$lateProduct->id => ['quantity_ordered' => 1]]
    ], $this->user))->toThrow(fn (HttpException $exception) => expect($exception->getStatusCode())->toBe(409));

    expect($order->transactions()->where('model_id', $lateProduct->id)->exists())->toBeFalse();
});

test('the chat panel offers adding items only to orders still ahead of packing', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);

    $addItems = fn () => collect(GetChatCustomerProfile::make()->contactAndLastOrders($this->customer->refresh())['last_orders'])
        ->firstWhere('reference', $order->reference)['add_items'];

    request()->setUserResolver(fn () => $this->user);

    expect($addItems())->toHaveKeys(['products', 'save']);

    $order->updateQuietly(['state' => OrderStateEnum::PACKING]);

    expect($addItems())->toBeNull();
});

test('a follow-up order tells the warehouse on both orders to send them together', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh()->updateQuietly(['state' => OrderStateEnum::PACKING, 'private_warehouse_note' => 'Fragile']);

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

test('the order page offers adding a product while the order is in the warehouse', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $hasAddToWarehouseOrder = fn () => collect(GetEcomOrderActions::run($order->refresh(), true))->contains('key', 'add-product-to-warehouse-order');

    expect($hasAddToWarehouseOrder())->toBeFalse();

    SendOrderToWarehouse::make()->action($order, []);
    expect($hasAddToWarehouseOrder())->toBeTrue();

    $order->updateQuietly(['state' => OrderStateEnum::PICKED]);
    expect($hasAddToWarehouseOrder())->toBeTrue();

    $order->updateQuietly(['state' => OrderStateEnum::PACKING]);
    expect($hasAddToWarehouseOrder())->toBeFalse();
});

function pickedOrderWithGift($customer, $product, float $giftQuantity = 2): array
{
    [$order] = submittedOrderWithTransaction($customer, $product);
    $gift = StoreTransaction::make()->action($order, $product->historicAsset, [
        'quantity_ordered' => 0,
        'quantity_bonus'   => $giftQuantity,
        'is_gift'          => true,
    ], strict: false);

    $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), []);
    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        $item->updateQuietly(['quantity_picked' => $item->quantity_required]);
    }
    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::PICKED]);
    $order->refresh()->updateQuietly(['state' => OrderStateEnum::PICKED]);

    return [$order->refresh(), $gift->refresh(), $deliveryNote];
}

test('a free gift can be removed from a picked order and the note goes back to picking', function () {
    [$order, $gift, $deliveryNote] = pickedOrderWithGift($this->customer, $this->product);

    expect($deliveryNote->deliveryNoteItems()->where('transaction_id', $gift->id)->exists())->toBeTrue();

    SaveOrderModification::make()->action($order, [
        'transactions' => [$gift->id => ['newQty' => 0]]
    ], $this->user);

    $giftItems = $deliveryNote->deliveryNoteItems()->where('transaction_id', $gift->id)->get();

    expect((float)$gift->refresh()->quantity_bonus)->toBe(0.0)
        ->and((float)$gift->quantity_ordered)->toBe(0.0)
        ->and($giftItems->every(fn ($item) => (float)$item->quantity_required == 0))->toBeTrue()
        ->and($deliveryNote->refresh()->state)->toEqual(DeliveryNoteStateEnum::HANDLING)
        ->and($order->refresh()->state)->toEqual(OrderStateEnum::HANDLING);
});

test('a free gift can be lowered but never raised', function () {
    [$order, $gift] = pickedOrderWithGift($this->customer, $this->product, 3);

    UpdateTransactionProductQuantityOrdered::make()->action($gift, ['quantity_ordered' => 1], $this->user);

    expect((float)$gift->refresh()->quantity_bonus)->toBe(1.0)
        ->and(fn () => UpdateTransactionProductQuantityOrdered::make()->action($gift, ['quantity_ordered' => 2], $this->user))
        ->toThrow(fn (HttpException $exception) => expect($exception->getStatusCode())->toBe(422))
        ->and((float)$gift->refresh()->quantity_bonus)->toBe(1.0)
        ->and((float)$order->refresh()->net_amount)->toBeGreaterThan(0.0);
});

test('a removed follow-on gift goes back to the customer to ship with the next order', function () {
    [$order, $gift] = pickedOrderWithGift($this->customer, $this->product);

    $upcomingTransaction = StoreUpcomingTransaction::run($this->customer, [
        'product_id'     => $this->product->id,
        'quantity'       => 2,
        'type'           => UpcomingTransactionTypeEnum::FOLLOW_ON,
        'state'          => UpcomingTransactionStateEnum::APPLIED,
        'order_id'       => $order->id,
        'transaction_id' => $gift->id,
    ]);

    UpdateTransactionProductQuantityOrdered::make()->action($gift, ['quantity_ordered' => 0], $this->user);

    $upcomingTransaction->refresh();

    expect($upcomingTransaction->state)->toEqual(UpcomingTransactionStateEnum::READY)
        ->and($upcomingTransaction->order_id)->toBeNull()
        ->and($upcomingTransaction->transaction_id)->toBeNull();

    $upcomingTransaction->delete();
});

test('a free gift removed before the order reaches the warehouse is deleted, so no empty line is sent to picking', function () {
    [$order] = submittedOrderWithTransaction($this->customer, $this->product);
    $gift = StoreTransaction::make()->action($order, $this->product->historicAsset, [
        'quantity_ordered' => 0,
        'quantity_bonus'   => 2,
        'is_gift'          => true,
    ], strict: false);

    UpdateTransactionProductQuantityOrdered::make()->action($gift->refresh(), ['quantity_ordered' => 0], $this->user);

    $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), []);

    expect(Transaction::find($gift->id))->toBeNull()
        ->and($deliveryNote->deliveryNoteItems()->where('transaction_id', $gift->id)->exists())->toBeFalse();
});

/**
 * A submitted dropshipping order of the test shop and a second company that could ship it whole: direction switched on by both, same SKO content, plenty of stock, customs crossing allowed.
 *
 * @return array{0: \App\Models\Ordering\Order, 1: \App\Models\SysAdmin\Organisation, 2: OrgStock, 3: OrgStock, 4: \App\Models\Procurement\OrgPartner}
 */
function crossDispatchOrder($test, float $quantity = 10): array
{
    [$order]    = submittedOrderWithTransaction($test->customer, $test->product, $quantity);
    $orgPartner = crossDispatchDirection($test->organisation);
    $dispatcher = $orgPartner->partner;
    $test->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
    UpdateCrossDispatchSettings::make()->action($orgPartner, $test->organisation, ['enabled' => true, 'allow_customs_crossing' => true]);
    UpdateCrossDispatchSettings::make()->action($orgPartner, $dispatcher, ['enabled' => true]);

    $homeSko = $test->product->orgStocks()->orderBy('org_stocks.id')->first();
    $sko     = dispatcherSkoFor($dispatcher, $homeSko);
    OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $homeSko->stock_id)->where('id', '!=', $sko->id)->delete();

    return [$order->refresh(), $dispatcher, $sko, $homeSko, $orgPartner->refresh()];
}

function dispatcherSkoFor(\App\Models\SysAdmin\Organisation $dispatcher, OrgStock $homeSko): OrgStock
{
    $sko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $homeSko->stock_id)->orderBy('id')->first()
        ?? StoreOrgStock::make()->action($dispatcher, $homeSko->stock, []);
    $sko->updateQuietly(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 1000, 'packed_in' => $homeSko->packed_in, 'sku_commercial_value' => 2]);
    $sko->tradeUnits()->sync($homeSko->tradeUnits->mapWithKeys(fn ($tradeUnit) => [$tradeUnit->id => ['quantity' => $tradeUnit->pivot->quantity]])->all());

    return $sko->refresh();
}

function crossDispatchRefusals(\App\Models\Ordering\Order $order, \App\Models\SysAdmin\Organisation $dispatcher): array
{
    $result = ResolveCrossDispatch::run($order->fresh(), $dispatcher->fresh());
    if ($result['refusals']) {
        expect($result['mapping'])->toBe([]);
    }

    return array_values(array_unique(array_column($result['refusals'], 'reason')));
}

test('an order another company can ship whole maps every line to that company\'s SKOs, and coverage lists it only once both sides switched the direction on', function () {
    [$order, $dispatcher, $sko, $homeSko, $orgPartner] = crossDispatchOrder($this, 10);
    $transaction = $order->transactions()->where('model_type', 'Product')->first();
    $before      = [$order->fresh()->toArray(), $sko->fresh()->toArray(), $order->deliveryNotes()->count()];

    $result = ResolveCrossDispatch::run($order, $dispatcher);

    expect($result['refusals'])->toBe([])
        ->and($result['mapping'])->toBe([
            $transaction->id => [
                ['home_org_stock_id' => $homeSko->id, 'org_stock_id' => $sko->id, 'quantity' => 10.0],
            ],
        ])
        ->and(GetCrossDispatchCoverage::run($order))->toBe([$dispatcher->id => $result['mapping']]);

    foreach ([$this->organisation, $dispatcher] as $side) {
        UpdateCrossDispatchSettings::make()->action($orgPartner, $side, ['enabled' => false]);
        expect(ResolveCrossDispatch::run($order, $dispatcher))->toBe(['mapping' => [], 'refusals' => [['reason' => 'not_enabled']]])
            ->and(GetCrossDispatchCoverage::run($order))->toBe([]);
        UpdateCrossDispatchSettings::make()->action($orgPartner, $side, ['enabled' => true]);
    }
    expect(GetCrossDispatchCoverage::run($order))->toBe([$dispatcher->id => $result['mapping']]);

    $unlinked = new \App\Models\SysAdmin\Organisation();
    $unlinked->forceFill(['id' => 0, 'group_id' => $order->group_id]);
    expect(array_column(ResolveCrossDispatch::run($order, $unlinked)['refusals'], 'reason'))->toContain('not_enabled');

    $sko->updateQuietly(['quantity_available' => 9]);
    expect(GetCrossDispatchCoverage::run($order))->toBe([])
        ->and([$order->fresh()->toArray(), \Illuminate\Support\Arr::except($sko->fresh()->toArray(), 'updated_at'), $order->deliveryNotes()->count()])->toBe([$before[0], \Illuminate\Support\Arr::except(array_merge($before[1], ['quantity_available' => '9.000']), 'updated_at'), $before[2]]);
});

test('a cross dispatch is refused for the orders the first version leaves at home', function () {
    [$order, $dispatcher] = crossDispatchOrder($this);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([])
        ->and(crossDispatchRefusals($order, $this->organisation))->toContain('same_organisation');

    $this->shop->updateQuietly(['type' => ShopTypeEnum::B2B]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['not_dropshipping']);
    $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);

    $order->updateQuietly(['to_be_paid_by' => OrderToBePaidByEnum::CASH_ON_DELIVERY]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['cash_on_delivery']);
    $order->updateQuietly(['to_be_paid_by' => null]);

    $order->updateQuietly(['collection_address_id' => $order->delivery_address_id]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['collection']);
    $order->updateQuietly(['collection_address_id' => null]);

    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'XD'.Str::upper(Str::random(4)), 'name' => 'Locked', 'trade_as' => 'locked']);
    $order->updateQuietly(['shipper_id' => $shipper->id, 'is_shipper_locked' => false]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
    $order->updateQuietly(['is_shipper_locked' => true]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['locked_shipper']);
    $order->updateQuietly(['shipper_id' => null, 'is_shipper_locked' => false]);

    $tiktokOrder = $order->fresh();
    $tiktokOrder->setRelation('platform', new Platform(['type' => PlatformTypeEnum::TIKTOK]));
    $shopifyOrder = $order->fresh();
    $shopifyOrder->setRelation('platform', new Platform(['type' => PlatformTypeEnum::SHOPIFY]));
    expect(array_column(ResolveCrossDispatch::run($tiktokOrder, $dispatcher)['refusals'], 'reason'))->toBe(['tiktok'])
        ->and(ResolveCrossDispatch::run($shopifyOrder, $dispatcher)['refusals'])->toBe([]);

    $channelOrder = $order->fresh();
    $channelOrder->setRelation('customerSalesChannel', (new CustomerSalesChannel())->setRelation('platform', new Platform(['type' => PlatformTypeEnum::TIKTOK])));
    expect(array_column(ResolveCrossDispatch::run($channelOrder, $dispatcher)['refusals'], 'reason'))->toBe(['tiktok']);

    $preOrder = PreOrder::create([
        'group_id'        => $order->group_id,
        'organisation_id' => $order->organisation_id,
        'shop_id'         => $order->shop_id,
        'customer_id'     => $order->customer_id,
        'order_id'        => $order->id,
        'state'           => PreOrderStateEnum::WAITING_FOR_GOODS,
        'is_trade'        => false,
        'upfront_amount'  => 0,
        'deferred_amount' => 0,
    ]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['pre_order']);
    $preOrder->delete();

    $this->mock(FillToOrder::class)->shouldReceive('artefactFor')->andReturn(new Artefact());
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['fill_to_order']);
    app()->forgetInstance(FillToOrder::class);

    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
});

test('a cross dispatch is refused once the home warehouse has started, and for an order with a replacement note', function () {
    [$order, $dispatcher] = crossDispatchOrder($this);
    $deliveryNote         = $order->deliveryNotes()->first() ?? SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();

    expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED)
        ->and(crossDispatchRefusals($order, $dispatcher))->toBe([]);

    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::QUEUED]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);

    $deliveryNote->deliveryNoteItems()->first()->updateQuietly(['quantity_picked' => 1]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['picked']);
    $deliveryNote->deliveryNoteItems()->first()->updateQuietly(['quantity_picked' => 0]);

    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::HANDLING]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['picked']);

    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::UNASSIGNED, 'type' => DeliveryNoteTypeEnum::REPLACEMENT]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['replacement']);

    $deliveryNote->updateQuietly(['state' => DeliveryNoteStateEnum::CANCELLED]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);

    $order->updateQuietly(['state' => OrderStateEnum::HANDLING]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['order_state']);
});

test('a cross dispatch never crosses the UK and EU customs border unless the pair of companies allows it', function () {
    [$order, $dispatcher, , , $orgPartner] = crossDispatchOrder($this);
    $countryId = fn (string $code) => Country::where('code', $code)->firstOrFail()->id;
    $shipFrom  = function (string $code) use ($dispatcher, $countryId) {
        $dispatcher->updateQuietly(['country_id' => $countryId($code)]);
        $dispatcher->warehouses()->orderBy('id')->first()?->address?->updateQuietly(['country_id' => $countryId($code), 'country_code' => $code]);
    };
    $deliverTo = fn (string $code) => $order->deliveryAddress->updateQuietly(['country_id' => $countryId($code), 'country_code' => $code]);
    $originalCountryId = $dispatcher->country_id;

    try {
        UpdateCrossDispatchSettings::make()->action($orgPartner, $dispatcher, ['allow_customs_crossing' => false]);

        $shipFrom('ES');
        $deliverTo('FR');
        expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);

        $deliverTo('GB');
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['customs_crossing']);

        $deliverTo('US');
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['customs_crossing']);

        $shipFrom('GB');
        $deliverTo('GB');
        expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);

        $deliverTo('SK');
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['customs_crossing']);

        UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['allow_customs_crossing' => true]);
        expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
    } finally {
        $dispatcher->updateQuietly(['country_id' => $originalCountryId]);
    }
});

test('a cross dispatch is refused when the other company\'s SKO is missing, doubled, not live, packed differently or short of stock', function () {
    [$order, $dispatcher, $sko, $homeSko] = crossDispatchOrder($this, 10);

    $sko->updateQuietly(['quantity_available' => 10]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
    $sko->updateQuietly(['quantity_available' => 9.5]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['not_enough_stock']);
    $sko->updateQuietly(['quantity_available' => 1000]);

    $sko->updateQuietly(['state' => OrgStockStateEnum::DISCONTINUING]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
    foreach ([OrgStockStateEnum::DISCONTINUED, OrgStockStateEnum::SUSPENDED, OrgStockStateEnum::ABNORMALITY] as $state) {
        $sko->updateQuietly(['state' => $state]);
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_inactive']);
    }
    $sko->updateQuietly(['state' => OrgStockStateEnum::ACTIVE]);

    $sko->updateQuietly(['packed_in' => ((int) $homeSko->packed_in) + 6]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_content_differs']);
    $sko->updateQuietly(['packed_in' => $homeSko->packed_in]);

    $tradeUnit = $homeSko->tradeUnits->first();
    $sko->tradeUnits()->updateExistingPivot($tradeUnit->id, ['quantity' => $tradeUnit->pivot->quantity + 1]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_content_differs']);
    $sko->tradeUnits()->sync([]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_content_differs']);
    $homeContent = $homeSko->tradeUnits->mapWithKeys(fn ($unit) => [$unit->id => ['quantity' => $unit->pivot->quantity]])->all();
    $homeSko->tradeUnits()->sync([]);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_content_differs']);
    $homeSko->tradeUnits()->sync($homeContent);
    dispatcherSkoFor($dispatcher, $homeSko->refresh());

    $duplicate = StoreOrgStock::make()->action($dispatcher, $homeSko->stock, []);
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_duplicate']);
    $duplicate->delete();
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);

    $sko->delete();
    expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_missing']);
    $sko->restore();
    expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
});

test('a cross dispatch needs an SKO behind every product, a commercial value on each SKO of a bundle, and stock for the whole order', function () {
    [$order, $dispatcher, $sko, $homeSko] = crossDispatchOrder($this, 10);
    $transaction   = $order->transactions()->where('model_type', 'Product')->first();
    $secondStock   = $this->group->stocks()->where('id', '!=', $homeSko->stock_id)->orderBy('id')->first();
    $secondHomeSko = createOrgStocks($this->organisation, [$secondStock])[0];
    $secondSko     = dispatcherSkoFor($dispatcher, $secondHomeSko);
    $pivot         = DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->get()->map(fn ($row) => (array) $row)->all();

    try {
        $this->product->orgStocks()->attach($secondHomeSko->id, ['quantity' => 3]);
        $result = ResolveCrossDispatch::run($order, $dispatcher);
        expect($result['refusals'])->toBe([])
            ->and($result['mapping'][$transaction->id])->toBe([
                ['home_org_stock_id' => $homeSko->id, 'org_stock_id' => $sko->id, 'quantity' => 10.0],
                ['home_org_stock_id' => $secondHomeSko->id, 'org_stock_id' => $secondSko->id, 'quantity' => 30.0],
            ]);

        $secondSko->updateQuietly(['sku_commercial_value' => 0]);
        expect(ResolveCrossDispatch::run($order, $dispatcher)['refusals'])->toBe([
            ['reason' => 'sko_without_commercial_value', 'transaction_id' => $transaction->id, 'home_org_stock_id' => $secondHomeSko->id, 'org_stock_id' => $secondSko->id, 'stock_id' => $secondStock->id],
        ]);

        $secondHomeSko->delete();
        expect(ResolveCrossDispatch::run($order, $dispatcher))->toBe(['mapping' => [$transaction->id => [['home_org_stock_id' => $homeSko->id, 'org_stock_id' => $sko->id, 'quantity' => 10.0]]], 'refusals' => []]);
        $secondHomeSko->restore();
        $secondSko->updateQuietly(['sku_commercial_value' => 2, 'quantity_available' => 29]);
        expect(ResolveCrossDispatch::run($order, $dispatcher)['refusals'])->toBe([
            ['reason' => 'not_enough_stock', 'org_stock_id' => $secondSko->id, 'stock_id' => $secondStock->id],
        ]);

        $this->product->orgStocks()->detach($secondHomeSko->id);
        $otherProduct = StoreProduct::make()->action($this->product->family, array_merge(Product::factory()->definition(), [
            'trade_units' => [['id' => $this->product->tradeUnits->first()->id, 'quantity' => 1]],
            'price'       => 10,
        ]));
        $otherProduct->orgStocks()->sync([$homeSko->id => ['quantity' => 2]]);
        StoreTransaction::make()->action($order, $otherProduct->historicAsset, ['quantity_ordered' => 4]);
        $sko->updateQuietly(['quantity_available' => 18, 'sku_commercial_value' => 0]);
        expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
        $sko->updateQuietly(['quantity_available' => 17]);
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['not_enough_stock']);
        $sko->updateQuietly(['quantity_available' => 1000]);

        $otherProduct->orgStocks()->detach();
        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['product_without_sko']);

        $order->transactions()->where('model_id', $otherProduct->id)->first()->updateQuietly(['quantity_ordered' => 0]);
        expect(crossDispatchRefusals($order, $dispatcher))->toBe([]);
    } finally {
        DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->delete();
        DB::table('product_has_org_stocks')->insert($pivot);
    }

    $emptyOrder = StoreOrder::make()->action($this->customer, []);
    $emptyOrder->updateQuietly(['state' => OrderStateEnum::SUBMITTED]);
    expect(crossDispatchRefusals($emptyOrder, $dispatcher))->toBe(['nothing_to_dispatch']);
});

/**
 * The fields of a delivery note and its lines that the cross dispatch steps touch, in line order.
 */
function deliveryNoteFingerprint(\App\Models\Dispatching\DeliveryNote $deliveryNote): array
{
    return [
        'note'  => \Illuminate\Support\Arr::only($deliveryNote->fresh()->getAttributes(), ['group_id', 'organisation_id', 'shop_id', 'customer_id', 'warehouse_id', 'reference', 'state', 'type', 'shop_type']),
        'lines' => $deliveryNote->deliveryNoteItems()->orderBy('id')->get()->map(fn ($item) => [
            'organisation_id'            => $item->organisation_id,
            'shop_id'                    => $item->shop_id,
            'transaction_id'             => $item->transaction_id,
            'org_stock_id'               => $item->org_stock_id,
            'stock_id'                   => $item->stock_id,
            'org_stock_family_id'        => $item->org_stock_family_id,
            'quantity_required'          => (float) $item->quantity_required,
            'original_quantity_required' => (float) $item->original_quantity_required,
            'quantity_picked'            => (float) $item->quantity_picked,
            'is_dirty'                   => (bool) $item->is_dirty,
        ])->all(),
    ];
}

test('a home order sent to the warehouse gets the same delivery note and lines as before cross dispatch existed', function () {
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $this->product, 10);
    crossDispatchDirection($this->organisation);
    $homeWarehouse = $this->organisation->warehouses()->first();
    $homeSkos      = $this->product->orgStocks()->get();

    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

    $expectedLines = fn (float $quantity, bool $dirty) => $homeSkos->map(fn (OrgStock $sko) => [
        'organisation_id'            => $this->organisation->id,
        'shop_id'                    => $this->shop->id,
        'transaction_id'             => $transaction->id,
        'org_stock_id'               => $sko->id,
        'stock_id'                   => $sko->stock_id,
        'org_stock_family_id'        => $sko->org_stock_family_id,
        'quantity_required'          => (float) $sko->pivot->quantity * $quantity,
        'original_quantity_required' => (float) $sko->pivot->quantity * 10,
        'quantity_picked'            => 0.0,
        'is_dirty'                   => $dirty,
    ])->all();

    expect($homeSkos)->not->toBeEmpty()
        ->and($deliveryNote->isCrossDispatch())->toBeFalse()
        ->and(deliveryNoteFingerprint($deliveryNote))->toEqual([
            'note'  => [
                'group_id'        => $this->group->id,
                'organisation_id' => $this->organisation->id,
                'shop_id'         => $this->shop->id,
                'customer_id'     => $this->customer->id,
                'warehouse_id'    => $homeWarehouse->id,
                'reference'       => $order->reference,
                'state'           => 'unassigned',
                'type'            => 'order',
                'shop_type'       => $this->shop->type->value,
            ],
            'lines' => $expectedLines(10, false),
        ]);

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, ['quantity_ordered' => 6], $this->user);
    expect(deliveryNoteFingerprint($deliveryNote)['lines'])->toBe($expectedLines(6, true));
});

/**
 * Picks every line of the note in full from a fresh location of its own warehouse, packs, ships, finalises and dispatches it.
 */
function pickPackAndDispatch(\App\Models\Dispatching\DeliveryNote $deliveryNote, \App\Models\SysAdmin\User $user): \App\Models\Dispatching\DeliveryNote
{
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $user);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote::make()->action($deliveryNote, $user);

    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        $location         = \App\Actions\Inventory\Location\StoreLocation::make()->action($deliveryNote->warehouse, \App\Models\Inventory\Location::factory()->definition());
        $locationOrgStock = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(
            orgStock: $item->orgStock,
            location: $location,
            modelData: ['quantity' => 100, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
            strict: false
        );
        \App\Actions\Dispatching\Picking\StorePicking::make()->action($item, $user, [
            'picker_user_id'        => $user->id,
            'location_org_stock_id' => $locationOrgStock->id,
            'quantity'              => (float) $item->quantity_required,
        ]);
    }

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote->refresh());
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $user);
    $deliveryNote->update(['parcels' => [['weight' => 1, 'dimensions' => [30, 20, 10]]]]);
    foreach ($deliveryNote->deliveryNoteItems()->get() as $item) {
        \App\Actions\Dispatching\Packing\StorePacking::make()->action($item, $user, []);
    }
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $user);

    $shipper = StoreShipper::make()->action($deliveryNote->organisation, ['code' => 'XD'.Str::random(4), 'name' => 'Xd', 'trade_as' => 'xd']);
    \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'TRK'.Str::random(6)]);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote::make()->action($deliveryNote->refresh());

    return \App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote::make()->action($deliveryNote->refresh());
}

/**
 * What the customer is invoiced, without anything that names the invoice itself.
 */
function invoicedFigures(\App\Models\Ordering\Order $order): array
{
    $invoice = $order->invoices()->where('type', \App\Enums\Accounting\Invoice\InvoiceTypeEnum::INVOICE)->sole();

    return [
        'organisation_id' => $invoice->organisation_id,
        'shop_id'         => $invoice->shop_id,
        'customer_id'     => $invoice->customer_id,
        'currency_id'     => $invoice->currency_id,
        'totals'          => array_map('floatval', \Illuminate\Support\Arr::only($invoice->getAttributes(), ['goods_amount', 'net_amount', 'tax_amount', 'total_amount'])),
        'lines'           => $invoice->invoiceTransactions()->orderBy('id')->get()->map(fn ($line) => [
            'model_type'      => $line->model_type,
            'model_id'        => $line->model_id,
            'organisation_id' => $line->organisation_id,
            'shop_id'         => $line->shop_id,
            'quantity'        => (float) $line->quantity,
            'net_amount'      => (float) $line->net_amount,
        ])->all(),
    ];
}

test('a submitted order is raised, picked and dispatched in another company\'s warehouse, and the customer is invoiced exactly as when it ships from home', function () {
    [$order, $dispatcher, $sko, $homeSko] = crossDispatchOrder($this, 10);
    $transaction         = $order->transactions()->where('model_type', 'Product')->first();
    $dispatcherWarehouse = $dispatcher->warehouses()->first();
    $homeStock           = fn () => \Illuminate\Support\Arr::except($homeSko->fresh()->getAttributes(), ['updated_at']);
    $homeMovements       = fn () => \App\Models\Inventory\OrgStockMovement::where('organisation_id', $this->organisation->id)->count();
    $homeBefore          = [$homeStock(), $homeMovements(), \App\Models\Dispatching\DeliveryNote::where('organisation_id', $this->organisation->id)->count()];

    $deliveryNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher);

    expect($deliveryNote->isCrossDispatch())->toBeTrue()
        ->and($order->refresh()->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->deliveryNotes()->count())->toBe(1)
        ->and(deliveryNoteFingerprint($deliveryNote))->toEqual([
            'note'  => [
                'group_id'        => $this->group->id,
                'organisation_id' => $dispatcher->id,
                'shop_id'         => $this->shop->id,
                'customer_id'     => $this->customer->id,
                'warehouse_id'    => $dispatcherWarehouse->id,
                'reference'       => $order->reference,
                'state'           => 'unassigned',
                'type'            => 'order',
                'shop_type'       => 'dropshipping',
            ],
            'lines' => [[
                'organisation_id'            => $dispatcher->id,
                'shop_id'                    => $this->shop->id,
                'transaction_id'             => $transaction->id,
                'org_stock_id'               => $sko->id,
                'stock_id'                   => $homeSko->stock_id,
                'org_stock_family_id'        => $sko->org_stock_family_id,
                'quantity_required'          => 10.0,
                'original_quantity_required' => 10.0,
                'quantity_picked'            => 0.0,
                'is_dirty'                   => false,
            ]],
        ]);

    $toBePicked = fn (OrgStock $orgStock) => \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::make()->getQuantityToBePicked($orgStock->id);
    $homeToBePicked = $toBePicked($homeSko);
    expect($toBePicked($sko))->toBe(10.0);

    $deliveryNote = pickPackAndDispatch($deliveryNote, $this->user);
    $picking      = $deliveryNote->pickings()->sole();

    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::DISPATCHED)
        ->and($order->refresh()->state)->toBe(OrderStateEnum::DISPATCHED)
        ->and([$picking->organisation_id, $picking->org_stock_id, (float) $picking->quantity])->toBe([$dispatcher->id, $sko->id, 10.0])
        ->and($picking->location->warehouse_id)->toBe($dispatcherWarehouse->id)
        ->and((float) \App\Models\Inventory\LocationOrgStock::where('org_stock_id', $sko->id)->where('location_id', $picking->location_id)->value('quantity'))->toBe(90.0)
        ->and($deliveryNote->shipments()->sole()->organisation_id)->toBe($dispatcher->id)
        ->and(\App\Models\Inventory\OrgStockMovement::where('org_stock_id', $sko->id)->where('organisation_id', $dispatcher->id)->where('warehouse_id', $dispatcherWarehouse->id)->exists())->toBeTrue()
        ->and($toBePicked($sko))->toBe(0.0)
        ->and($toBePicked($homeSko))->toBe($homeToBePicked)
        ->and([$homeStock(), $homeMovements(), \App\Models\Dispatching\DeliveryNote::where('organisation_id', $this->organisation->id)->count()])->toEqual($homeBefore);

    [$homeOrder] = submittedOrderWithTransaction($this->customer, $this->product, 10);
    $homeNote    = pickPackAndDispatch(SendOrderToWarehouse::make()->action($homeOrder, []), $this->user);

    $cross = invoicedFigures($order->refresh());
    $home  = invoicedFigures($homeOrder->refresh());

    expect($homeNote->isCrossDispatch())->toBeFalse()
        ->and($cross)->toEqual($home)
        ->and($cross['organisation_id'])->toBe($this->organisation->id)
        ->and($cross['shop_id'])->toBe($this->shop->id)
        ->and($cross['lines'])->not->toBeEmpty()
        ->and((float) $order->transactions()->where('model_type', 'Product')->first()->quantity_dispatched)->toBe(10.0);
});

test('an order the other company cannot ship whole, or whose direction is switched off, raises nothing', function () {
    [$order, $dispatcher, $sko, , $orgPartner] = crossDispatchOrder($this, 10);
    $snapshot = fn () => [
        \Illuminate\Support\Arr::except($order->fresh()->getAttributes(), ['updated_at']),
        $order->transactions()->orderBy('id')->get()->map(fn ($line) => \Illuminate\Support\Arr::except($line->getAttributes(), ['updated_at']))->all(),
        \App\Models\Dispatching\DeliveryNote::count(),
        \App\Models\Dispatching\DeliveryNoteItem::count(),
    ];
    $before  = $snapshot();
    $refused = function (string $reason) use ($order, $dispatcher, $snapshot, $before) {
        try {
            \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher->fresh());
            $this->fail('The order was raised although it should be refused for '.$reason);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            expect($exception->errors()['cross_dispatch'][0])->toContain($reason)
                ->and(array_column(array_map(fn ($refusal) => json_decode($refusal, true), $exception->errors()['refusals']), 'reason'))->toContain($reason);
        }
        expect($snapshot())->toEqual($before);
    };

    $sko->updateQuietly(['quantity_available' => 9]);
    $refused('not_enough_stock');
    $sko->updateQuietly(['quantity_available' => 1000]);

    foreach ([$this->organisation, $dispatcher] as $side) {
        UpdateCrossDispatchSettings::make()->action($orgPartner, $side, ['enabled' => false]);
        $refused('not_enabled');
        expect(fn () => SendOrderToWarehouse::make()->action($order->fresh(), [], dispatcher: $dispatcher))->toThrow(\Illuminate\Validation\ValidationException::class);
        expect($snapshot())->toEqual($before);
        UpdateCrossDispatchSettings::make()->action($orgPartner, $side, ['enabled' => true]);
    }

    $order->updateQuietly(['delivery_address_id' => null]);
    $this->customer->updateQuietly(['delivery_address_id' => null]);
    $withoutAddress = $snapshot();
    try {
        \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher);
        $this->fail('An order held for its address was raised');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect(json_encode($exception->errors()))->toContain('order_held');
    }
    expect($snapshot())->toEqual($withoutAddress);
    $order->updateQuietly(['delivery_address_id' => $before[0]['delivery_address_id']]);
    $this->customer->updateQuietly(['delivery_address_id' => $before[0]['delivery_address_id']]);

    $homeNote = SendOrderToWarehouse::make()->action($order->fresh(), []);
    expect($homeNote->isCrossDispatch())->toBeFalse()
        ->and(fn () => \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and($order->deliveryNotes()->count())->toBe(1);
});

test('edits to an order being picked in another company\'s warehouse reach that company\'s SKOs, or are refused whole', function () {
    [$order, $dispatcher, $sko, $homeSko] = crossDispatchOrder($this, 10);
    $transaction  = $order->transactions()->where('model_type', 'Product')->first();
    $deliveryNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher);
    $lines        = fn () => $deliveryNote->deliveryNoteItems()->orderBy('id')->get()->map(fn ($item) => [$item->transaction_id, $item->org_stock_id, $item->organisation_id, (float) $item->quantity_required])->all();

    UpdateTransactionProductQuantityOrdered::make()->action($transaction, ['quantity_ordered' => 6], $this->user);
    expect($lines())->toBe([[$transaction->id, $sko->id, $dispatcher->id, 6.0]])
        ->and($deliveryNote->deliveryNoteItems()->first()->is_dirty)->toBeTrue();

    $lateProduct = productAddedLater($this->product);
    $lateProduct->orgStocks()->sync([$homeSko->id => ['quantity' => 2]]);
    SaveOrderModification::make()->action($order->refresh(), ['products' => [$lateProduct->id => ['quantity_ordered' => 3]]], $this->user);
    $lateTransaction = $order->transactions()->where('model_id', $lateProduct->id)->sole();
    expect($lines())->toBe([[$transaction->id, $sko->id, $dispatcher->id, 6.0], [$lateTransaction->id, $sko->id, $dispatcher->id, 6.0]]);

    $deliveryNote->deliveryNoteItems()->where('transaction_id', $lateTransaction->id)->forceDelete();
    expect(\App\Actions\Dispatching\DeliveryNoteItem\StoreMissingDeliveryNoteItemsOfProduct::run($lateProduct))->toBe(1)
        ->and(array_slice($lines()[1], 0, 4))->toBe([$lateTransaction->id, $sko->id, $dispatcher->id, 6.0]);

    $pivot = DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->where('org_stock_id', $homeSko->id);
    $skosPerProduct = $pivot->value('quantity');
    try {
        (clone $pivot)->update(['quantity' => 3]);
        \App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity::run($homeSko->fresh());
        expect($lines()[0])->toBe([$transaction->id, $sko->id, $dispatcher->id, 18.0]);

        (clone $pivot)->update(['quantity' => 2]);
        \App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity::run($sko->fresh());
        expect($lines()[0])->toBe([$transaction->id, $sko->id, $dispatcher->id, 12.0]);
    } finally {
        (clone $pivot)->update(['quantity' => $skosPerProduct]);
    }
    \App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity::run($homeSko->fresh());
    expect($lines()[0])->toBe([$transaction->id, $sko->id, $dispatcher->id, 6.0]);

    $firstLine = $deliveryNote->deliveryNoteItems()->orderBy('id')->first();
    $firstLine->updateQuietly(['quantity_waiting_crm' => 2, 'state' => DeliveryNoteItemStateEnum::HANDLING_BLOCKED]);
    $swapProduct = productAddedLater($this->product);
    $swapProduct->orgStocks()->sync([$homeSko->id => ['quantity' => 1]]);
    $this->post(route('grp.models.delivery_note_item.waiting_items_replace_product', [$firstLine->id]), ['quantity' => 1, 'products' => [['id' => $swapProduct->id, 'quantity' => 1]]])->assertSessionHasNoErrors();
    $swapLine = $deliveryNote->deliveryNoteItems()->where('transaction_id', $order->transactions()->where('model_id', $swapProduct->id)->sole()->id)->sole();
    expect([$swapLine->org_stock_id, $swapLine->organisation_id, (float) $swapLine->quantity_required])->toBe([$sko->id, $dispatcher->id, 1.0])
        ->and((float) $firstLine->fresh()->quantity_waiting_crm)->toBe(1.0);

    $sko->updateQuietly(['packed_in' => ((int) $homeSko->packed_in) + 6]);
    $before = [$lines(), $order->transactions()->count(), (float) $transaction->fresh()->quantity_ordered];
    $unmapped = productAddedLater($this->product);
    $unmapped->orgStocks()->sync([$homeSko->id => ['quantity' => 1]]);

    expect(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction->fresh(), ['quantity_ordered' => 4], $this->user))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_content_differs')
        ->and(fn () => SaveOrderModification::make()->action($order->refresh(), ['products' => [$unmapped->id => ['quantity_ordered' => 1]]], $this->user))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_content_differs')
        ->and([$lines(), $order->transactions()->count(), (float) $transaction->fresh()->quantity_ordered])->toBe($before);
    $this->post(route('grp.models.delivery_note_item.waiting_items_replace_product', [$firstLine->id]), ['quantity' => 1, 'products' => [['id' => $unmapped->id, 'quantity' => 1]]])->assertSessionHasErrors('cross_dispatch');
    expect([$lines(), $order->transactions()->count(), (float) $firstLine->fresh()->quantity_waiting_crm])->toBe([$before[0], $before[1], 1.0]);

    $sko->updateQuietly(['packed_in' => $homeSko->packed_in]);
    $sko->delete();
    expect(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction->fresh(), ['quantity_ordered' => 4], $this->user))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_missing')
        ->and([$lines(), $order->transactions()->count(), (float) $transaction->fresh()->quantity_ordered])->toBe($before);
    $sko->restore();
});

test('the dispatching company works a cross dispatched note with its own trolleys, staff, settings and rights, and the ordering company\'s do not apply', function () {
    [$order, $dispatcher, $sko] = crossDispatchOrder($this, 10);
    $deliveryNote        = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher);
    $item                = $deliveryNote->deliveryNoteItems()->sole();
    $dispatcherWarehouse = $dispatcher->warehouses()->first();
    $homeSettings        = $this->organisation->settings;
    $theirSettings       = $dispatcher->settings;

    $homeAdmin  = userWithOnlyPermissions($this->organisation, ["org-admin.{$this->organisation->id}"]);
    $shopStaff  = userWithOnlyPermissions($this->organisation, ["orders.{$this->shop->id}.edit"]);
    $theirAdmin = userWithOnlyPermissions($dispatcher, ["org-admin.$dispatcher->id"]);

    expect($deliveryNote->canBeWorkedOnBy($theirAdmin))->toBeTrue()
        ->and($deliveryNote->canBeViewedBy($theirAdmin))->toBeTrue()
        ->and($deliveryNote->canBeWorkedOnBy($homeAdmin))->toBeFalse()
        ->and($deliveryNote->canBeViewedBy($homeAdmin))->toBeFalse()
        ->and($deliveryNote->canBeWorkedOnBy($shopStaff))->toBeFalse()
        ->and($deliveryNote->canBeViewedBy($shopStaff))->toBeTrue()
        ->and($deliveryNote->canBeShippedBy($shopStaff))->toBeTrue()
        ->and($deliveryNote->canBeCancelledBy($shopStaff))->toBeTrue();

    $homeTrolley  = \App\Actions\Dispatching\Trolley\StoreTrolley::make()->handle($this->warehouse, ['name' => 'home_'.Str::random(5)]);
    $theirTrolley = \App\Actions\Dispatching\Trolley\StoreTrolley::make()->handle($dispatcherWarehouse, ['name' => 'their_'.Str::random(5)]);

    try {
        $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $theirAdmin);
        $this->actingAs($theirAdmin);

        $this->patch(route('grp.models.delivery_note.state.handling_with_trolley', [$deliveryNote->id]), ['trolley' => $homeTrolley->id])->assertSessionHasErrors('trolley');
        expect($deliveryNote->fresh()->state)->toBe(DeliveryNoteStateEnum::QUEUED);

        $this->patch(route('grp.models.delivery_note.state.handling_with_trolley', [$deliveryNote->id]), ['trolley' => $theirTrolley->id])->assertSessionHasNoErrors();
        expect($deliveryNote->fresh()->state)->toBe(DeliveryNoteStateEnum::HANDLING)
            ->and(DB::table('delivery_note_has_trolleys')->where('delivery_note_id', $deliveryNote->id)->get(['organisation_id', 'trolley_id'])->map(fn ($row) => (array) $row)->all())->toBe([['organisation_id' => $dispatcher->id, 'trolley_id' => $theirTrolley->id]]);

        $this->patch(route('grp.models.delivery_note.state.change_trolley', [$deliveryNote->id]), ['trolley' => $homeTrolley->id])->assertSessionHasErrors('trolley');

        $homeBay  = \App\Actions\Dispatching\PickedBay\StorePickedBay::make()->handle($this->warehouse, ['code' => 'hb'.Str::random(5)]);
        $theirBay = \App\Actions\Dispatching\PickedBay\StorePickedBay::make()->handle($dispatcherWarehouse, ['code' => 'tb'.Str::random(5)]);
        $this->patch(route('grp.models.delivery_note.state.change_picked_bay', [$deliveryNote->id]), ['picked_bay' => $homeBay->id])->assertSessionHasErrors('picked_bay');
        $this->patch(route('grp.models.delivery_note.state.set_as_picked_with_picked_bay', [$deliveryNote->id]), ['picked_bay' => $homeBay->id])->assertSessionHasErrors('picked_bay');
        expect($deliveryNote->fresh()->state)->toBe(DeliveryNoteStateEnum::HANDLING);
        $this->patch(route('grp.models.delivery_note.state.change_picked_bay', [$deliveryNote->id]), ['picked_bay' => $theirBay->id])->assertSessionHasNoErrors();
        expect(DB::table('picked_bay_has_delivery_notes')->where('delivery_note_id', $deliveryNote->id)->pluck('picked_bay_id')->all())->toBe([$theirBay->id]);

        $homeEmployee  = \App\Actions\HumanResources\Employee\StoreEmployee::make()->action($this->organisation, \App\Models\HumanResources\Employee::factory()->definition());
        $theirEmployee = \App\Actions\HumanResources\Employee\StoreEmployee::make()->action($dispatcher, \App\Models\HumanResources\Employee::factory()->definition());
        expect(fn () => \App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($deliveryNote->fresh(), ['picker_id' => $homeEmployee->id]))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(\App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($deliveryNote->fresh(), ['picker_id' => $theirEmployee->id])->picker_id)->toBe($theirEmployee->id)
            ->and(fn () => \App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($deliveryNote->fresh(), ['packer_id' => $homeEmployee->id]))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(\App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($deliveryNote->fresh(), ['packer_id' => $theirEmployee->id])->packer_id)->toBe($theirEmployee->id);

        $this->organisation->update(['settings' => array_replace_recursive($homeSettings ?? [], ['orders' => ['allow_waiting' => true]])]);
        $dispatcher->update(['settings' => array_replace_recursive($theirSettings ?? [], ['orders' => ['allow_waiting' => false]])]);
        $this->post(route('grp.models.delivery_note_item.set_as_waiting_warehouse', [$item->id]), ['quantity' => 2])->assertForbidden();
        expect((float) $item->fresh()->quantity_waiting_warehouse)->toBe(0.0);

        $this->organisation->update(['settings' => array_replace_recursive($homeSettings ?? [], ['orders' => ['allow_waiting' => false]])]);
        $dispatcher->update(['settings' => array_replace_recursive($theirSettings ?? [], ['orders' => ['allow_waiting' => true]])]);
        $this->post(route('grp.models.delivery_note_item.set_as_waiting_warehouse', [$item->id]), ['quantity' => 2])->assertSessionHasNoErrors();
        expect((float) $item->fresh()->quantity_waiting_warehouse)->toBe(2.0);

        $this->actingAs($this->user);
        $this->get(route('grp.org.shops.show.ordering.orders.show.delivery-note', [$this->organisation->slug, $this->shop->slug, $order->slug, $deliveryNote->slug]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('allow_waiting', true));
    } finally {
        $this->organisation->update(['settings' => $homeSettings]);
        $dispatcher->update(['settings' => $theirSettings]);
        $this->actingAs($this->user);
    }
});

/**
 * Production's return_stats has number_items_state_waiting_to_receive, which StoreReturnItem writes; a database built from
 * the migrations gets number_items_state_pending instead, so an order return cannot be stored in the test database as it is.
 */
function giveReturnStatsTheColumnProductionHas(): void
{
    if (!\Illuminate\Support\Facades\Schema::hasColumn('return_stats', 'number_items_state_waiting_to_receive')) {
        \Illuminate\Support\Facades\Schema::table('return_stats', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->unsignedSmallInteger('number_items_state_waiting_to_receive')->default(0));
    }
}

test('a note picked in another company\'s warehouse cannot be deleted, and its returns and replacements belong to that company', function () {
    giveReturnStatsTheColumnProductionHas();
    [$order, $dispatcher, $sko, , $orgPartner] = crossDispatchOrder($this, 10);
    $dispatcherWarehouse = $dispatcher->warehouses()->first();
    $deliveryNote        = pickPackAndDispatch(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher), $this->user);
    $item                = $deliveryNote->deliveryNoteItems()->sole();

    expect(fn () => \App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote::make()->action($deliveryNote, []))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and($deliveryNote->fresh()->trashed())->toBeFalse()
        ->and($deliveryNote->pickings()->count())->toBe(1);

    UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => false]);
    expect(fn () => \App\Actions\Dispatching\DeliveryNote\StoreReplacementDeliveryNote::make()->action($order->refresh(), [
        'delivery_note_items' => [['id' => $item->id, 'quantity' => 1, 'reason' => 'damaged_in_transit']],
    ]))->toThrow(\Illuminate\Validation\ValidationException::class, 'not_enabled')
        ->and($order->deliveryNotes()->count())->toBe(1);

    $returnNote = \App\Actions\GoodsIn\ReturnDeliveryNote\ProcessReturnDeliveryNote::make()->action($deliveryNote->fresh());
    expect([$returnNote->organisation_id, $returnNote->warehouse_id, $returnNote->shop_id])->toBe([$dispatcher->id, $dispatcherWarehouse->id, $this->shop->id]);

    $orderReturn = \App\Actions\GoodsIn\Return\StoreReturn::make()->action($order->refresh(), []);
    expect([$orderReturn->organisation_id, $orderReturn->warehouse_id, $orderReturn->shop_id])->toBe([$dispatcher->id, $dispatcherWarehouse->id, $this->shop->id])
        ->and(DB::table('return_items')->where('return_id', $orderReturn->id)->get(['organisation_id', 'org_stock_id'])->map(fn ($row) => (array) $row)->all())->toBe([['organisation_id' => $dispatcher->id, 'org_stock_id' => $sko->id]]);

    UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => true]);
    $replacement = \App\Actions\Dispatching\DeliveryNote\StoreReplacementDeliveryNote::make()->action($order->refresh(), [
        'delivery_note_items' => [['id' => $item->id, 'quantity' => 1, 'reason' => 'damaged_in_transit']],
    ]);
    $replacementLine = $replacement->deliveryNoteItems()->sole();
    expect($replacement->isCrossDispatch())->toBeTrue()
        ->and([$replacement->organisation_id, $replacement->warehouse_id, $replacement->shop_id])->toBe([$dispatcher->id, $dispatcherWarehouse->id, $this->shop->id])
        ->and([$replacementLine->organisation_id, $replacementLine->org_stock_id, (float) $replacementLine->quantity_required])->toBe([$dispatcher->id, $sko->id, 1.0]);

    [$homeOrder] = submittedOrderWithTransaction($this->customer, $this->product, 10);
    $homeNote    = pickPackAndDispatch(SendOrderToWarehouse::make()->action($homeOrder, []), $this->user);
    $homeReturn  = \App\Actions\GoodsIn\Return\StoreReturn::make()->action($homeOrder->refresh(), []);
    $homeReplacement = \App\Actions\Dispatching\DeliveryNote\StoreReplacementDeliveryNote::make()->action($homeOrder->refresh(), [
        'delivery_note_items' => [['id' => $homeNote->deliveryNoteItems()->first()->id, 'quantity' => 1, 'reason' => 'damaged_in_transit']],
    ]);
    expect([$homeReturn->organisation_id, $homeReturn->warehouse_id])->toBe([$this->organisation->id, $this->organisation->warehouses()->first()->id])
        ->and([$homeReplacement->organisation_id, $homeReplacement->warehouse_id, $homeReplacement->isCrossDispatch()])->toBe([$this->organisation->id, $this->organisation->warehouses()->first()->id, false]);

    \App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote::make()->action($homeNote->fresh(), []);
    expect($homeNote->fresh()->trashed())->toBeTrue();
});

test('a note is told apart as cross dispatched however it was loaded, and a home note never reads as one', function () {
    [$order, $dispatcher] = crossDispatchOrder($this, 10);
    $crossNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher);
    [$homeOrder] = submittedOrderWithTransaction($this->customer, $this->product, 10);
    $homeNote  = SendOrderToWarehouse::make()->action($homeOrder, []);

    foreach ([[$crossNote, true, $dispatcher->id], [$homeNote, false, $this->organisation->id]] as [$note, $isCross, $organisationId]) {
        $withStringIds = \App\Models\Dispatching\DeliveryNote::find($note->id);
        $withStringIds->setRawAttributes(array_map(fn ($value) => is_int($value) ? (string) $value : $value, $withStringIds->getAttributes()), true);
        $item = $note->deliveryNoteItems()->first();

        foreach ([
            \App\Models\Dispatching\DeliveryNote::find($note->id),
            \App\Models\Dispatching\DeliveryNote::select(['id', 'shop_id'])->find($note->id),
            \App\Models\Dispatching\DeliveryNote::select(['id', 'organisation_id'])->find($note->id),
            \App\Models\Dispatching\DeliveryNote::select(['id'])->find($note->id),
            $withStringIds,
        ] as $loaded) {
            expect($loaded->isCrossDispatch())->toBe($isCross)
                ->and($loaded->crossDispatchOrganisationId())->toBe($organisationId);
        }

        expect($item->isCrossDispatch())->toBe($isCross)
            ->and(\App\Models\Dispatching\DeliveryNoteItem::select(['id', 'delivery_note_id'])->find($item->id)->isCrossDispatch())->toBe($isCross)
            ->and(\App\Models\Dispatching\DeliveryNoteItem::select(['id'])->find($item->id)->isCrossDispatch())->toBe($isCross);

        $partial = \App\Models\Dispatching\DeliveryNote::select(['id', 'shop_id', 'state', 'reference'])->find($note->id);
        \App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote::make()->action($partial, ['phone' => '+44 1234 '.$note->id]);
        expect($note->fresh()->phone)->toBe('+44 1234 '.$note->id)
            ->and($partial->relationLoaded('organisation'))->toBeFalse();

        $freshItem = \App\Models\Dispatching\DeliveryNoteItem::find($item->id);
        $queries   = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $freshItem->shop->organisation->group;
        $queries = 0;
        (new class () extends \App\Actions\OrgAction {
            public function rules(): array
            {
                return [];
            }
        })->initialisationFromDeliveryNoteItem($freshItem, []);
        expect($freshItem->relationLoaded('deliveryNote'))->toBeFalse()
            ->and($queries)->toBe($isCross ? $queries : 0);
    }
});

test('a product listing two SKOs of the same stock is refused for cross dispatch instead of losing a line, and a taken reference is a refusal', function () {
    [$order, $dispatcher, $sko, $homeSko] = crossDispatchOrder($this, 10);
    $transaction = $order->transactions()->where('model_type', 'Product')->first();
    $twin        = StoreOrgStock::make()->action($this->organisation, $homeSko->stock, []);
    $twin->updateQuietly(['packed_in' => $homeSko->packed_in]);
    $twin->tradeUnits()->sync($homeSko->tradeUnits->mapWithKeys(fn ($tradeUnit) => [$tradeUnit->id => ['quantity' => $tradeUnit->pivot->quantity]])->all());
    $pivot = DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->get()->map(fn ($row) => (array) $row)->all();

    try {
        $this->product->orgStocks()->attach($twin->id, ['quantity' => 2]);

        expect(crossDispatchRefusals($order, $dispatcher))->toBe(['sko_duplicate'])
            ->and(fn () => \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_duplicate')
            ->and($order->deliveryNotes()->count())->toBe(0);

        $this->product->orgStocks()->detach($twin->id);
        $deliveryNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher);
        $this->product->orgStocks()->attach($twin->id, ['quantity' => 2]);

        expect(fn () => ResolveCrossDispatch::make()->orgStocksForDeliveryNote($deliveryNote, $this->product->fresh()->orgStocks))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_duplicate')
            ->and(fn () => UpdateTransactionProductQuantityOrdered::make()->action($transaction->fresh(), ['quantity_ordered' => 4], $this->user))->toThrow(\Illuminate\Validation\ValidationException::class, 'sko_duplicate')
            ->and((float) $transaction->fresh()->quantity_ordered)->toBe(10.0)
            ->and($deliveryNote->deliveryNoteItems()->count())->toBe(1);
    } finally {
        DB::table('product_has_org_stocks')->where('product_id', $this->product->id)->delete();
        DB::table('product_has_org_stocks')->insert($pivot);
        $twin->delete();
    }

    [$secondOrder] = submittedOrderWithTransaction($this->customer, $this->product, 2);
    $secondOrder->updateQuietly(['reference' => strtoupper($order->reference)]);
    expect(fn () => \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($secondOrder->fresh(), $dispatcher))->toThrow(\Illuminate\Validation\ValidationException::class, 'reference_taken')
        ->and($secondOrder->deliveryNotes()->count())->toBe(0)
        ->and($secondOrder->fresh()->state)->toBe(OrderStateEnum::SUBMITTED);
});

function crossAndHomeNotes($test): array
{
    [$order, $dispatcher, , , $orgPartner] = crossDispatchOrder($test, 10);
    $crossNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order, $dispatcher);

    $warehouseAddress                 = $test->shop->address->replicate();
    $warehouseAddress->address_line_1 = 'Dispatcher Dock 7';
    $warehouseAddress->save();
    $crossNote->warehouse->updateQuietly(['address_id' => $warehouseAddress->id]);

    [$homeOrder] = submittedOrderWithTransaction($test->customer, $test->product, 10);
    $homeNote    = SendOrderToWarehouse::make()->action($homeOrder, []);

    return [$crossNote->refresh(), $homeNote->refresh(), $dispatcher, $order->refresh(), $homeOrder->refresh(), $orgPartner];
}

function shipperOf(\App\Models\SysAdmin\Organisation $organisation, string $tradeAs = 'carrier'): \App\Models\Dispatching\Shipper
{
    return StoreShipper::make()->action($organisation, ['code' => 'XS'.Str::upper(Str::random(6)), 'name' => 'Carrier '.Str::random(5), 'trade_as' => $tradeAs]);
}

function shipmentRefusals(\App\Models\Dispatching\DeliveryNote $deliveryNote, \App\Models\Dispatching\Shipper $shipper): array
{
    $deliveryNote = $deliveryNote->fresh();
    $reasons      = $deliveryNote->isCrossDispatch()
        ? array_map(fn ($reason) => $reason->value, ResolveCrossDispatch::make()->shipmentRefusals($deliveryNote, $shipper))
        : [];

    try {
        \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'TRK'.Str::random(6)]);
        $stored = true;
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $stored = false;
        expect(array_keys($exception->errors()))->toBe(['shipper'])
            ->and($exception->errors()['shipper'])->toHaveCount(1);
    }

    expect($stored)->toBe($reasons === []);

    return $reasons;
}

test('on a note worked in another company\'s warehouse the shipper lists and every way of creating a shipment take that company\'s shippers, and a home note keeps its own', function () {
    [$crossNote, $homeNote, $dispatcher] = crossAndHomeNotes($this);
    $theirs   = shipperOf($dispatcher);
    $ours     = shipperOf($this->organisation);
    $inactive = shipperOf($dispatcher);
    $inactive->update(['status' => false]);

    $mobileList  = fn ($note) => array_column((new \App\Actions\Api\Group\GoodsOut\IndexMobileShippers())->handle($note)['data'], 'id');
    $companiesOf = fn (array $shipperIds) => \App\Models\Dispatching\Shipper::whereIn('id', $shipperIds)->pluck('organisation_id')->unique()->values()->all();
    $webList     = fn ($note) => \App\Actions\Dispatching\DeliveryNote\Json\GetMiniDeliveryNoteShipments::make()->jsonResponse($note)['shipment']['shipments_routes']['fetch_route']['parameters']['organisation'];
    $mobilePick  = new class () {
        use \App\Actions\Api\Group\GoodsOut\WithMobileShipments;

        public function pick(\App\Models\Dispatching\DeliveryNote $deliveryNote, int $shipperId): \App\Models\Dispatching\Shipper
        {
            return $this->storableShipper($deliveryNote, $shipperId, false);
        }
    };

    expect($mobileList($crossNote))->toContain($theirs->id)->not->toContain($ours->id)->not->toContain($inactive->id)
        ->and($companiesOf($mobileList($crossNote)))->toBe([$dispatcher->id])
        ->and($mobileList($homeNote))->toContain($ours->id)->not->toContain($theirs->id)
        ->and($companiesOf($mobileList($homeNote)))->toBe([$this->organisation->id])
        ->and($webList($crossNote))->toBe($dispatcher->slug)
        ->and($webList($homeNote))->toBe($this->organisation->slug)
        ->and($mobilePick->pick($crossNote, $theirs->id)->id)->toBe($theirs->id)
        ->and($mobilePick->pick($homeNote, $ours->id)->id)->toBe($ours->id)
        ->and(fn () => $mobilePick->pick($crossNote, $ours->id))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => $mobilePick->pick($crossNote, $inactive->id))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => $mobilePick->pick($homeNote, $theirs->id))->toThrow(\Illuminate\Validation\ValidationException::class);

    $this->actingAs(userWithOnlyPermissions($dispatcher, ["org-admin.$dispatcher->id"]));
    $this->post(route('grp.models.delivery_note.shipment.store', [$crossNote->id]), ['shipper_id' => $ours->id, 'tracking' => 'WEB1'])->assertSessionHasErrors('shipper_id');
    expect($crossNote->shipments()->count())->toBe(0);
    $this->post(route('grp.models.delivery_note.shipment.store', [$crossNote->id]), ['shipper_id' => $theirs->id, 'tracking' => 'WEB2', 'cost' => 7.5])->assertSessionHasNoErrors();
    $shipment = $crossNote->shipments()->sole();
    expect([$shipment->shipper_id, $shipment->organisation_id, $shipment->shop_id, $shipment->tracking, (float) $shipment->cost])
        ->toBe([$theirs->id, $dispatcher->id, $this->shop->id, 'WEB2', 7.5]);

    $this->actingAs($this->user);
    $this->post(route('grp.models.delivery_note.shipment.store', [$homeNote->id]), ['shipper_id' => $theirs->id, 'tracking' => 'WEB3'])->assertSessionHasErrors('shipper_id');
    $this->post(route('grp.models.delivery_note.shipment.store', [$homeNote->id]), ['shipper_id' => $ours->id, 'tracking' => 'WEB4'])->assertSessionHasNoErrors();
    $homeShipment = $homeNote->shipments()->sole();
    expect([$homeShipment->shipper_id, $homeShipment->organisation_id, $homeShipment->number_parcels])->toBe([$ours->id, $this->organisation->id, null])
        ->and($homeShipment->data)->not->toHaveKey('cross_dispatch');

    expect(shipmentRefusals($crossNote, $ours))->toBe(['shipper_of_another_company'])
        ->and(fn () => \App\Actions\Dispatching\DeliveryNote\SaveDeliveryNoteShippingFieldsAndRetryStoreShipping::make()->handle($crossNote->fresh(), $ours, []))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Choose one of its shippers')
        ->and($crossNote->shipments()->count())->toBe(1);
});

test('the label of a note worked in another company\'s warehouse is asked from that company\'s carrier account with its warehouse as sender, and the shipment records what the charge between companies needs', function () {
    [$crossNote, $homeNote, $dispatcher] = crossAndHomeNotes($this);
    $theirs = shipperOf($dispatcher, 'GLS');
    $theirs->update(['api_shipper' => 'gls-sk']);
    $ours = shipperOf($this->organisation, 'GLS');
    $ours->update(['api_shipper' => 'gls-sk']);

    \App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSkShipping::mock()
        ->shouldReceive('handle')
        ->once()
        ->withArgs(fn ($parent, $shipper) => $parent->is($crossNote) && $shipper->is($theirs))
        ->andReturn(['status' => 'success', 'modelData' => ['tracking' => 'GLS1 GLS2', 'trackings' => ['GLS1', 'GLS2'], 'tracking_urls' => [], 'number_parcels' => 2]]);

    expect(shipmentRefusals($crossNote, $ours))->toBe(['shipper_of_another_company']);

    $shipment = \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($crossNote->fresh(), $theirs, []);
    expect([$shipment->shipper_id, $shipment->organisation_id, $shipment->trade_as, $shipment->tracking, $shipment->number_parcels])
        ->toBe([$theirs->id, $dispatcher->id, 'GLS', 'GLS1 GLS2', 2])
        ->and($shipment->data['cross_dispatch'])->toEqual(['cost_currency' => $dispatcher->currency->code, 'cost_source' => 'missing']);
    app()->forgetInstance(\App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSkShipping::class);

    $crossNote->updateQuietly(['parcels' => [['weight' => 1], ['weight' => 2], ['weight' => 3]]]);
    $typed = \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($crossNote->fresh(), shipperOf($dispatcher), ['tracking' => 'MAN1', 'cost' => 12.5]);
    expect([$typed->number_parcels, (float) $typed->cost])->toBe([3, 12.5])
        ->and($typed->data['cross_dispatch'])->toEqual(['cost_currency' => $dispatcher->currency->code, 'cost_source' => 'typed']);

    $homeNote->updateQuietly(['parcels' => [['weight' => 1], ['weight' => 2]]]);
    $homeShipment = \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($homeNote->fresh(), shipperOf($this->organisation), ['tracking' => 'MAN2', 'cost' => 3]);
    expect($homeShipment->number_parcels)->toBeNull()
        ->and($homeShipment->data)->not->toHaveKey('cross_dispatch');

    $sender     = \App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData::run($crossNote->fresh());
    $homeSender = \App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData::run($homeNote->fresh());
    $merchant   = ['from_first_name', 'from_last_name', 'from_company_name', 'from_contact_name', 'from_phone', 'from_email'];

    $partlyLoaded = fn ($note) => \App\Models\Dispatching\DeliveryNote::with('shop:id')->find($note->id);
    $partLine     = fn ($note) => \App\Models\Dispatching\DeliveryNoteItem::with('shop:id')->where('delivery_note_id', $note->id)->firstOrFail();
    expect($partlyLoaded($crossNote)->isCrossDispatch())->toBeTrue()
        ->and($partlyLoaded($homeNote)->isCrossDispatch())->toBeFalse()
        ->and($partLine($crossNote)->isCrossDispatch())->toBeTrue()
        ->and($partLine($homeNote)->isCrossDispatch())->toBeFalse()
        ->and(\App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData::run($partlyLoaded($crossNote))['from_address']['id'])->toBe($crossNote->warehouse->address_id);

    expect($sender['from_address']['id'])->toBe($crossNote->warehouse->address_id)
        ->and($sender['from_address']['address_line_1'])->toBe('Dispatcher Dock 7')
        ->and($homeSender['from_address']['id'])->toBe($this->shop->address_id)
        ->and($homeSender['from_address']['address_line_1'])->toBe((string) $this->shop->address->address_line_1)
        ->and(\Illuminate\Support\Arr::only($sender, $merchant))->toBe(\Illuminate\Support\Arr::only($homeSender, $merchant));

    $shopWithoutAddress = $homeNote->fresh();
    $shopWithoutAddress->setRelation('shop', $this->shop->fresh()->setRelation('address', null));
    expect(fn () => \App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData::run($shopWithoutAddress))->toThrow(\ErrorException::class);

    $labelRequest = new ReflectionMethod(\App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSkShipping::class, 'prepareParcelList');
    $pickup       = fn (array $data) => $labelRequest->invoke(new \App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSkShipping(), array_merge($data, ['reference' => 'DN-1', 'shipping_notes' => '']), 1234, null)[0]->PickupAddress;

    expect($pickup($sender)->Street)->toStartWith('Dispatcher Dock 7')
        ->and($pickup($sender)->Name)->toBe($pickup($homeSender)->Name)
        ->and($pickup($homeSender)->Street)->toStartWith((string) $this->shop->address->address_line_1);
});

test('a shipper the customer locked stops the label of a note worked in another company\'s warehouse, and the ordering company\'s own shipper choices give way to the dispatching company\'s rules', function () {
    [$crossNote, $homeNote, $dispatcher, $order, $homeOrder] = crossAndHomeNotes($this);
    $ours      = shipperOf($this->organisation);
    $ourRule   = shipperOf($this->organisation);
    $theirs    = shipperOf($dispatcher);
    $theirRule = shipperOf($dispatcher);
    $scope     = \App\Actions\Dispatching\Shipment\StoreShipment::make()->tradeScopeForShopType($this->shop->type);
    $directive = fn ($note) => \Illuminate\Support\Arr::only(\App\Actions\Dispatching\Shipment\StoreShipment::make()->getShipperDirective($note->fresh()), ['locked_shipper_id', 'preferred_shipper_id', 'locked_by']);
    $lockedOut = fn ($note) => collect((new \App\Actions\Api\Group\GoodsOut\IndexMobileShippers())->handle($note->fresh())['data'])->where('is_locked_out', true)->pluck('id')->all();

    $order->updateQuietly(['shipper_id' => $ours->id, 'is_shipper_locked' => true]);
    $homeOrder->updateQuietly(['shipper_id' => $ours->id, 'is_shipper_locked' => true]);

    $rules = [
        \App\Models\Catalogue\PreferredShipping::create(['group_id' => $this->group->id, 'organisation_id' => $this->organisation->id, 'shop_id' => $this->shop->id, 'shipper_id' => $ourRule->id, 'trade_scope' => $scope, 'important' => true]),
        \App\Models\Catalogue\PreferredShipping::create(['group_id' => $this->group->id, 'organisation_id' => $dispatcher->id, 'shipper_id' => $theirRule->id, 'trade_scope' => $scope, 'important' => true]),
    ];

    try {
        $customerLock = ['locked_shipper_id' => $ours->id, 'preferred_shipper_id' => $ours->id, 'locked_by' => 'customer'];
        expect($directive($homeNote))->toBe($customerLock)
            ->and($directive($crossNote))->toBe($customerLock)
            ->and($lockedOut($crossNote))->toContain($theirs->id, $theirRule->id)
            ->and(shipmentRefusals($crossNote, $theirs))->toBe(['locked_shipper'])
            ->and(shipmentRefusals($crossNote, $ours))->toBe(['shipper_of_another_company', 'locked_shipper'])
            ->and(fn () => \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($crossNote->fresh(), $theirs, ['tracking' => 'LOCK0']))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'The customer chose a shipper for this order, and it cannot be used')
            ->and(fn () => \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($homeNote->fresh(), shipperOf($this->organisation), ['tracking' => 'LOCK1']))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'The customer chose a shipper for this order. Contact customer services')
            ->and($crossNote->shipments()->count() + $homeNote->shipments()->count())->toBe(0);

        $order->updateQuietly(['is_shipper_locked' => false]);
        $homeOrder->updateQuietly(['is_shipper_locked' => false]);
        expect($directive($homeNote))->toBe(['locked_shipper_id' => null, 'preferred_shipper_id' => $ours->id, 'locked_by' => null])
            ->and($directive($crossNote))->toBe(['locked_shipper_id' => $theirRule->id, 'preferred_shipper_id' => $theirRule->id, 'locked_by' => 'preferred_shipping']);

        $homeOrder->updateQuietly(['shipper_id' => null]);
        expect($directive($homeNote))->toBe(['locked_shipper_id' => $ourRule->id, 'preferred_shipper_id' => $ourRule->id, 'locked_by' => 'preferred_shipping']);

        $rules[1]->delete();
        expect($directive($crossNote))->toBe(['locked_shipper_id' => null, 'preferred_shipper_id' => null, 'locked_by' => null])
            ->and($lockedOut($crossNote))->toBe([])
            ->and(shipmentRefusals($crossNote, $theirs))->toBe([])
            ->and($crossNote->shipments()->sole()->shipper_id)->toBe($theirs->id);
    } finally {
        collect($rules)->each->delete();
    }
});

test('a label is refused on a note worked in another company\'s warehouse when the parcel would cross customs, or the order turned out to be TikTok, cash on delivery or for collection, and never for a home note', function () {
    [$crossNote, $homeNote, $dispatcher, $order, $homeOrder, $orgPartner] = crossAndHomeNotes($this);
    $theirs    = shipperOf($dispatcher);
    $countryId = fn (string $code) => Country::where('code', $code)->firstOrFail()->id;
    $shipFrom  = fn (string $code) => $crossNote->warehouse->address->updateQuietly(['country_id' => $countryId($code), 'country_code' => $code]);
    $deliverTo = fn (string $code) => $crossNote->deliveryAddress->updateQuietly(['country_id' => $countryId($code), 'country_code' => $code]);
    $refused   = fn () => array_map(fn ($reason) => $reason->value, ResolveCrossDispatch::make()->shipmentRefusals($crossNote->fresh(), $theirs));

    UpdateCrossDispatchSettings::make()->action($orgPartner, $dispatcher, ['allow_customs_crossing' => false]);
    $shipFrom('ES');
    $deliverTo('FR');
    expect($refused())->toBe([]);

    $deliverTo('GB');
    expect($refused())->toBe(['customs_crossing'])
        ->and(shipmentRefusals($crossNote, $theirs))->toBe(['customs_crossing'])
        ->and(fn () => \App\Actions\Dispatching\DeliveryNote\SaveDeliveryNoteShippingFieldsAndRetryStoreShipping::make()->handle($crossNote->fresh(), $theirs, []))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'customs border');
    $deliverTo('US');
    expect($refused())->toBe(['customs_crossing']);

    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $this->organisation, ['allow_customs_crossing' => true]);
    expect($refused())->toBe([]);
    $deliverTo('ES');

    $crossNote->updateQuietly(['is_cash_on_delivery' => true]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['cash_on_delivery']);
    $crossNote->updateQuietly(['is_cash_on_delivery' => false]);
    $order->updateQuietly(['to_be_paid_by' => OrderToBePaidByEnum::CASH_ON_DELIVERY]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['cash_on_delivery']);
    $order->updateQuietly(['to_be_paid_by' => null]);

    $crossNote->updateQuietly(['collection_address_id' => $crossNote->address_id]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['collection']);
    $crossNote->updateQuietly(['collection_address_id' => null]);

    $order->updateQuietly(['platform_id' => Platform::where('type', PlatformTypeEnum::TIKTOK)->firstOrFail()->id]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['tiktok']);
    $order->updateQuietly(['platform_id' => null]);

    $crossNote->orders()->attach($homeOrder->id);
    $homeOrder->updateQuietly(['to_be_paid_by' => OrderToBePaidByEnum::CASH_ON_DELIVERY, 'platform_id' => Platform::where('type', PlatformTypeEnum::TIKTOK)->firstOrFail()->id]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['tiktok', 'cash_on_delivery']);
    $homeOrder->updateQuietly(['to_be_paid_by' => null, 'platform_id' => null]);
    $crossNote->orders()->detach($homeOrder->id);

    $warehouse        = $crossNote->warehouse;
    $warehouseAddress = $warehouse->address_id;
    $warehouse->updateQuietly(['address_id' => null]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['warehouse_without_address'])
        ->and(fn () => \App\Actions\Dispatching\Shipment\StoreShipment::make()->action($crossNote->fresh(), $theirs, ['tracking' => 'NOADDR']))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'has no address');
    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['allow_customs_crossing' => false]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['warehouse_without_address']);
    $warehouse->updateQuietly(['address_id' => $warehouseAddress]);
    $deliverTo('GB');
    expect(shipmentRefusals($crossNote, $theirs))->toBe(['customs_crossing']);
    $deliverTo('ES');

    expect($crossNote->shipments()->count())->toBe(0);

    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => false]);
    expect(shipmentRefusals($crossNote, $theirs))->toBe([])
        ->and($crossNote->shipments()->count())->toBe(1);

    $homeNote->deliveryAddress->updateQuietly(['country_id' => $countryId('US'), 'country_code' => 'US']);
    $homeNote->updateQuietly(['is_cash_on_delivery' => true, 'collection_address_id' => $homeNote->address_id]);
    expect(shipmentRefusals($homeNote, shipperOf($this->organisation)))->toBe([])
        ->and($homeNote->shipments()->count())->toBe(1);
});

/**
 * INI-485. Products of the test shop, turned dropshipping, each on SKOs of stocks of its own that the other company holds
 * 1000 of: in stock, at 0, on demand, discontinued, made of two SKOs, and sold at six per SKO. Every input the stock
 * figure reads is set here, and each product gets a listing on a Shopify channel of the customer.
 * Setups: 'none' lists no other warehouse while the direction is on at both sides; 'switched_off' lists the other
 * company while it has switched the direction off.
 *
 * @return array{0: array<string, Product>, 1: array<string, OrgStock>, 2: \App\Models\SysAdmin\Organisation, 3: array<string, CustomerSalesChannel>}
 */
function otherWarehouseStockFixture($test, string $setup): array
{
    $test->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
    $test->shop->dropshippingStats()->exists() || $test->shop->dropshippingStats()->create([]);
    $orgPartner = crossDispatchDirection($test->organisation);
    $dispatcher = $orgPartner->partner;
    UpdateCrossDispatchSettings::make()->action($orgPartner, $test->organisation, ['enabled' => true, 'allow_customs_crossing' => true]);
    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => $setup === 'none']);

    $settings = \Illuminate\Support\Arr::except($test->shop->fresh()->settings ?? [], 'stock_from_other_warehouses');
    if ($setup === 'switched_off') {
        $settings['stock_from_other_warehouses'] = [['organisation_id' => $dispatcher->id, 'safety_margin' => 0]];
    }
    $test->shop->updateQuietly(['settings' => $settings]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();

    $tradeUnit = $test->product->tradeUnits->first();
    $newSko    = function (array $attributes) use ($test, $dispatcher, $tradeUnit): OrgStock {
        $stock   = \App\Actions\Goods\Stock\StoreStock::make()->action($test->group, array_merge(\App\Models\Goods\Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
        $homeSko = StoreOrgStock::make()->action($test->organisation, $stock, []);
        $homeSko->updateQuietly(array_merge(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'has_been_in_warehouse' => true, 'packed_in' => 1, 'quantity_available' => 0], $attributes));
        $homeSko->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);
        dispatcherSkoFor($dispatcher, $homeSko->refresh());

        return $homeSko->refresh();
    };

    $skos = [
        'in_stock'  => $newSko(['quantity_available' => 10]),
        'at_zero'   => $newSko([]),
        'on_demand' => $newSko(['is_on_demand' => true]),
        'second'    => $newSko(['quantity_available' => 6]),
        'pack'      => $newSko(['quantity_available' => 13]),
    ];

    $channels = [];
    foreach (['woo' => PlatformTypeEnum::WOOCOMMERCE, 'ebay' => PlatformTypeEnum::EBAY, 'tiktok' => PlatformTypeEnum::TIKTOK, 'shopify' => PlatformTypeEnum::SHOPIFY] as $key => $platformType) {
        $channels[$key] = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action(
            $test->customer,
            $test->group->platforms()->where('type', $platformType)->firstOrFail(),
            ['reference' => 'ini485_'.$key.'_'.Str::random(6)]
        );
    }

    $newProduct = function (array $orgStocks, array $attributes = []) use ($test, $tradeUnit, $channels): Product {
        $product = StoreProduct::make()->action($test->product->family, array_merge(Product::factory()->definition(), [
            'trade_units' => [['id' => $tradeUnit->id, 'quantity' => 1]],
            'price'       => 10,
        ]));
        $product->orgStocks()->sync($orgStocks);
        $portfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($channels['shopify'], $product->refresh(), []);
        $portfolio->updateQuietly(['sku' => 'ini485-'.$product->id, 'platform_sku' => null]);
        $product->updateQuietly(array_merge([
            'state'                         => ProductStateEnum::ACTIVE,
            'is_for_sale'                   => true,
            'is_on_demand'                  => false,
            'available_quantity'            => 0,
            'status'                        => \App\Enums\Catalogue\Product\ProductStatusEnum::OUT_OF_STOCK,
            'back_in_stock_since'           => null,
            'out_of_stock_since'            => null,
            'available_quantity_updated_at' => null,
        ], $attributes));

        return $product->refresh();
    };

    $products = [
        'in_stock'     => $newProduct([$skos['in_stock']->id => ['quantity' => 1]]),
        'at_zero'      => $newProduct([$skos['at_zero']->id => ['quantity' => 1]]),
        'on_demand'    => $newProduct([$skos['on_demand']->id => ['quantity' => 1]]),
        'discontinued' => $newProduct([$skos['in_stock']->id => ['quantity' => 1]], ['state' => ProductStateEnum::DISCONTINUED, 'status' => \App\Enums\Catalogue\Product\ProductStatusEnum::DISCONTINUED]),
        'two_skos'     => $newProduct([$skos['in_stock']->id => ['quantity' => 1], $skos['second']->id => ['quantity' => 2]]),
        'six_per_sko'  => $newProduct([$skos['pack']->id => ['quantity' => 0.16666667]]),
    ];

    return [$products, $skos, $dispatcher, $channels];
}

/**
 * Hydrates each product's stock figure and reads what customers and each channel are told. A stamp reads 'first run'
 * when it carries $firstRunAt, so a later run that leaves it alone reads the same.
 *
 * @param  array<string, Product>  $products
 * @param  array<string, CustomerSalesChannel>  $channels
 */
function otherWarehouseStockFigures(array $products, array $channels, \Illuminate\Support\Carbon $firstRunAt): array
{
    $stamp = fn ($value) => $value === null ? null : (\Illuminate\Support\Carbon::parse($value)->equalTo($firstRunAt) ? 'first run' : 'other');

    \Illuminate\Support\Facades\Cache::forget('shopify:fetch_stock:channel:'.$channels['shopify']->id);
    $figures = [];
    foreach ($products as $key => $product) {
        \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($product->fresh());
        $product = $product->fresh();

        $figures[$key] = [
            'available_quantity'            => $product->available_quantity,
            'available_quantity_elsewhere'  => $product->available_quantity_elsewhere,
            'status'                        => $product->status->value,
            'is_on_demand'                  => $product->is_on_demand,
            'available_quantity_updated_at' => $stamp($product->available_quantity_updated_at),
            'back_in_stock_since'           => $stamp($product->back_in_stock_since),
            'out_of_stock_since'            => $stamp($product->out_of_stock_since),
            'woo'                           => \App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $channels['woo']->fresh()),
            'ebay'                          => \App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio::quantityToSend($product, $channels['ebay']->fresh()),
            'tiktok'                        => \App\Actions\Dropshipping\Tiktok\Product\UpdateTiktokInventory::quantityToSend($product, $channels['tiktok']->fresh()),
            'tiktok_listing'                => $product->homeAvailableQuantity(),
        ];
    }

    $shopifyPayload = \App\Actions\Dropshipping\Shopify\Fulfilment\Callback\CallbackFetchStock::make()->handle(new \App\Models\Dropshipping\ShopifyUser(['customer_sales_channel_id' => $channels['shopify']->id]));
    foreach ($products as $key => $product) {
        $figures[$key]['shopify'] = $shopifyPayload['ini485-'.$product->id] ?? 'missing';
    }

    return $figures;
}

test('stock figures and channel payloads stay what they were before other warehouses could count', function (string $setup) {
    $firstRunAt = now()->startOfSecond();
    \Illuminate\Support\Carbon::setTestNow($firstRunAt);

    try {
        [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, $setup);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $resolver = $setup === 'none' ? $this->spy(ResolveCrossDispatch::class) : null;
        $figures  = otherWarehouseStockFigures($products, $channels, $firstRunAt);
        app()->forgetInstance(ResolveCrossDispatch::class);
        $columns  = ['available_quantity', 'available_quantity_elsewhere', 'status', 'is_on_demand', 'available_quantity_updated_at', 'back_in_stock_since', 'out_of_stock_since', 'woo', 'ebay', 'tiktok', 'tiktok_listing', 'shopify'];
        $expected = array_map(fn (array $row) => array_combine($columns, $row), [
            'in_stock'     => [10, 0, 'for-sale', false, 'first run', 'first run', null, 10, 10, 10, 10, 10],
            'at_zero'      => [0, 0, 'out-of-stock', false, null, null, 'first run', 0, 0, 0, 0, 0],
            'on_demand'    => [250, 0, 'for-sale', true, 'first run', 'first run', null, 250, 250, 250, 250, 250],
            'discontinued' => [null, 0, 'discontinued', false, 'first run', null, null, 0, 0, 0, null, 0],
            'two_skos'     => [3, 0, 'for-sale', false, 'first run', 'first run', null, 3, 3, 3, 3, 3],
            'six_per_sko'  => [78, 0, 'for-sale', false, 'first run', 'first run', null, 78, 78, 78, 78, 78],
        ]);

        expect($figures)->toBe($expected);
        if ($setup === 'none') {
            expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'org_partners') || str_contains($sql, 'from "org_stocks" where "organisation_id" = ?'))->all())->toBe([]);
            $resolver->shouldNotHaveReceived('wholeProductsIn');
        }

        \Illuminate\Support\Carbon::setTestNow($firstRunAt->copy()->addHour());
        expect(otherWarehouseStockFigures($products, $channels, $firstRunAt))->toBe($expected);
    } finally {
        \Illuminate\Support\Carbon::setTestNow();
    }
})->with(['no other warehouse listed' => 'none', 'a listed warehouse whose direction is switched off' => 'switched_off']);

test('a stock change in the other company hydrates none of the shop\'s products, whichever path reaches its SKOs', function (string $setup) {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, $setup);
    $orgPartner     = ResolveCrossDispatch::direction($this->organisation->id, $dispatcher->id);
    $theirSko       = fn (OrgStock $homeSko) => OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $homeSko->stock_id)->sole();
    $shopProductIds = collect($products)->pluck('id')->all();
    $isHydrate      = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::class);
    $hydrates       = fn (array $productIds) => fn ($job) => $isHydrate($job) && in_array($job->getParameters()[0]->id, $productIds, true);

    [$order] = submittedOrderWithTransaction($this->customer, $products['in_stock'], 2);
    UpdateCrossDispatchSettings::make()->action($orgPartner, $dispatcher, ['enabled' => true]);
    $crossNote = \App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::run($order->fresh(), $dispatcher);
    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => $setup === 'none']);

    \Illuminate\Support\Facades\Bus::fake([$isHydrate]);

    $moveStock = function (OrgStock $sko, float $quantity): void {
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($sko->organisation->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(
            orgStock: $sko,
            location: $location,
            modelData: ['quantity' => $quantity, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
            strict: false
        );
        expect((float) $sko->fresh()->quantity_in_locations)->toBe($quantity);
    };
    $changeHasBeenInWarehouse = function (OrgStock $sko): void {
        $sko->updateQuietly(['has_been_in_warehouse' => !$sko->has_been_in_warehouse]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsAvailableQuantity::run($sko->fresh());
    };
    $changeOnDemand = function (OrgStock $sko): void {
        $sko->updateQuietly(['is_on_demand' => false]);
        \App\Actions\Inventory\OrgStock\UpdateOrgStock::make()->action($sko->fresh(), ['is_on_demand' => true]);
        expect($sko->fresh()->is_on_demand)->toBeTrue();
    };

    foreach (['in_stock', 'second', 'pack'] as $key) {
        $moveStock($theirSko($skos[$key]), 40);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($theirSko($skos[$key])->id);
        $changeHasBeenInWarehouse($theirSko($skos[$key]));
    }
    $this->artisan('hydrate:org_stocks_quantity_in_locations', ['organisations' => [$dispatcher->slug]])->assertExitCode(0);
    $line = $crossNote->deliveryNoteItems()->sole();
    $line->update(['quantity_required' => 3]);
    expect($line->org_stock_id)->toBe($theirSko($skos['in_stock'])->id)
        ->and((float) $theirSko($skos['in_stock'])->quantity_to_be_picked)->toBe($dispatcher->is_aiku_stock_control ? 3.0 : 0.0);
    \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $hydrates($shopProductIds));

    $moveStock($skos['in_stock'], 40);
    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $hydrates([$products['in_stock']->id]));

    $spy = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::spy();
    foreach (['in_stock', 'second', 'pack'] as $key) {
        $changeOnDemand($theirSko($skos[$key]));
    }
    $spy->shouldNotHaveReceived('handle', [Mockery::on(fn ($product) => in_array($product->id, $shopProductIds, true))]);

    $changeOnDemand($skos['second']);
    $spy->shouldHaveReceived('handle')->with(Mockery::on(fn ($product) => $product->id === $products['two_skos']->id));
})->with(['no other warehouse listed' => 'none', 'a listed warehouse whose direction is switched off' => 'switched_off']);

test('an order the home warehouse cannot cover still gets its delivery note at home', function (string $setup) {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, $setup);
    [$order, $transaction] = submittedOrderWithTransaction($this->customer, $products['at_zero'], 3);

    expect(array_key_exists($dispatcher->id, GetCrossDispatchCoverage::run($order->fresh())))->toBe($setup === 'none');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $deliveryNote = SendOrderToWarehouse::make()->action($order->fresh(), []);
    if ($setup === 'none') {
        expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'org_partners') || str_contains($sql, '"products"."available_quantity_elsewhere" >'))->all())->toBe([]);
    }

    expect($deliveryNote->isCrossDispatch())->toBeFalse()
        ->and(deliveryNoteFingerprint($deliveryNote))->toEqual([
            'note'  => [
                'group_id'        => $this->group->id,
                'organisation_id' => $this->organisation->id,
                'shop_id'         => $this->shop->id,
                'customer_id'     => $this->customer->id,
                'warehouse_id'    => $this->organisation->warehouses()->first()->id,
                'reference'       => $order->reference,
                'state'           => 'unassigned',
                'type'            => 'order',
                'shop_type'       => ShopTypeEnum::DROPSHIPPING->value,
            ],
            'lines' => [[
                'organisation_id'            => $this->organisation->id,
                'shop_id'                    => $this->shop->id,
                'transaction_id'             => $transaction->id,
                'org_stock_id'               => $skos['at_zero']->id,
                'stock_id'                   => $skos['at_zero']->stock_id,
                'org_stock_family_id'        => $skos['at_zero']->org_stock_family_id,
                'quantity_required'          => 3.0,
                'original_quantity_required' => 3.0,
                'quantity_picked'            => 0.0,
                'is_dirty'                   => false,
            ]],
        ]);
})->with(['no other warehouse listed' => 'none', 'a listed warehouse whose direction is switched off' => 'switched_off']);

test('the other company can ship as many whole products as its SKOs of the same pack hold after the margin, and none when the pack differs', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $theirSko = fn (string $key) => OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos[$key]->stock_id)->sole();
    $shippable = fn (string $product, int $margin = 0) => ResolveCrossDispatch::make()->wholeProductsIn($products[$product]->fresh(), $dispatcher, $margin);

    $theirSko('in_stock')->updateQuietly(['quantity_available' => 12]);
    $theirSko('second')->updateQuietly(['quantity_available' => 13]);
    $theirSko('pack')->updateQuietly(['quantity_available' => 13]);
    expect($shippable('in_stock'))->toBe(12)
        ->and($shippable('in_stock', 2))->toBe(10)
        ->and($shippable('in_stock', 20))->toBe(0)
        ->and($shippable('six_per_sko'))->toBe(78)
        ->and($shippable('six_per_sko', 2))->toBe(66)
        ->and($shippable('two_skos'))->toBe(6)
        ->and($shippable('two_skos', 2))->toBe(5);

    $theirSko('in_stock')->updateQuietly(['packed_in' => 7]);
    expect($shippable('in_stock'))->toBeNull()->and($shippable('two_skos'))->toBeNull();
    $theirSko('in_stock')->updateQuietly(['packed_in' => 1]);

    $tradeUnit = $skos['pack']->tradeUnits->first();
    $theirSko('pack')->tradeUnits()->updateExistingPivot($tradeUnit->id, ['quantity' => 2]);
    expect($shippable('six_per_sko'))->toBeNull();
    $theirSko('pack')->tradeUnits()->updateExistingPivot($tradeUnit->id, ['quantity' => 1]);
    expect($shippable('six_per_sko'))->toBe(78);

    $theirTwin = StoreOrgStock::make()->action($dispatcher, $skos['in_stock']->stock, []);
    expect($shippable('in_stock'))->toBeNull();
    $theirTwin->delete();

    $homeTwin = StoreOrgStock::make()->action($this->organisation, $skos['in_stock']->stock, []);
    $homeTwin->updateQuietly(['packed_in' => 1]);
    $homeTwin->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 1]]);
    $products['in_stock']->orgStocks()->attach($homeTwin->id, ['quantity' => 1]);
    expect($shippable('in_stock'))->toBeNull();
    $products['in_stock']->orgStocks()->detach($homeTwin->id);
    $homeTwin->delete();
    expect($shippable('in_stock'))->toBe(12);

    $theirSko('in_stock')->updateQuietly(['state' => OrgStockStateEnum::DISCONTINUING]);
    expect($shippable('in_stock'))->toBe(12);
    foreach ([OrgStockStateEnum::DISCONTINUED, OrgStockStateEnum::SUSPENDED, OrgStockStateEnum::ABNORMALITY] as $state) {
        $theirSko('in_stock')->updateQuietly(['state' => $state]);
        expect($shippable('in_stock'))->toBeNull();
    }
    $theirSko('in_stock')->updateQuietly(['state' => OrgStockStateEnum::ACTIVE]);

    $theirSko('second')->updateQuietly(['sku_commercial_value' => 0]);
    $theirSko('in_stock')->updateQuietly(['sku_commercial_value' => 0]);
    expect($shippable('two_skos'))->toBeNull()->and($shippable('in_stock'))->toBe(12);
    $theirSko('second')->updateQuietly(['sku_commercial_value' => 2]);
    $theirSko('in_stock')->updateQuietly(['sku_commercial_value' => 2]);

    $this->mock(FillToOrder::class)->shouldReceive('artefactFor')->andReturn(new Artefact());
    expect($shippable('in_stock'))->toBeNull();
    app()->forgetInstance(FillToOrder::class);

    $theirSko('pack')->delete();
    expect($shippable('six_per_sko'))->toBeNull();
    $products['at_zero']->orgStocks()->detach();
    expect($shippable('at_zero'))->toBeNull()
        ->and($shippable('two_skos'))->toBe(6);
});

test('the shippable whole products of a product agree with the order resolver line by line', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $products = \Illuminate\Support\Arr::except($products, 'discontinued');
    $theirSko = fn (string $key) => OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos[$key]->stock_id)->sole();

    $order = StoreOrder::make()->action($this->customer, []);
    $transactionOf = [];
    foreach ($products as $key => $product) {
        $transactionOf[$key] = StoreTransaction::make()->action($order, $product->historicAsset, ['quantity_ordered' => 1])->id;
    }
    $order = SubmitOrder::make()->action($order->refresh());

    $agree = function (array $expectedRefused) use ($products, $dispatcher, $order, $transactionOf) {
        $refusedLines = collect(ResolveCrossDispatch::run($order->fresh(), $dispatcher->fresh())['refusals'])->pluck('transaction_id')->filter()->unique();
        $byResolver   = array_map(fn (string $key) => $refusedLines->contains($transactionOf[$key]), array_combine(array_keys($products), array_keys($products)));
        $byMethod     = array_map(fn (Product $product) => ResolveCrossDispatch::make()->wholeProductsIn($product->fresh(), $dispatcher) === null, $products);

        expect($byMethod)->toBe($byResolver)
            ->and(array_keys(array_filter($byMethod)))->toBe($expectedRefused);
    };

    $agree([]);

    $theirSko('in_stock')->updateQuietly(['packed_in' => 7]);
    $agree(['in_stock', 'two_skos']);
    $theirSko('in_stock')->updateQuietly(['packed_in' => 1]);

    $tradeUnit = $skos['pack']->tradeUnits->first();
    $theirSko('pack')->tradeUnits()->updateExistingPivot($tradeUnit->id, ['quantity' => 2]);
    $agree(['six_per_sko']);
    $theirSko('pack')->tradeUnits()->updateExistingPivot($tradeUnit->id, ['quantity' => 1]);

    $theirTwin = StoreOrgStock::make()->action($dispatcher, $skos['second']->stock, []);
    $agree(['two_skos']);
    $theirTwin->delete();

    $theirSko('at_zero')->updateQuietly(['state' => OrgStockStateEnum::SUSPENDED]);
    $theirSko('second')->updateQuietly(['sku_commercial_value' => 0]);
    $agree(['at_zero', 'two_skos']);
    $theirSko('at_zero')->updateQuietly(['state' => OrgStockStateEnum::ACTIVE]);
    $theirSko('second')->updateQuietly(['sku_commercial_value' => 2]);

    $theirSko('on_demand')->delete();
    $agree(['on_demand']);

    $theirSko('in_stock')->updateQuietly(['quantity_available' => 12]);
    $theirSko('second')->updateQuietly(['quantity_available' => 13]);
    foreach ([['in_stock', 13, 12], ['two_skos', 13, 6], ['six_per_sko', 13, 78], ['six_per_sko', 501, 3005]] as [$key, $packSkos, $whole]) {
        $theirSko('pack')->updateQuietly(['quantity_available' => $packSkos]);
        expect(ResolveCrossDispatch::make()->wholeProductsIn($products[$key]->fresh(), $dispatcher))->toBe($whole);
        foreach ([$whole => [], $whole + 1 => ['not_enough_stock']] as $quantity => $reasons) {
            [$singleLineOrder] = submittedOrderWithTransaction($this->customer, $products[$key], $quantity);
            expect(crossDispatchRefusals($singleLineOrder, $dispatcher))->toBe($reasons);
        }
    }
});

test('a dropshipping shop lists another company\'s warehouse only while both companies have the direction on, each with a whole safety margin', function () {
    $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING, 'settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;
    $save       = fn (array $list) => \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['stock_from_other_warehouses' => $list]);
    $errorsOf   = function (array $list) use ($save): array {
        try {
            $save($list);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    };
    $stored = fn () => \Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'stock_from_other_warehouses');

    expect($this->shop->fresh()->stockFromOtherWarehouses())->toBe([])
        ->and($errorsOf([['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]))->toHaveKey('stock_from_other_warehouses.0.organisation_id');

    UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => true, 'allow_customs_crossing' => true]);
    expect($errorsOf([['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]))->toHaveKey('stock_from_other_warehouses.0.organisation_id');

    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => true]);
    foreach ([[], ['safety_margin' => null], ['safety_margin' => -1], ['safety_margin' => 1.5], ['safety_margin' => 'two']] as $margin) {
        expect($errorsOf([array_merge(['organisation_id' => $dispatcher->id], $margin)]))->toHaveKey('stock_from_other_warehouses.0.safety_margin');
    }
    expect($errorsOf([['organisation_id' => $dispatcher->id, 'safety_margin' => 0], ['organisation_id' => $dispatcher->id, 'safety_margin' => 1]]))->toHaveKey('stock_from_other_warehouses.1.organisation_id')
        ->and($errorsOf([['organisation_id' => $this->organisation->id, 'safety_margin' => 1]]))->toHaveKey('stock_from_other_warehouses.0.organisation_id')
        ->and($stored())->toBeNull();

    expect($errorsOf([['organisation_id' => (string) $dispatcher->id, 'safety_margin' => '2']]))->toBe(['stock_from_other_warehouses' => [\App\Actions\Catalogue\Shop\UpdateShop::stockFromOtherWarehousesNotLiveMessage()]])
        ->and($stored())->toBeNull();

    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]])]);
    expect($this->shop->fresh()->stockFromOtherWarehouses())->toEqual([['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]);

    foreach ([$dispatcher, $this->organisation] as $side) {
        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $side, ['enabled' => false]);
        expect($this->shop->fresh()->stockFromOtherWarehouses())->toBe([])
            ->and($stored())->toEqual([['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]);
        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $side, ['enabled' => true]);
    }
    expect($this->shop->fresh()->stockFromOtherWarehouses())->toEqual([['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]);

    $save([]);
    expect($stored())->toBeNull()
        ->and($this->shop->fresh()->stockFromOtherWarehouses())->toBe([]);

    $this->shop->updateQuietly(['type' => ShopTypeEnum::B2B, 'settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 0]]])]);
    expect($this->shop->fresh()->stockFromOtherWarehouses())->toBe([])
        ->and($errorsOf([['organisation_id' => $dispatcher->id, 'safety_margin' => 0]]))->toHaveKey('stock_from_other_warehouses');
    $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
    $save([]);
});

test('only organisation admins can list other warehouses, from the shop settings screen, while shop admins keep the other settings', function () {
    $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING, 'settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
    $this->shop->dropshippingStats()->exists() || $this->shop->dropshippingStats()->create([]);
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;
    UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => true]);
    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => true]);
    $this->withoutVite();

    $list   = ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 3]]];
    $update = fn () => $this->patch(route('grp.models.org.shop.update', [$this->organisation->id, $this->shop->id]), $list);
    $stored = fn () => \Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'stock_from_other_warehouses');

    actingAsUserWithOnlyPermissions(userWithOnlyPermissions($this->organisation, ["orders.{$this->shop->id}.edit"]), ["orders.{$this->shop->id}.edit"]);
    $update()->assertForbidden();
    expect($stored())->toBeNull();

    $shopAdmin = userWithOnlyPermissions($this->organisation, ['shop-admin.'.$this->shop->id]);
    actingAsUserWithOnlyPermissions($shopAdmin, ['shop-admin.'.$this->shop->id]);
    $update()->assertSessionHasErrors('stock_from_other_warehouses');
    expect($stored())->toBeNull();
    $this->patch(route('grp.models.org.shop.update', [$this->organisation->id, $this->shop->id]), ['packaging_and_inserts_enabled' => $this->shop->fresh()->hasPackagingAndInserts()])->assertSessionHasNoErrors()->assertRedirect();

    $this->patch(route('grp.models.org.shop.update', [$this->organisation->id, $this->shop->id]), ['stock_from_other_warehouses' => []])->assertSessionHasErrors('stock_from_other_warehouses');

    $orgAdmin = userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
    actingAsUserWithOnlyPermissions($orgAdmin, ['org-admin.'.$this->organisation->id]);
    $update()->assertSessionHasErrors(['stock_from_other_warehouses' => \App\Actions\Catalogue\Shop\UpdateShop::stockFromOtherWarehousesNotLiveMessage()]);
    expect($stored())->toBeNull();
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], $list)]);
    expect($stored())->toEqual([['organisation_id' => $dispatcher->id, 'safety_margin' => 3]]);

    UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => false]);
    $field = collect($this->get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $this->shop->slug]))->assertOk()->viewData('page')['props']['formData']['blueprint'])
        ->keyBy('label')->get('Stock from other warehouses')['fields']['stock_from_other_warehouses'];
    expect($field['type'])->toBe('stock_from_other_warehouses')
        ->and($field['readonly'])->toBeTrue()
        ->and($field['warning'])->toBe(\App\Actions\Catalogue\Shop\UpdateShop::stockFromOtherWarehousesNotLiveMessage())
        ->and($field['value'])->toEqual([['organisation_id' => $dispatcher->id, 'safety_margin' => 3]])
        ->and(collect($field['options'])->firstWhere('organisation_id', $dispatcher->id))->toBe(['organisation_id' => $dispatcher->id, 'name' => $dispatcher->name, 'enabled' => false]);

    $update()->assertSessionHasErrors('stock_from_other_warehouses.0.organisation_id');
    $this->patch(route('grp.models.org.shop.update', [$this->organisation->id, $this->shop->id]), ['stock_from_other_warehouses' => []])->assertSessionHasNoErrors();
    expect($stored())->toBeNull();
});

/**
 * Puts an organisation's first warehouse, and the organisation itself, in a country, for the customs rule.
 */
function shipsFromCountry(\App\Models\SysAdmin\Organisation $organisation, string $countryCode): void
{
    $country = Country::where('code', $countryCode)->firstOrFail();
    $organisation->updateQuietly(['country_id' => $country->id]);
    $organisation->warehouses()->orderBy('id')->first()?->address?->updateQuietly(['country_id' => $country->id, 'country_code' => $countryCode]);
}

test('a product sells the stock of the best single listed warehouse, after its margin, and only home stock makes it new', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $orgPartner = ResolveCrossDispatch::direction($this->organisation->id, $dispatcher->id);
    $homeCountry      = ResolveCrossDispatch::warehouseCountryCode($this->organisation);
    $dispatcherCountry = ResolveCrossDispatch::warehouseCountryCode($dispatcher);
    shipsFromCountry($this->organisation, 'ES');
    shipsFromCountry($dispatcher, 'SK');
    UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['allow_customs_crossing' => false]);
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]])]);

    $theirSko = fn (string $key) => OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos[$key]->stock_id)->sole();
    $hydrate  = fn (string $key) => \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products[$key]->fresh())->fresh();
    $figure   = fn (Product $product) => [$product->available_quantity, $product->available_quantity_elsewhere, $product->status->value];

    try {
        $products['at_zero']->updateQuietly(['status' => \App\Enums\Catalogue\Product\ProductStatusEnum::COMING_SOON, 'has_live_webpage' => true, 'first_in_stock_at' => null, 'first_available_at' => null]);
        $skos['at_zero']->updateQuietly(['has_been_in_warehouse' => false]);
        $theirSko('at_zero')->updateQuietly(['quantity_available' => 12]);
        $product = $hydrate('at_zero');
        expect($figure($product))->toBe([10, 10, 'for-sale'])
            ->and($product->first_in_stock_at)->toBeNull()
            ->and($product->first_available_at)->toBeNull()
            ->and($product->back_in_stock_since)->not->toBeNull();

        \App\Actions\Maintenance\Catalogue\RepairProductsFirstAvailableAt::make()->handle($product->fresh());
        expect($product->fresh()->first_available_at)->toBeNull();

        $skos['at_zero']->updateQuietly(['quantity_available' => 3, 'has_been_in_warehouse' => true]);
        $product = $hydrate('at_zero');
        expect($figure($product))->toBe([10, 7, 'for-sale'])
            ->and($product->first_in_stock_at)->not->toBeNull()
            ->and($product->first_available_at)->not->toBeNull();

        $launchedAt = now()->subYear()->startOfSecond();
        $skos['pack']->updateQuietly(['quantity_available' => 0]);
        $theirSko('pack')->updateQuietly(['quantity_available' => 13]);
        $products['six_per_sko']->updateQuietly(['first_in_stock_at' => null, 'first_available_at' => $launchedAt]);
        expect($figure($hydrate('six_per_sko')))->toBe([66, 66, 'for-sale']);
        $skos['pack']->updateQuietly(['quantity_available' => 1]);
        $product = $hydrate('six_per_sko');
        expect($figure($product))->toBe([66, 60, 'for-sale'])
            ->and($product->first_in_stock_at)->toBeNull()
            ->and($product->first_available_at->equalTo($launchedAt))->toBeTrue();

        $skos['in_stock']->updateQuietly(['quantity_available' => 5]);
        $theirSko('in_stock')->updateQuietly(['quantity_available' => 5]);
        expect($figure($hydrate('in_stock')))->toBe([5, 0, 'for-sale']);
        $skos['in_stock']->updateQuietly(['quantity_available' => 2]);
        $theirSko('in_stock')->updateQuietly(['quantity_available' => 11]);
        expect($figure($hydrate('in_stock')))->toBe([9, 7, 'for-sale'])
            ->and($figure($hydrate('on_demand')))->toBe([250, 0, 'for-sale'])
            ->and($figure($hydrate('discontinued')))->toBe([null, 0, 'discontinued']);

        $skos['in_stock']->updateQuietly(['quantity_available' => 0]);
        $updatedAt = $hydrate('in_stock')->available_quantity_updated_at;
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(5));
        expect($figure($hydrate('in_stock')))->toBe([9, 9, 'for-sale'])
            ->and($hydrate('in_stock')->available_quantity_updated_at->equalTo($updatedAt))->toBeTrue();

        shipsFromCountry($dispatcher, 'GB');
        expect($figure($hydrate('in_stock')))->toBe([0, 0, 'out-of-stock']);
        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['allow_customs_crossing' => true]);
        expect($figure($hydrate('in_stock')))->toBe([9, 9, 'for-sale']);

        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => false]);
        expect($figure($hydrate('in_stock')))->toBe([0, 0, 'out-of-stock'])
            ->and($figure($hydrate('at_zero')))->toBe([3, 0, 'for-sale']);
    } finally {
        \Illuminate\Support\Carbon::setTestNow();
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        $homeCountry && shipsFromCountry($this->organisation, $homeCountry);
        $dispatcherCountry && shipsFromCountry($dispatcher, $dispatcherCountry);
    }
});

test('a whole shop hydrated from the command reads the other company\'s SKOs once and gets the same figures', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 0]]])]);

    try {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->artisan('product:hydrate-available-quantity', ['--shop' => [$this->shop->slug]])->assertExitCode(0);
        $theirOrgStockReads = collect($queries)->filter(fn (string $sql) => str_contains($sql, 'from "org_stocks" where "organisation_id" = ?'))->count();
        $chunks             = (int) ceil(Product::where('shop_id', $this->shop->id)->count() / 100);

        $byCommand = collect($products)->map(fn (Product $product) => [$product->fresh()->available_quantity, $product->fresh()->available_quantity_elsewhere])->all();
        foreach ($products as $product) {
            \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($product->fresh());
        }
        $oneByOne = collect($products)->map(fn (Product $product) => [$product->fresh()->available_quantity, $product->fresh()->available_quantity_elsewhere])->all();

        expect($theirOrgStockReads)->toBe($chunks)
            ->and($byCommand)->toBe($oneByOne)
            ->and($byCommand['at_zero'])->toBe([1000, 1000])
            ->and($byCommand['in_stock'])->toBe([1000, 990]);
    } finally {
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        $this->artisan('product:hydrate-available-quantity', ['--shop' => [$this->shop->slug]])->assertExitCode(0);
    }
    expect([$products['at_zero']->fresh()->available_quantity, $products['at_zero']->fresh()->available_quantity_elsewhere])->toBe([0, 0])
        ->and(Product::where('shop_id', $this->shop->id)->where('available_quantity_elsewhere', '>', 0)->exists())->toBeFalse();
});

test('TikTok is told only the home stock of a product that sells another company\'s stock, and a change at home reaches it under an unchanged figure', function () {
    [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, 'none');
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]])]);
    $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['in_stock']->stock_id)->sole();
    $hydrate  = fn () => \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['in_stock']->fresh())->fresh();
    $tiktok   = $channels['tiktok']->fresh();
    $sweep    = \App\Actions\Dropshipping\Tiktok\Product\UpdateInventoryTiktokProducts::make();

    try {
        $portfolio = \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($tiktok, $products['in_stock']->fresh(), []);
        $portfolio->updateQuietly(['platform_product_id' => 'ini485-tiktok', 'platform_status' => true]);

        $skos['in_stock']->updateQuietly(['quantity_available' => 2]);
        $theirSko->updateQuietly(['quantity_available' => 11]);
        $product = $hydrate();
        expect([$product->available_quantity, $product->available_quantity_elsewhere, $product->homeAvailableQuantity()])->toBe([9, 7, 2])
            ->and(\App\Actions\Dropshipping\Tiktok\Product\UpdateTiktokInventory::quantityToSend($product, $tiktok))->toBe(2)
            ->and(\App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $channels['woo']->fresh()))->toBe(9)
            ->and(\App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio::quantityToSend($product, $channels['ebay']->fresh()))->toBe(9);

        $portfolio->updateQuietly(['last_stock_value' => 2, 'stock_last_updated_at' => now()->subHours(2), 'stock_last_fail_updated_at' => now()->addMinute()]);
        expect($sweep->checkIfApplicable($portfolio->fresh(), $tiktok))->toBeFalse();

        $stampedAt = $product->available_quantity_updated_at;
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(5));
        $skos['in_stock']->updateQuietly(['quantity_available' => 0]);
        $product = $hydrate();
        expect([$product->available_quantity, $product->available_quantity_elsewhere, $product->homeAvailableQuantity()])->toBe([9, 9, 0])
            ->and($product->available_quantity_updated_at->gt($stampedAt))->toBeTrue()
            ->and(\App\Actions\Dropshipping\Tiktok\Product\UpdateTiktokInventory::quantityToSend($product, $tiktok))->toBe(0)
            ->and($sweep->checkIfApplicable($portfolio->fresh(), $tiktok))->toBeTrue();

        $portfolio->updateQuietly(['stock_last_fail_updated_at' => null, 'stock_last_updated_at' => now()->subMinute()]);
        expect($sweep->checkIfApplicable($portfolio->fresh(), $tiktok))->toBeTrue();
        $portfolio->updateQuietly(['last_stock_value' => 0]);
        expect($sweep->checkIfApplicable($portfolio->fresh(), $tiktok))->toBeFalse();
    } finally {
        \Illuminate\Support\Carbon::setTestNow();
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
    }
});

test('a stock change in another company re-hydrates the products of the shops that count it, and only those', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $orgPartner = ResolveCrossDispatch::direction($this->organisation->id, $dispatcher->id);
    $theirSko   = fn (string $key) => OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos[$key]->stock_id)->sole();
    $isHydrate  = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::class);
    $hydrated   = fn () => collect(\Illuminate\Support\Facades\Bus::dispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isHydrate))->map(fn ($job) => $job->getParameters()[0]->id)->unique()->sort()->values()->all();
    $moveStock  = function (OrgStock $sko, float $quantity): void {
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($sko->organisation->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $sko, location: $location, modelData: ['quantity' => $quantity, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
    };

    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, array_merge(\App\Models\Catalogue\Shop::factory()->definition(), ['type' => ShopTypeEnum::DROPSHIPPING]));
    [, $otherProduct] = createProduct($otherShop);
    $otherProduct->orgStocks()->sync([$skos['in_stock']->id => ['quantity' => 1]]);

    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 0]]])]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();

    try {
        \Illuminate\Support\Facades\Bus::fake([$isHydrate]);

        $moveStock($theirSko('in_stock'), 40);
        $expected = collect([$products['in_stock']->id, $products['discontinued']->id, $products['two_skos']->id])->sort()->values()->all();
        expect($hydrated())->toBe($expected);

        $before = $hydrated();
        \App\Actions\Inventory\OrgStock\UpdateOrgStock::make()->action($theirSko('at_zero'), ['state' => OrgStockStateEnum::DISCONTINUING]);
        expect(array_values(array_diff($hydrated(), $before)))->toBe([$products['at_zero']->id]);

        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => false]);
        $before = $hydrated();
        $moveStock($theirSko('pack'), 40);
        expect($hydrated())->toBe($before);

        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Dispatching\CrossDispatch\UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => true]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
        $second  = $theirSko('second');
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::run($second);
        $cold = $queries;
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::run($second);
        expect($cold)->toBe(1)
            ->and($queries - $cold)->toBe(0)
            ->and($hydrated())->toBe($before);
    } finally {
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
    }
});

test('the self-heal leaves a product that sells another company\'s stock alone while its home part is in sync', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]])]);
    OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['in_stock']->stock_id)->sole()->updateQuietly(['quantity_available' => 11]);
    $isHydrate = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::class);
    $hydrates  = fn (int $productId) => fn ($job) => $isHydrate($job) && $job->getParameters()[0]->id === $productId;

    try {
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($this->organisation->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $skos['in_stock'], location: $location, modelData: ['quantity' => 2, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
        $product = $products['in_stock']->fresh();
        expect((float) $skos['in_stock']->fresh()->quantity_available)->toBe(2.0)
            ->and([$product->available_quantity, $product->available_quantity_elsewhere])->toBe([9, 7]);

        \Illuminate\Support\Facades\Bus::fake([$isHydrate]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($skos['in_stock']->id);
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $hydrates($products['in_stock']->id));
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $hydrates($products['two_skos']->id));

        $products['in_stock']->updateQuietly(['available_quantity_elsewhere' => 6]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($skos['in_stock']->id);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $hydrates($products['in_stock']->id));
    } finally {
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
    }
});

/**
 * The in-stock product of the fixture selling the other company's stock: 2 at home, 9 shippable there after the margin.
 *
 * @return array{0: Product, 1: \App\Models\Dropshipping\CustomerSalesChannel}
 */
function pooledInStockProduct($test, array $products, array $skos, \App\Models\SysAdmin\Organisation $dispatcher, array $channels): array
{
    $test->shop->updateQuietly(['settings' => array_merge($test->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 2]]])]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
    $skos['in_stock']->updateQuietly(['quantity_available' => 2]);
    OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['in_stock']->stock_id)->sole()->updateQuietly(['quantity_available' => 11]);
    $product = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['in_stock']->fresh())->fresh();
    expect([$product->available_quantity, $product->available_quantity_elsewhere])->toBe([9, 7])
        ->and(\App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $channels['woo']->fresh()))->toBe(9);

    return [$product, $channels['woo']->fresh()];
}

test('removing the other warehouse from the shop re-hydrates its products, and they drop to the home figure', function () {
    [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, 'none');
    [$product, $woo] = pooledInStockProduct($this, $products, $skos, $dispatcher, $channels);
    $isShopHydrate = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\HydrateProductsStockFromOtherWarehouses::class);

    try {
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(5));
        \Illuminate\Support\Facades\Bus::fake([$isShopHydrate]);
        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['stock_from_other_warehouses' => []]);
        $dispatched = \Illuminate\Support\Facades\Bus::dispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isShopHydrate);
        expect($dispatched)->toHaveCount(1)
            ->and($dispatched->first()->getParameters()[0]->id)->toBe($this->shop->id)
            ->and($products['in_stock']->fresh()->available_quantity)->toBe(9);

        expect(\App\Actions\Catalogue\Product\Hydrators\HydrateProductsStockFromOtherWarehouses::run($this->shop->fresh()))->toBe(Product::where('shop_id', $this->shop->id)->count());
        $healed = $products['in_stock']->fresh();
        expect([$healed->available_quantity, $healed->available_quantity_elsewhere])->toBe([2, 0])
            ->and($healed->available_quantity_updated_at->gt($product->available_quantity_updated_at))->toBeTrue()
            ->and(\App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio::quantityToSend($healed, $woo))->toBe(2);
    } finally {
        \Illuminate\Support\Carbon::setTestNow();
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
    }
});

test('a direction switched off at either side, or its customs crossing taken back, re-hydrates the shops that count it', function () {
    [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, 'none');
    $orgPartner = ResolveCrossDispatch::direction($this->organisation->id, $dispatcher->id);
    $homeCountry       = ResolveCrossDispatch::warehouseCountryCode($this->organisation);
    $dispatcherCountry = ResolveCrossDispatch::warehouseCountryCode($dispatcher);

    try {
        foreach ([[$dispatcher, ['enabled' => false]], [$this->organisation, ['enabled' => false]], [$this->organisation, ['allow_customs_crossing' => false]]] as [$side, $change]) {
            shipsFromCountry($this->organisation, 'ES');
            shipsFromCountry($dispatcher, 'GB');
            foreach ([$this->organisation, $dispatcher] as $eachSide) {
                UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $eachSide, ['enabled' => true]);
            }
            UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $this->organisation, ['allow_customs_crossing' => true]);
            [$product, $woo] = pooledInStockProduct($this, $products, $skos, $dispatcher, $channels);

            \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(5));
            UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $side, $change);
            $healed = $products['in_stock']->fresh();
            expect([$healed->available_quantity, $healed->available_quantity_elsewhere])->toBe([2, 0])
                ->and($healed->available_quantity_updated_at->gt($product->available_quantity_updated_at))->toBeTrue()
                ->and(\App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio::quantityToSend($healed, $woo))->toBe(2);
        }
    } finally {
        \Illuminate\Support\Carbon::setTestNow();
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
        $homeCountry && shipsFromCountry($this->organisation, $homeCountry);
        $dispatcherCountry && shipsFromCountry($dispatcher, $dispatcherCountry);
    }
});

test('the nightly reconcile heals a product left selling stock from elsewhere, and queues nothing once none is left', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $products['at_zero']->updateQuietly(['available_quantity' => 9, 'available_quantity_elsewhere' => 9, 'status' => \App\Enums\Catalogue\Product\ProductStatusEnum::FOR_SALE]);

    $this->artisan('products:hydrate-stock-from-other-warehouses')->assertExitCode(0);
    $healed = $products['at_zero']->fresh();
    expect([$healed->available_quantity, $healed->available_quantity_elsewhere, $healed->status->value])->toBe([0, 0, 'out-of-stock']);

    $isHydrate = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::class);
    \Illuminate\Support\Facades\Bus::fake([$isHydrate]);
    expect(\App\Actions\Catalogue\Product\Hydrators\HydrateProductsStockFromOtherWarehouses::run())->toBe(0);
    \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isHydrate);
});

test('a malformed entry in a shop\'s list is skipped and never breaks a stock movement', function () {
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($this, 'none');
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings ?? [], ['stock_from_other_warehouses' => [
        ['safety_margin' => 1], ['organisation_id' => 'abc', 'safety_margin' => 1], 'junk', ['organisation_id' => $dispatcher->id], ['organisation_id' => (string) $dispatcher->id, 'safety_margin' => 0],
    ]])]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();

    try {
        expect($this->shop->fresh()->stockFromOtherWarehouses())->toBe([['organisation_id' => $dispatcher->id, 'safety_margin' => 0]]);

        $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['at_zero']->stock_id)->sole();
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($dispatcher->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $theirSko, location: $location, modelData: ['quantity' => 7, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
        expect((float) $theirSko->fresh()->quantity_in_locations)->toBe(7.0)
            ->and($products['at_zero']->fresh()->available_quantity)->toBe(7);
    } finally {
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
        \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['at_zero']->fresh());
    }
});

test('a closed shop counts no other warehouse, and a type or state change re-hydrates a shop that lists one', function () {
    [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, 'none');
    pooledInStockProduct($this, $products, $skos, $dispatcher, $channels);
    $isShopHydrate = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\HydrateProductsStockFromOtherWarehouses::class);
    $state         = $this->shop->fresh()->state;

    try {
        $this->shop->updateQuietly(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::CLOSED]);
        expect($this->shop->fresh()->stockFromOtherWarehouses())->toBe([])
            ->and(\App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['in_stock']->fresh())->fresh()->available_quantity_elsewhere)->toBe(0);
        $this->shop->updateQuietly(['state' => $state]);

        \Illuminate\Support\Facades\Bus::fake();
        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['type' => ShopTypeEnum::B2B]);
        expect(\Illuminate\Support\Facades\Bus::dispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isShopHydrate)->map(fn ($job) => $job->getParameters()[0]->id)->all())->toBe([$this->shop->id]);
    } finally {
        $this->shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING, 'state' => $state, 'settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
    }
});

test('a failing lookup of the shops counting another company never breaks the stock move around it, and a failing count elsewhere falls back to home', function () {
    [$products, $skos, $dispatcher, $channels] = otherWarehouseStockFixture($this, 'none');
    $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['pack']->stock_id)->sole();

    try {
        \Illuminate\Support\Facades\Cache::put(\App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::LISTING_SHOPS_CACHE_KEY, [$dispatcher->id => ['not-a-shop-id']], now()->addMinute());
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($dispatcher->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        DB::transaction(function () use ($theirSko, $location) {
            \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $theirSko, location: $location, modelData: ['quantity' => 7, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
            $theirSko->refresh()->updateQuietly(['note_to_pickers' => 'moved']);
        });
        expect((float) $theirSko->fresh()->quantity_in_locations)->toBe(7.0)
            ->and($theirSko->fresh()->note_to_pickers)->toBe('moved');
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();

        pooledInStockProduct($this, $products, $skos, $dispatcher, $channels);
        $this->mock(ResolveCrossDispatch::class)->shouldReceive('wholeProductsIn')->andThrow(new RuntimeException('broken'));
        $product = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['in_stock']->fresh())->fresh();
        expect([$product->available_quantity, $product->available_quantity_elsewhere])->toBe([2, 0]);
    } finally {
        app()->forgetInstance(ResolveCrossDispatch::class);
        $this->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($this->shop->fresh()->settings ?? [], 'stock_from_other_warehouses')]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
    }
});

/**
 * INI-485 step 7. The fixture shop selling the other company's stock (margin 0), its products hydrated, with the order settings given.
 *
 * @return array{0: array<string, Product>, 1: array<string, OrgStock>, 2: \App\Models\SysAdmin\Organisation, 3: \Closure}
 */
function shopRoutingToOtherWarehouse($test, array $orderSettings = []): array
{
    [$products, $skos, $dispatcher] = otherWarehouseStockFixture($test, 'none');
    $test->shop->updateQuietly(['settings' => array_merge($test->shop->fresh()->settings ?? [], [
        'stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 0]],
        'stock_elsewhere'             => $orderSettings,
    ])]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
    foreach (['in_stock', 'at_zero'] as $key) {
        $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos[$key]->stock_id)->sole();
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($dispatcher->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $theirSko, location: $location, modelData: ['quantity' => 1000, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
    }
    foreach ($products as $product) {
        \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($product->fresh());
    }
    expect($products['at_zero']->fresh()->available_quantity_elsewhere)->toBe(1000);

    $submit = function (array $lines, array $orderAttributes = []) use ($test): \App\Models\Ordering\Order {
        $order = StoreOrder::make()->action($test->customer, []);
        foreach ($lines as [$product, $quantity]) {
            StoreTransaction::make()->action($order, $product->historicAsset, ['quantity_ordered' => $quantity]);
        }
        $order = SubmitOrder::make()->action($order->refresh());
        if ($orderAttributes) {
            $order->updateQuietly($orderAttributes);
        }

        return $order->fresh();
    };

    return [$products, $skos, $dispatcher, $submit];
}

function forgetShopRoutingToOtherWarehouse($test): void
{
    $test->shop->updateQuietly(['settings' => \Illuminate\Support\Arr::except($test->shop->fresh()->settings ?? [], ['stock_from_other_warehouses', 'stock_elsewhere'])]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops::forgetShopsListing();
}

test('an order only another warehouse can ship goes there by itself, and one home covers stays home', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this);
    $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['at_zero']->stock_id)->sole();

    try {
        $order        = $submit([[$products['at_zero'], 3]]);
        $toBePicked   = fn () => \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::make()->getQuantityToBePicked($theirSko->id);
        $pickedBefore = $toBePicked();
        $deliveryNote = SendOrderToWarehouse::make()->action($order, []);

        expect($deliveryNote->isCrossDispatch())->toBeTrue()
            ->and($deliveryNote->organisation_id)->toBe($dispatcher->id)
            ->and($deliveryNote->deliveryNoteItems()->pluck('org_stock_id')->all())->toBe([$theirSko->id])
            ->and($toBePicked() - $pickedBefore)->toBe(3.0)
            ->and($order->fresh()->state)->toBe(OrderStateEnum::IN_WAREHOUSE);

        $homeOrder = $submit([[$products['in_stock'], 2]]);
        $homeNote  = SendOrderToWarehouse::make()->action($homeOrder, []);
        expect($homeNote->isCrossDispatch())->toBeFalse()
            ->and($homeNote->organisation_id)->toBe($this->organisation->id);
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('an order held for another warehouse waits submitted with its reason, and staff send it home or to that warehouse', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['only_elsewhere' => 'hold']);
    $staff = userWithOnlyPermissions($this->organisation, ["orders.{$this->shop->id}.edit"]);

    try {
        $order = $submit([[$products['at_zero'], 3]]);
        expect(SendOrderToWarehouse::make()->action($order, []))->toBeNull();
        $order = $order->fresh();
        expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
            ->and($order->deliveryNotes()->count())->toBe(0)
            ->and(SendOrderToWarehouse::heldReason($order->private_warehouse_note))->toContain($dispatcher->name)
            ->and(SendOrderToWarehouse::make()->blockedReason($order))->toContain($dispatcher->name);

        app()->setLocale('sk');
        try {
            expect(SendOrderToWarehouse::make()->blockedReason($order))->toBe("celú objednávku vie odoslať iba sklad {$dispatcher->name}, pošlite ju tam alebo do domovského skladu");
        } finally {
            app()->setLocale('en');
        }

        actingAsUserWithOnlyPermissions($staff, ["orders.{$this->shop->id}.edit"]);
        $this->patch(route('grp.models.order.state.in-other-warehouse', [$order->id, $dispatcher->id]))->assertSessionHasNoErrors();
        $order = $order->fresh();
        expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($order->deliveryNotes()->sole()->organisation_id)->toBe($dispatcher->id)
            ->and(SendOrderToWarehouse::heldReason($order->private_warehouse_note))->toBeNull()
            ->and(\Illuminate\Support\Arr::has($order->data, SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD))->toBeFalse();

        $second = $submit([[$products['at_zero'], 2]]);
        SendOrderToWarehouse::make()->action($second, []);
        $this->patch(route('grp.models.order.state.in-warehouse', [$second->id]), ['despite_hold' => true])->assertSessionHasNoErrors();
        $second = $second->fresh();
        expect($second->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($second->deliveryNotes()->sole()->organisation_id)->toBe($this->organisation->id);
    } finally {
        actingAs($this->user);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('an order no single warehouse can ship is held or sent home as the shop chooses', function () {
    foreach (['hold' => true, 'home' => false] as $mixed => $held) {
        [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['mixed' => $mixed]);
        OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['in_stock']->stock_id)->sole()->updateQuietly(['quantity_available' => 0]);

        try {
            $order        = $submit([[$products['in_stock'], 2], [$products['at_zero'], 3]]);
            $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
            $order        = $order->fresh();
            if ($held) {
                expect($deliveryNote)->toBeNull()
                    ->and($order->state)->toBe(OrderStateEnum::SUBMITTED)
                    ->and(SendOrderToWarehouse::heldReason($order->private_warehouse_note))->toContain('no single warehouse');
            } else {
                expect($deliveryNote->organisation_id)->toBe($this->organisation->id)
                    ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);
            }
        } finally {
            forgetShopRoutingToOtherWarehouse($this);
        }
    }
});

test('TikTok, pre-order and collection orders are never sent to another warehouse', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this);

    try {
        $tiktok = $submit([[$products['at_zero'], 1]], ['platform_id' => \App\Models\Dropshipping\Platform::where('type', PlatformTypeEnum::TIKTOK)->firstOrFail()->id]);
        $collection = $submit([[$products['at_zero'], 1]]);
        $collection->updateQuietly(['collection_address_id' => $collection->delivery_address_id]);
        $preOrdered = $submit([[$products['at_zero'], 1]]);
        PreOrder::create([
            'group_id'        => $preOrdered->group_id,
            'organisation_id' => $preOrdered->organisation_id,
            'shop_id'         => $preOrdered->shop_id,
            'customer_id'     => $preOrdered->customer_id,
            'order_id'        => $preOrdered->id,
            'state'           => PreOrderStateEnum::RELEASED,
            'is_trade'        => false,
            'upfront_amount'  => 0,
            'deferred_amount' => 0,
        ]);

        foreach ([$tiktok, $collection, $preOrdered] as $order) {
            $deliveryNote = SendOrderToWarehouse::make()->action($order->fresh(), []);
            expect($deliveryNote?->organisation_id)->toBe($this->organisation->id)
                ->and(SendOrderToWarehouse::heldReason($order->fresh()->private_warehouse_note))->toBeNull();
        }
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('a refused or failing send to another warehouse holds the order and never breaks the payment that sent it', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this);

    try {
        foreach ([\Illuminate\Validation\ValidationException::withMessages(['cross_dispatch' => ['reference taken']]), new RuntimeException('broken')] as $failure) {
            $this->mock(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class)->shouldReceive('handle')->andThrow($failure);

            $order = StoreOrder::make()->action($this->customer, []);
            StoreTransaction::make()->action($order, $products['at_zero']->historicAsset, ['quantity_ordered' => 2]);
            $order->refresh()->updateQuietly(['pay_status' => OrderPayStatusEnum::PAID]);
            $order = SubmitOrder::make()->action($order->refresh())->fresh();

            expect($order->state)->toBe(OrderStateEnum::SUBMITTED)
                ->and($order->deliveryNotes()->count())->toBe(0)
                ->and(SendOrderToWarehouse::heldReason($order->private_warehouse_note))->toContain($dispatcher->name);
            app()->forgetInstance(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class);
        }
    } finally {
        app()->forgetInstance(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('organisation admins choose how orders use other warehouses and whether their stock marks products new, shop admins cannot', function () {
    [$products, $skos, $dispatcher] = shopRoutingToOtherWarehouse($this);
    $update = fn (array $data) => $this->patch(route('grp.models.org.shop.update', [$this->organisation->id, $this->shop->id]), $data);

    try {
        actingAsUserWithOnlyPermissions(userWithOnlyPermissions($this->organisation, ['shop-admin.'.$this->shop->id]), ['shop-admin.'.$this->shop->id]);
        $update(['stock_elsewhere_mixed' => 'home'])->assertSessionHasErrors('stock_elsewhere_mixed');
        expect($this->shop->fresh()->stockElsewhereSetting('mixed'))->toBe('hold');

        actingAsUserWithOnlyPermissions(userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]), ['org-admin.'.$this->organisation->id]);
        $update(['stock_elsewhere_only_elsewhere' => 'elsewhere'])->assertSessionHasErrors('stock_elsewhere_only_elsewhere');
        foreach (['stock_elsewhere_only_elsewhere' => 'hold', 'stock_elsewhere_mixed' => 'home', 'stock_elsewhere_marks_new' => true] as $field => $value) {
            $update([$field => $value])->assertSessionHasNoErrors();
        }
        $shop = $this->shop->fresh();
        expect([$shop->stockElsewhereSetting('only_elsewhere'), $shop->stockElsewhereSetting('mixed'), $shop->stockElsewhereSetting('marks_new')])->toBe(['hold', 'home', true]);

        $products['at_zero']->fresh()->updateQuietly(['status' => \App\Enums\Catalogue\Product\ProductStatusEnum::COMING_SOON, 'has_live_webpage' => true, 'available_quantity' => 0, 'available_quantity_elsewhere' => 0, 'first_in_stock_at' => null, 'first_available_at' => null]);
        $product = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['at_zero']->fresh())->fresh();
        expect($product->available_quantity_elsewhere)->toBe(1000)
            ->and($product->first_in_stock_at)->not->toBeNull()
            ->and($product->first_available_at)->not->toBeNull();
    } finally {
        actingAs($this->user);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('staff send a held order only to a warehouse the shop counts, of an order of a shop they edit', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['only_elsewhere' => 'hold']);
    $orgPartner = ResolveCrossDispatch::direction($this->organisation->id, $dispatcher->id);
    $staff      = userWithOnlyPermissions($this->organisation, ["orders.{$this->shop->id}.edit"]);
    $send       = fn ($order, $organisation) => $this->patch(route('grp.models.order.state.in-other-warehouse', [$order->id, $organisation->id]));
    $paid       = ['pay_status' => OrderPayStatusEnum::PAID];

    try {
        $order = $submit([[$products['at_zero'], 3]], $paid);
        SendOrderToWarehouse::make()->action($order, []);
        $notHeld = $submit([[$products['in_stock'], 1]]);
        actingAsUserWithOnlyPermissions($staff, ["orders.{$this->shop->id}.edit"]);

        $send($order, $this->organisation)->assertSessionHasErrors('cross_dispatch');
        $send($notHeld, $dispatcher)->assertSessionHasErrors('cross_dispatch');
        $order->updateQuietly(['data' => array_merge($order->fresh()->data ?? [], ['do_not_pick' => true])]);
        $send($order, $dispatcher)->assertSessionHasErrors('cross_dispatch');
        $order->updateQuietly(['data' => array_merge($order->fresh()->data ?? [], ['do_not_pick' => false])]);

        $otherShop  = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, array_merge(\App\Models\Catalogue\Shop::factory()->definition(), ['type' => ShopTypeEnum::DROPSHIPPING]));
        $otherOrder = StoreOrder::make()->action(createCustomer($otherShop), []);
        $otherOrder->updateQuietly(['state' => OrderStateEnum::SUBMITTED]);
        $send($otherOrder, $dispatcher)->assertForbidden();
        expect($order->fresh()->deliveryNotes()->count())->toBe(0)
            ->and($notHeld->fresh()->deliveryNotes()->count())->toBe(0);

        $send($order, $dispatcher)->assertSessionHasNoErrors();
        expect($order->fresh()->deliveryNotes()->sole()->organisation_id)->toBe($dispatcher->id)
            ->and(DB::table('orders')->where('id', $order->id)->value('data'))->not->toBe('[]');

        $second = $submit([[$products['at_zero'], 2]], $paid);
        SendOrderToWarehouse::make()->action($second, []);
        expect($second->fresh()->state)->toBe(OrderStateEnum::SUBMITTED);
        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => false]);
        expect($second->fresh()->deliveryNotes()->sole()->organisation_id)->toBe($this->organisation->id);
        $send($second, $dispatcher)->assertSessionHasErrors('cross_dispatch');
    } finally {
        actingAs($this->user);
        UpdateCrossDispatchSettings::make()->action($orgPartner->refresh(), $dispatcher, ['enabled' => true]);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('a held order is re-routed once under its lock, only when paid, and loses its hold when sent back to the basket or cancelled', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['only_elsewhere' => 'hold']);
    $held = function (array $attributes = []) use ($submit, $products) {
        $order = $submit([[$products['at_zero'], 3]], $attributes);
        SendOrderToWarehouse::make()->action($order, []);

        return $order->fresh();
    };

    try {
        $unpaid = $held();
        $paid   = $held(['pay_status' => OrderPayStatusEnum::PAID]);
        $skos['at_zero']->fresh()->updateQuietly(['quantity_available' => 10]);

        \App\Actions\Ordering\Order\UpdateState\RerouteOrderHeldForOtherWarehouse::run($unpaid);
        \App\Actions\Ordering\Order\UpdateState\RerouteOrderHeldForOtherWarehouse::run($paid);
        \App\Actions\Ordering\Order\UpdateState\RerouteOrderHeldForOtherWarehouse::run($paid);
        expect($unpaid->fresh()->state)->toBe(OrderStateEnum::SUBMITTED)
            ->and($paid->fresh()->deliveryNotes()->count())->toBe(1)
            ->and($paid->fresh()->deliveryNotes()->sole()->organisation_id)->toBe($this->organisation->id);
        $skos['at_zero']->fresh()->updateQuietly(['quantity_available' => 0]);

        \App\Actions\Ordering\Order\UpdateState\SendOrderBackToBasket::make()->handle($unpaid->fresh());
        expect(\Illuminate\Support\Arr::has($unpaid->fresh()->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD))->toBeFalse();

        $toCancel = $held();
        \App\Actions\Ordering\Order\UpdateState\CancelOrder::make()->action($toCancel->fresh());
        expect($toCancel->fresh()->state)->toBe(OrderStateEnum::CANCELLED)
            ->and(\Illuminate\Support\Arr::has($toCancel->fresh()->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD))->toBeFalse();

        $waiting = $held(['pay_status' => OrderPayStatusEnum::PAID]);
        expect($waiting->state)->toBe(OrderStateEnum::SUBMITTED);
        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['stock_from_other_warehouses' => []]);
        $waiting = $waiting->fresh();
        expect($waiting->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($waiting->deliveryNotes()->sole()->organisation_id)->toBe($this->organisation->id);
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('a hold is never written over an order another send already took, and releasing one never leaves its data a list', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this);

    try {
        $order = $submit([[$products['at_zero'], 2]]);
        $this->mock(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class)->shouldReceive('handle')->andReturnUsing(function (\App\Models\Ordering\Order $order) {
            $order->updateQuietly(['state' => OrderStateEnum::IN_WAREHOUSE]);

            throw \Illuminate\Validation\ValidationException::withMessages(['cross_dispatch' => ['order state']]);
        });
        expect(SendOrderToWarehouse::make()->action($order, []))->toBeNull();
        $order = $order->fresh();
        expect(SendOrderToWarehouse::heldReason($order->private_warehouse_note))->toBeNull()
            ->and(\Illuminate\Support\Arr::has($order->data ?? [], SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD))->toBeFalse();
        app()->forgetInstance(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class);

        $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings, ['stock_elsewhere' => ['only_elsewhere' => 'hold']])]);
        $held = $submit([[$products['at_zero'], 2]]);
        $held->updateQuietly(['data' => []]);
        SendOrderToWarehouse::make()->action($held->fresh(), []);
        expect(DB::table('orders')->where('id', $held->id)->value('data'))->toContain(SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD);
        $this->patch(route('grp.models.order.state.in-warehouse', [$held->id]), ['despite_hold' => true])->assertSessionHasNoErrors();
        expect($held->fresh()->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and(DB::table('orders')->where('id', $held->id)->value('data'))->not->toBe('[]');
    } finally {
        app()->forgetInstance(\App\Actions\Dispatching\CrossDispatch\RaiseCrossDispatchDeliveryNote::class);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('a held order goes home when home stock arrives, and to the other warehouse once it can ship it whole', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['only_elsewhere' => 'hold']);
    $stockIn = function (OrgStock $sko, float $quantity): void {
        $location = \App\Actions\Inventory\Location\StoreLocation::make()->action($sko->organisation->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action(orgStock: $sko, location: $location, modelData: ['quantity' => $quantity, 'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);
    };

    try {
        $order = $submit([[$products['at_zero'], 3]], ['pay_status' => OrderPayStatusEnum::PAID]);
        SendOrderToWarehouse::make()->action($order, []);
        expect($order->fresh()->state)->toBe(OrderStateEnum::SUBMITTED);
        $stockIn($skos['at_zero']->fresh(), 5);
        $order = $order->fresh();
        expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($order->deliveryNotes()->sole()->organisation_id)->toBe($this->organisation->id);

        $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings, ['stock_elsewhere' => ['only_elsewhere' => 'send']])]);
        $theirSko = OrgStock::where('organisation_id', $dispatcher->id)->where('stock_id', $skos['pack']->stock_id)->sole();
        $skos['pack']->fresh()->updateQuietly(['quantity_available' => 0]);
        $stockIn($theirSko, 2);
        $product = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['six_per_sko']->fresh())->fresh();
        expect([$product->available_quantity, $product->available_quantity_elsewhere])->toBe([12, 12]);
        $short = $submit([[$products['six_per_sko'], 13]], ['pay_status' => OrderPayStatusEnum::PAID]);
        SendOrderToWarehouse::make()->action($short, []);
        expect($short->fresh()->state)->toBe(OrderStateEnum::SUBMITTED)
            ->and(SendOrderToWarehouse::heldReason($short->fresh()->private_warehouse_note))->toContain('no single warehouse');

        $stockIn($theirSko, 20);
        $short = $short->fresh();
        expect($short->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($short->deliveryNotes()->sole()->organisation_id)->toBe($dispatcher->id);
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('an order no other warehouse may take says why, and shop settings fall back to their defaults', function () {
    [$products, $skos, $dispatcher, $submit] = shopRoutingToOtherWarehouse($this);
    $isShopHydrate = fn ($job) => $job instanceof \Lorisleiva\Actions\Decorators\JobDecorator && $job->decorates(\App\Actions\Catalogue\Product\Hydrators\HydrateProductsStockFromOtherWarehouses::class);

    try {
        $order = $submit([[$products['at_zero'], 3]], ['to_be_paid_by' => OrderToBePaidByEnum::CASH_ON_DELIVERY]);
        expect(SendOrderToWarehouse::make()->action($order, []))->toBeNull()
            ->and(SendOrderToWarehouse::heldReason($order->fresh()->private_warehouse_note))->toContain('paid on delivery')
            ->and(collect(\App\Actions\Dispatching\CrossDispatch\RouteOrderToOtherWarehouse::make()->warehouseOptions($order->fresh()))->firstWhere('organisation.id', $dispatcher->id))->toMatchArray(['can_ship' => false, 'shortfall' => 'it is paid on delivery']);

        $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings, ['stock_elsewhere' => ['only_elsewhere' => '', 'mixed' => null, 'marks_new' => '']])]);
        $shop = $this->shop->fresh();
        expect([$shop->stockElsewhereSetting('only_elsewhere'), $shop->stockElsewhereSetting('mixed'), $shop->stockElsewhereSetting('marks_new')])->toBe(['send', 'hold', false]);

        \Illuminate\Support\Facades\Bus::fake([$isShopHydrate]);
        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($shop, ['stock_elsewhere_mixed' => 'home']);
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isShopHydrate);
        \App\Actions\Catalogue\Shop\UpdateShop::make()->action($this->shop->fresh(), ['stock_elsewhere_marks_new' => true]);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\BoundedUniqueUntilProcessingJobDecorator::class, $isShopHydrate);
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('the orders backlog counts an order held for another warehouse, paid or not, the same one the release job finds, and forgets it once sent', function () {
    [$products, , $dispatcher, $submit] = shopRoutingToOtherWarehouse($this, ['only_elsewhere' => 'hold']);
    $indexOrders    = \App\Actions\Ordering\Order\UI\IndexOrders::make();
    $heldInBacklog  = fn () => collect($indexOrders->backlogAttention($this->shop)['stages'])->sum('other_warehouse');
    $heldForRelease = fn () => (new ReflectionMethod(\App\Actions\Ordering\Order\UpdateState\RerouteOrderHeldForOtherWarehouse::class, 'heldOrders'))->invoke(null)->where('shop_id', $this->shop->id)->pluck('id')->all();
    $shopState      = $this->shop->fresh()->state;
    $this->shop->updateQuietly(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);
    $backlog = fn (string $tab, string $query = '') => $this->get(route('grp.org.shops.show.ordering.backlog', [$this->organisation->slug, $this->shop->slug, 'tab' => $tab]).$query)->assertOk()->viewData('page')['props'];

    try {
        \App\Models\Ordering\Order::where('shop_id', $this->shop->id)->whereNotNull('data->'.SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD)->get()->each(fn ($held) => SendOrderToWarehouse::forgetOtherWarehouseHold($held));
        $before = $heldInBacklog();
        $order  = $submit([[$products['at_zero'], 3]]);
        SendOrderToWarehouse::make()->action($order, []);
        $order = $order->fresh();
        $other = $submit([[$products['in_stock'], 1]]);
        \App\Models\Ordering\Order::where('id', $other->id)->update(['submitted_at' => now()->subYears(30)]);
        expect($order->pay_status)->toBe(\App\Enums\Ordering\Order\OrderPayStatusEnum::UNPAID)
            ->and($order->state)->toBe(OrderStateEnum::SUBMITTED);

        $unpaidStage = collect($indexOrders->backlogAttention($this->shop)['stages'])->firstWhere('tab', 'submitted_unpaid');
        expect($heldInBacklog() - $before)->toBe(1)
            ->and($unpaidStage)->toMatchArray(['stuck' => 0, 'waiting_stock' => 0, 'waiting_cs' => 0])
            ->and(collect($indexOrders->backlogAttention($this->shop)['oldest'])->pluck('reference'))->not->toContain($other->reference)
            ->and($heldForRelease())->toContain($order->id);

        $filtered = $backlog('submitted_unpaid', '&submitted_unpaid_elements[attention]=other_warehouse');
        expect(collect($filtered['submitted_unpaid']['data'])->pluck('reference')->all())->toBe([$order->reference])
            ->and($filtered['backlog_filters']['current']['attention'])->toBe('other_warehouse')
            ->and($filtered['backlog_filters']['counts']['attention']['other_warehouse'])->toBe(1);

        \App\Models\Ordering\Order::where('id', $order->id)->update(['pay_status' => \App\Enums\Ordering\Order\OrderPayStatusEnum::PAID]);
        $paid = $backlog('submitted_paid', '&submitted_paid_elements[attention]=other_warehouse');
        expect(collect($paid['submitted_paid']['data'])->pluck('reference')->all())->toBe([$order->reference])
            ->and(collect($paid['attention']['stages'])->firstWhere('tab', 'submitted_paid')['other_warehouse'])->toBe(1)
            ->and($heldInBacklog() - $before)->toBe(1);

        $this->patch(route('grp.models.order.state.in-other-warehouse', [$order->id, $dispatcher->id]))->assertSessionHasNoErrors();
        expect($heldInBacklog())->toBe($before)
            ->and($heldForRelease())->not->toContain($order->id);
    } finally {
        $this->shop->updateQuietly(['state' => $shopState]);
        forgetShopRoutingToOtherWarehouse($this);
    }
});

test('the staff product page lists what home and each listed warehouse could ship whole after its margin, and nothing while the shop lists none', function () {
    [$products, , $dispatcher] = shopRoutingToOtherWarehouse($this);
    $this->shop->updateQuietly(['settings' => array_merge($this->shop->fresh()->settings, ['stock_from_other_warehouses' => [['organisation_id' => $dispatcher->id, 'safety_margin' => 4]]])]);
    $staffPage = fn (Product $product) => $this->get(route('grp.org.shops.show.catalogue.products.all_products.show', [$this->organisation->slug, $this->shop->slug, $product->slug]))->assertOk()->viewData('page')['props']['showcase'];

    try {
        $product = \App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity::run($products['at_zero']->fresh())->fresh();

        expect($staffPage($product)['stock_by_warehouse'])->toBe([
            ['name' => $this->organisation->name, 'is_home' => true, 'quantity' => 0, 'safety_margin' => 0],
            ['name' => $dispatcher->name, 'is_home' => false, 'quantity' => 996, 'safety_margin' => 4],
        ])
            ->and($product->available_quantity)->toBe(996);

        forgetShopRoutingToOtherWarehouse($this);
        expect($staffPage($product->fresh())['stock_by_warehouse'])->toBe([]);
    } finally {
        forgetShopRoutingToOtherWarehouse($this);
    }
});
