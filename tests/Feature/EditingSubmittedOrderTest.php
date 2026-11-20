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
