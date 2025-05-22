<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 12:57:17 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Dispatching\Picking\UpdatePickingStateToDone;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipment\UpdateShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Shipper\UpdateShipper;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
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

test('update second delivery note state to in queue', function (DeliveryNote $deliveryNote) {
    $employee = StoreEmployee::make()->action($this->organisation, Employee::factory()->definition());

    $deliveryNote = UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $employee);

    $deliveryNote->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->picker_id)->toBe($employee->id)
        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::QUEUED);

    return $deliveryNote;
})->depends('create second delivery note');

test('store picking', function (DeliveryNote $deliveryNote) {
    $location = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());

    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->first();
    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class); 

    $picking = StorePicking::make()->action($deliveryNoteItem, [
        'picker_id' => $this->user->id,
        'location_id' => $location->id
    ]);
    
    expect($picking)->toBeInstanceOf(Picking::class);

    $picking->refresh();

    return $picking;

})->depends('update second delivery note state to in queue');

test('update picking', function (Picking $picking) {

    //HALF
    $picking = UpdatePicking::make()->action($picking, [
        'quantity_picked' => 5
    ]);

    $picking->refresh();
    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity_picked))->toBe(5);
    expect(intval($picking->deliveryNoteItem->quantity_picked))->toBe(5);
    
    //ALL
    $picking = UpdatePicking::make()->action($picking, [
        'quantity_picked' => 10
    ]);
    
    $picking->refresh();
    expect($picking)->toBeInstanceOf(Picking::class)
        ->and(intval($picking->quantity_picked))->toBe(10);
    expect(intval($picking->deliveryNoteItem->quantity_picked))->toBe(10);
    
    $picking->refresh();

    return $picking;

})->depends('store picking');

test('mark picking as done', function (Picking $picking) {

    $picking = UpdatePickingStateToDone::make()->action($picking);

    expect($picking)->toBeInstanceOf(Picking::class)
        ->and($picking->state)->toBe(PickingStateEnum::DONE);

    return $picking;

})->depends('update picking');

//test('assign picker to picking', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->first();
//    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);
//
//    $picking = $deliveryNoteItem->pickings;
//    expect($picking)->toBeInstanceOf(Picking::class);
//
//    $assignedPicking = AssignPickerToPicking::make()->action($picking, [
//        'picker_id' => $this->employee->id
//    ]);
//
//    expect($assignedPicking)->toBeInstanceOf(Picking::class)
//        ->and($assignedPicking->picker)->not->toBeNull();
//
//    $deliveryNote->refresh();
//
//    return $deliveryNote;
//})->depends('update second delivery note item state to in queue');
//
//test('update delivery note state to picker assigned', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNote = UpdateDeliveryNoteStateToPickerAssigned::make()->action($deliveryNote);
//
//    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
//        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PICKER_ASSIGNED);
//
//    $deliveryNote->refresh();
//
//    return $deliveryNote;
//})->depends('assign picker to picking');
//
//test('update delivery note and picking state to picking', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->first();
//    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);
//
//    $picking = $deliveryNoteItem->pickings;
//    expect($picking)->toBeInstanceOf(Picking::class);
//
//    $picking = UpdatePickingStateToPicking::make()->action($picking, [
//        'quantity_picked' => 10
//    ]);
//
//    expect($picking->state)->toBe(PickingStateEnum::PICKING)
//        ->and($picking->quantity_picked)->toBe(10);
//
//    $deliveryNote = UpdateDeliveryNoteStateToPicking::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PICKING);
//
//    $deliveryNote->refresh();
//
//    return $picking;
//})->depends('update delivery note state to picker assigned');
//
//test('update picking state to queried', function (Picking $picking) {
//
//    $picking = UpdatePickingStateToQueried::make()->action($picking);
//
//    expect($picking)->toBeInstanceOf(Picking::class)
//        ->and($picking->state)->toBe(PickingStateEnum::QUERIED);
//
//    $picking->refresh();
//
//    return $picking;
//})->depends('update delivery note and picking state to picking');
//
//test('update picking state to waiting', function (Picking $picking) {
//
//    $picking = UpdatePickingStateToWaiting::make()->action($picking);
//
//    expect($picking)->toBeInstanceOf(Picking::class)
//        ->and($picking->state)->toBe(PickingStateEnum::WAITING);
//
//    $picking->refresh();
//
//    return $picking;
//})->depends('update picking state to queried');
//
//test('update delivery note and picking state to picked', function (Picking $picking) {
//
//    $deliveryNote = $picking->deliveryNoteItem->deliveryNote;
//    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class);
//
//    $picking = UpdatePickingStateToPicked::make()->action($picking);
//
//    expect($picking->state)->toBe(PickingStateEnum::PICKED);
//
//    $deliveryNote = UpdateDeliveryNoteStateToPicked::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PICKED);
//
//    $deliveryNote->refresh();
//
//    return $picking;
//})->depends('update picking state to waiting');
//
//test('assign packer to picking', function (Picking $picking) {
//
//    $assignedPicking = AssignPackerToPicking::make()->action($picking, [
//        'packer_id' => $this->employee->id
//    ]);
//
//    expect($assignedPicking)->toBeInstanceOf(Picking::class)
//        ->and($assignedPicking->packer)->not->toBeNull();
//
//    $picking->refresh();
//
//    return $picking;
//})->depends('update delivery note and picking state to picked');
//
//test('update delivery note and picking state to packing', function (Picking $picking) {
//
//    $deliveryNote = $picking->deliveryNoteItem->deliveryNote;
//    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class);
//
//    $deliveryNote = UpdateDeliveryNoteStateToPacking::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PACKING)
//        ->and($deliveryNote->deliveryNoteItems->first()->pickings->state)->toBe(PickingStateEnum::PACKING);
//
//    $deliveryNote->refresh();
//
//    return $deliveryNote;
//})->depends('assign packer to picking');
//
//test('update picking state to done', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNoteItem = $deliveryNote->deliveryNoteItems->first();
//    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);
//
//    $picking = $deliveryNoteItem->pickings;
//    expect($picking)->toBeInstanceOf(Picking::class);
//
//    $picking = UpdatePickingStateToDone::make()->action($picking);
//    expect($picking->state)->toBe(PickingStateEnum::DONE);
//
//    return $picking;
//})->depends('update delivery note and picking state to packing');
//
//test('update delivery note state to packed', function (Picking $picking) {
//
//    $deliveryNote = $picking->deliveryNoteItem->deliveryNote;
//    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class);
//
//    $deliveryNote = SetDeliveryNoteStateAsPacked::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PACKED);
//
//    return $deliveryNote;
//})->depends('update picking state to done');
//
//test('update delivery note state to finalised', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNote = FinaliseDeliveryNote::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::FINALISED);
//
//    return $deliveryNote;
//})->depends('update delivery note state to packed');
//
//test('update delivery note state to settled', function (DeliveryNote $deliveryNote) {
//
//    $deliveryNote = UpdateDeliveryNoteStateToSettled::make()->action($deliveryNote);
//    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::DISPATCHED);
//
//    return $deliveryNote;
//})->depends('update delivery note state to finalised');


test('create shipment', function ($deliveryNote, $shipper) {
    $arrayData              = [
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
