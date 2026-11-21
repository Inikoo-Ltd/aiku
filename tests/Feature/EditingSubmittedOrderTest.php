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
