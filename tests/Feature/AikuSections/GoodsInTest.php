<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2025
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\GoodsIn\Sowing\MigratePickingReturnsToSowing;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\Sowing;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use Config;

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
    $this->adminGuest = createAdminGuest($this->group);

    $this->warehouse = createWarehouse();

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);
    $this->order    = createOrder($this->customer, $this->product);

    if ($this->order->state == OrderStateEnum::CREATING) {
        $this->order = SubmitOrder::make()->action($this->order);
    }

    Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
    actingAs($this->adminGuest->getUser());
});

test('create sowing record directly', function () {

    $arrayData = [
        'reference'        => 'SOWING-TEST-01',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ];

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, $arrayData);
    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    // Create transaction
    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    expect($deliveryNoteItem)->toBeInstanceOf(DeliveryNoteItem::class);

    // Create location and location org stock
    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    // Create sowing record
    $sowing = StoreSowing::run(
        $deliveryNoteItem,
        $locationOrgStock,
        [
            'quantity'       => 5,
            'sower_user_id'  => $this->user->id,
        ]
    );

    expect($sowing)->toBeInstanceOf(Sowing::class)
        ->and($sowing->quantity)->toBe('5.000000')
        ->and($sowing->location_id)->toBe($location->id)
        ->and($sowing->org_stock_id)->toBe($orgStock->id)
        ->and($sowing->sower_user_id)->toBe($this->user->id)
        ->and($sowing->orgStockMovement)->not->toBeNull();

    return $sowing;
});

test('sowing is linked to delivery note item', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-TEST-02',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'      => 5,
        'sower_user_id' => $this->user->id,
    ]);

    $deliveryNoteItem->refresh();

    // Verify the relationship works
    expect($deliveryNoteItem->sowings()->count())->toBe(1)
        ->and($deliveryNoteItem->sowings->first()->quantity)->toBe('5.000000');
});

test('sowing is linked to delivery note', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-TEST-03',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'      => 5,
        'sower_user_id' => $this->user->id,
    ]);

    $deliveryNote->refresh();

    // Verify the relationship works through delivery note
    expect($deliveryNote->sowings()->count())->toBe(1);
});

test('sowing with original picking reference', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-TEST-04',
        'state'            => DeliveryNoteStateEnum::HANDLING,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    // First create a picking
    $picking = StorePicking::make()->action($deliveryNoteItem, $this->user, [
        'picker_user_id'        => $this->user->id,
        'location_org_stock_id' => $locationOrgStock->id,
        'quantity'              => 5,
    ]);

    expect($picking)->toBeInstanceOf(Picking::class);

    // Now create a sowing with reference to original picking
    $sowing = StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'            => 5,
        'sower_user_id'       => $this->user->id,
        'original_picking_id' => $picking->id,
    ]);

    expect($sowing)->toBeInstanceOf(Sowing::class)
        ->and($sowing->original_picking_id)->toBe($picking->id);
});


test('update sowing', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-UPDATE-TEST',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    // Create sowing
    $sowing = StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'      => 5,
        'sower_user_id' => $this->user->id,
    ]);

    // Update sowing
    $updatedSowing = \App\Actions\GoodsIn\Sowing\UpdateSowing::make()->action($sowing, [
        'quantity' => 10,
    ]);

    expect($updatedSowing)->toBeInstanceOf(Sowing::class)
        ->and($updatedSowing->quantity)->toBe('10.000000');
});

test('delete sowing', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-DELETE-TEST',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    // Create sowing
    $sowing = StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'      => 5,
        'sower_user_id' => $this->user->id,
    ]);

    $sowingId = $sowing->id;

    // Delete sowing
    $result = \App\Actions\GoodsIn\Sowing\DeleteSowing::make()->action($sowing);

    expect($result)->toBeTrue()
        ->and(Sowing::find($sowingId))->toBeNull();
});

test('assign sower to sowing', function () {

    $deliveryNote = StoreDeliveryNote::make()->action($this->order, [
        'reference'        => 'SOWING-ASSIGN-TEST',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $this->warehouse->id
    ]);

    // Create stock and org stock
    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    /** @var HistoricAsset $historicAsset */
    $historicAsset = HistoricAsset::first();
    $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, [
        'quantity_ordered' => 10,
        'net_amount'       => 100,
    ]);

    $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 10
    ]);

    $location         = StoreLocation::make()->action($this->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: [
            'quantity'   => 100,
            'type'       => LocationStockTypeEnum::PICKING,
            'fetched_at' => now(),
        ],
        strict: false
    );

    $sowing = StoreSowing::run($deliveryNoteItem, $locationOrgStock, [
        'quantity'      => 5,
        'sower_user_id' => null,
    ]);

    $assignedSowing = \App\Actions\GoodsIn\Sowing\AssignSowerToSowing::make()->action($sowing, [
        'sower_user_id' => $this->user->id,
    ]);

    expect($assignedSowing)->toBeInstanceOf(Sowing::class)
        ->and($assignedSowing->sower_user_id)->toBe($this->user->id);
});
