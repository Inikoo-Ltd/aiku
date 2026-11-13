<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Goods\Stock;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Str;

function makeDeliveryNote($ctx): DeliveryNote
{
    return StoreDeliveryNote::make()->action($ctx->order, [
        'reference'        => 'DN'.Str::random(6),
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $ctx->warehouse->id,
    ]);
}

function freshSubmittedOrder($ctx)
{
    $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($ctx->customer, [
        'reference'        => 'O'.Str::random(8),
        'date'             => date('Y-m-d'),
        'customer_id'      => $ctx->customer->id,
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    StoreTransaction::make()->action($order, $ctx->product->historicAsset, Transaction::factory()->definition());

    return SubmitOrder::make()->action($order);
}

function giveParcelDimensions(DeliveryNote $deliveryNote): void
{
    $deliveryNote->update(['parcels' => [['weight' => 1, 'dimensions' => [30, 20, 10]]]]);
}

function handlingDeliveryNoteWithPicking($ctx, int $pickedQuantity = 10): array
{
    $order        = freshSubmittedOrder($ctx);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $ctx->warehouse->id]);
    $deliveryNote->deliveryNoteItems()->delete();

    $stock       = StoreStock::make()->action($ctx->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock    = StoreOrgStock::make()->action($ctx->organisation, $stock);
    $transaction = $order->transactions()->first();

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10,
    ]);

    $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $ctx->user);
    $deliveryNote = StartHandlingDeliveryNote::make()->action($deliveryNote, $ctx->user);

    $location         = StoreLocation::make()->action($ctx->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $deliveryNoteItem->orgStock,
        location: $location,
        modelData: ['quantity' => 100, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );

    StorePicking::make()->action($deliveryNoteItem, $ctx->user, [
        'picker_user_id'        => $ctx->user->id,
        'location_org_stock_id' => $locationOrgStock->id,
        'quantity'              => $pickedQuantity,
    ]);

    return [$deliveryNote->refresh(), $deliveryNoteItem->refresh()];
}

function finalisedDeliveryNote($ctx): array
{
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($ctx);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $ctx->user);
    giveParcelDimensions($item->deliveryNote);
    StorePacking::make()->action($item->refresh(), $ctx->user, []);
    $deliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $ctx->user);

    $shipper = StoreShipper::make()->action($ctx->organisation, ['code' => 'SH'.Str::random(4), 'name' => 'Sh', 'trade_as' => 'sh']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'TRK'.Str::random(4)]);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote::make()->action($deliveryNote->refresh());

    return [$deliveryNote->refresh(), $item->refresh()];
}

function apiShipper($ctx, string $apiShipper)
{
    return StoreShipper::make()->action($ctx->organisation, [
        'code'        => strtoupper(Str::random(5)),
        'name'        => $apiShipper.' carrier',
        'trade_as'    => $apiShipper,
        'api_shipper' => $apiShipper,
    ]);
}

function deliveryNoteForShipping($ctx): DeliveryNote
{
    $deliveryNote = makeDeliveryNote($ctx);
    $deliveryNote->update([
        'parcels' => [
            ['weight' => 2, 'dimensions' => [10, 20, 30]],
        ],
    ]);

    return $deliveryNote->refresh();
}
