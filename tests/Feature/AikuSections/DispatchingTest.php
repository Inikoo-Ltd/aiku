<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 12:57:17 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\SetDeliveryNoteStateAsPacked;
use App\Actions\Dispatching\DeliveryNote\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipment\UpdateShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Shipper\UpdateShipper;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Dispatching\Shipment;
use App\Models\Goods\Stock;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Location;
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

    $this->group = $this->organisation->group;

    $this->warehouse = createWarehouse();

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);
    $this->order    = createOrder($this->customer, $this->product);

    if ($this->order->state == OrderStateEnum::CREATING) {
        $this->orde = SubmitOrder::make()->action($this->order);
    }

    $this->employee = StoreEmployee::make()->action($this->organisation, Employee::factory()->definition());
});

test('create shipper', function () {
    $arrayData = [
        'code' => 'ABC',
        'name' => 'ABC Shipping'
    ];

    $createdShipper = StoreShipper::make()->action($this->organisation, $arrayData);
    expect($createdShipper->code)->toBe($arrayData['code']);

    return $createdShipper;
});

test('update shipper', function ($createdShipper) {
    $arrayData = [
        'code' => 'DEF',
        'name' => 'DEF Movers'
    ];

    $updatedShipper = UpdateShipper::make()->action($createdShipper, $arrayData);

    expect($updatedShipper->code)->toBe($arrayData['code']);
})->depends('create shipper');


test('create delivery note', function () {
    $arrayData = [
        'reference'        => 'A123456',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ];

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, $arrayData);
    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->reference)->toBe($arrayData['reference']);


    return $deliveryNote;
});

test('update delivery note', function ($lastDeliveryNote) {
    $arrayData = [
        'reference' => 'A2321321',
        'state'     => DeliveryNoteStateEnum::HANDLING,
        'email'     => 'test@email.com',
        'phone'     => '+62081353890000',
        'date'      => date('Y-m-d')
    ];

    $updatedDeliveryNote = UpdateDeliveryNote::make()->action($lastDeliveryNote, $arrayData);

    expect($updatedDeliveryNote->reference)->toBe($arrayData['reference']);
})->depends('create delivery note');

test('create delivery note item', function (DeliveryNote $deliveryNote) {
    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::find(1);

    $stock       = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock    = StoreOrgStock::make()->action($this->organisation, $stock);
    $transaction = StoreTransaction::make()->action($this->order, $historicAsset, Transaction::factory()->definition());

    $deliveryNoteData = [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ];

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteData);

    expect($deliveryNoteItem->delivery_note_id)->toBe($deliveryNoteData['delivery_note_id']);

    return $deliveryNoteItem;
})->depends('create delivery note');


test('remove delivery note', function ($deliveryNote) {
    $success = DeleteDeliveryNote::make()->handle($deliveryNote, []);

    $this->assertModelExists($deliveryNote);

    return $success;
})->depends('create delivery note', 'create delivery note item');

test('create second delivery note', function () {
    $arrayData = [
        'reference'        => 'A234567',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ];

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, $arrayData);
    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->reference)->toBe($arrayData['reference']);


    return $deliveryNote;
});

test('create second delivery note item', function (DeliveryNote $deliveryNote) {
    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::find(1);

    $stock       = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock    = StoreOrgStock::make()->action($this->organisation, $stock);
    $transaction = StoreTransaction::make()->action($this->order, $historicAsset, Transaction::factory()->definition());

    $deliveryNoteData = [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ];

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteData);
    $deliveryNote->refresh();
    expect($deliveryNoteItem->delivery_note_id)->toBe($deliveryNoteData['delivery_note_id'])
        ->and($deliveryNote->deliveryNoteItems()->count())->toBe(1);

    return $deliveryNoteItem;
})->depends('create second delivery note');

test('create more delivery note item', function (DeliveryNote $deliveryNote) {
    /** @var HistoricAsset $historicAsset */
    $historicAsset2 = HistoricAsset::find(1);

    $stock       = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock    = StoreOrgStock::make()->action($this->organisation, $stock);
    $transaction = StoreTransaction::make()->action($this->order, $historicAsset2, Transaction::factory()->definition());

    $deliveryNoteData = [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 15
    ];

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteData);
    $deliveryNote->refresh();
    expect($deliveryNoteItem->delivery_note_id)->toBe($deliveryNoteData['delivery_note_id'])
        ->and($deliveryNote->deliveryNoteItems()->count())->toBe(2);

    return $deliveryNoteItem;
})->depends('create second delivery note');

test('update second delivery note state to in queue', function (DeliveryNote $deliveryNote) {
    $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $this->user);

    $deliveryNote->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->picker_user_id)->toBe($this->user->id)
        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::QUEUED);

    return $deliveryNote;
})->depends('create second delivery note');

test('update second delivery note state to handling', function (DeliveryNote $deliveryNote) {
    $deliveryNote = StartHandlingDeliveryNote::make()->action($deliveryNote, $this->user);

    $deliveryNote->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->picker_user_id)->toBe($this->user->id)
        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::HANDLING);

    return $deliveryNote;
})->depends('update second delivery note state to in queue');

test('store picking', function (DeliveryNote $deliveryNote) {
    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->first();
    $locationOrgStock = StoreLocationOrgStock::make()->action(orgStock: $deliveryNoteItem->orgStock, location: $location, modelData: [
        'quantity'   => 100,
        'type'       => LocationStockTypeEnum::PICKING,
        'fetched_at' => now(),
    ], strict: false);

    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);

    $picking = StorePicking::make()->action(
        $deliveryNoteItem,
        $this->user,
        [
            'picker_user_id'        => $this->user->id,
            'location_org_stock_id' => $locationOrgStock->id,
            'quantity'              => 5,

        ]
    );

    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity))->toBe(5)
        ->and(intval($picking->deliveryNoteItem->quantity_picked))->toBe(5)
        ->and($picking->deliveryNoteItem->is_handled)->toBeFalse()
        ->and($picking->location_id)->toBe($locationOrgStock->location_id);

    $picking->refresh();

    return $picking;
})->depends('update second delivery note state to in queue');

test('update picking', function (Picking $picking) {
    $picking = UpdatePicking::make()->action($picking, [
        'quantity' => 10
    ]);

    $picking->refresh();

    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity))->toBe(10)
        ->and(intval($picking->deliveryNoteItem->quantity_picked))->toBe(10)
        ->and($picking->deliveryNoteItem->is_handled)->toBeTrue();

    $picking->refresh();

    return $picking;
})->depends('store picking');

test('pack item', function (Picking $picking) {
    $deliveryNoteItem = $picking->deliveryNoteItem;

    $packing = StorePacking::make()->action($deliveryNoteItem, $this->user, [
    ]);

    $packing->refresh();

    expect($packing)->toBeInstanceOf(Packing::class)
        ->and(intval($packing->quantity))->toBe(10)
        ->and(intval($packing->deliveryNoteItem->quantity_packed))->toBe(10);

    $packing->refresh();

    return $packing;
})->depends('update picking');

test('store second picking', function (DeliveryNote $deliveryNote) {
    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->skip(1)->first();
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $deliveryNoteItem->orgStock,
        location: $location,
        modelData: [
            'quantity'   => 150,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);
    $picking = StorePicking::make()->action($deliveryNoteItem, $this->user, [
        'picker_user_id'        => $this->user->id,
        'location_org_stock_id' => $locationOrgStock->id,
        'quantity'              => 5,

    ]);

    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity))->toBe(5)
        ->and(intval($picking->deliveryNoteItem->quantity_picked))->toBe(5)
        ->and($picking->deliveryNoteItem->is_handled)->toBeFalse()
        ->and($picking->location_id)->toBe($locationOrgStock->location_id);

    $picking->refresh();

    return $picking;
})->depends('update second delivery note state to in queue');

test('set remaining quantity to not picked (2nd picking)', function (Picking $picking) {
    $deliveryNoteItem = $picking->deliveryNoteItem;
    $picking          = StoreNotPickPicking::make()->action($deliveryNoteItem, $this->user, [
    ]);

    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity))->toBe(10)
        ->and(intval($picking->deliveryNoteItem->quantity_picked))->toBe(5)
        ->and(intval($picking->deliveryNoteItem->quantity_not_picked))->toBe(10)
        ->and($picking->deliveryNoteItem->is_handled)->toBeTrue();

    $picking->refresh();

    return $picking;
})->depends('store second picking');

test('Set Delivery Note state to Packed', function (Picking $picking) {
    $deliveryNote     = $picking->deliveryNote;
    $deliveryNoteItem = $picking->deliveryNoteItem;

    $packedDeliveryNote = SetDeliveryNoteStateAsPacked::make()->action($deliveryNote, $this->user);

    $packedDeliveryNote->refresh();
    $deliveryNoteItem->refresh();

    expect($packedDeliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($packedDeliveryNote->state)->toBe(DeliveryNoteStateEnum::PACKED)
        ->and($deliveryNoteItem->state)->toBe(DeliveryNoteItemStateEnum::PACKED)
        ->and(intval($deliveryNoteItem->quantity_packed))->toBe(5);

    return $packedDeliveryNote;
})->depends('set remaining quantity to not picked (2nd picking)');

test('create shipment', function ($deliveryNote, $shipper) {
    $arrayData = [
        'tracking' => 'AAA'
    ];

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, $arrayData);
    expect($shipment)->toBeInstanceOf(Shipment::class)
        ->and($shipment->tracking)->toBe($arrayData['tracking']);

    return $shipment;
})->depends('create delivery note', 'create shipper');

test('update shipment', function ($lastShipment) {
    $arrayData = [
        'reference' => 'BBB'
    ];

    $shipment = UpdateShipment::make()->action($lastShipment, $arrayData);

    expect($shipment->reference)->toBe($arrayData['reference']);
})->depends('create shipment');
