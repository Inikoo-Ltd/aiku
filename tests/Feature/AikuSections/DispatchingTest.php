<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 12:57:17 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNotePercentage;
use App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateShipments;
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
use App\Actions\Dispatching\PickingSession\StartPickPickingSession;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipment\UpdateShipment;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Dispatching\PickingSession\CalculatePickingSessionPicks;
use App\Actions\Dispatching\PickingSession\StorePickingSession;
use App\Actions\Dispatching\PickingSession\UpdatePickingSession;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Shipper\UpdateShipper;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Goods\Stock;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Location;
use App\Models\Inventory\PickingSession;
use App\Models\Ordering\Transaction;
use Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group      = $this->organisation->group;
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

    if (!isset($this->employee)) {
        $employeeData                  = Employee::factory()->definition();
        $employeeData['worker_number'] .= Str::random(6);

        $this->employee = StoreEmployee::make()->action($this->organisation, $employeeData);
    }


    Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
    actingAs($this->adminGuest->getUser());
});

test('create shipper', function () {
    $arrayData = [
        'code'     => 'ABC',
        'name'     => 'ABC Shipping',
        'trade_as' => 'abc'
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

test("UI Index dispatching delivery-notes", function () {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.org.warehouses.show.dispatching.delivery-notes", [
            $this->organisation->slug,
            $this->warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Dispatching/DeliveryNotes")
            ->where("title", 'Delivery notes')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                    ->where("title", "Delivery notes")
                    ->etc()
            )
            ->has("data");
    });
});

test("UI Index dispatching show delivery-notes", function (DeliveryNote $deliveryNote) {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.dispatching.delivery_notes.show", [
            $deliveryNote->organisation->slug,
            $deliveryNote->warehouse->slug,
            $deliveryNote->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($deliveryNote) {
        $page
            ->component("Org/Dispatching/DeliveryNote")
            ->where("title", 'delivery note')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                    ->where("title", $deliveryNote->reference)
                    ->where("model", 'Delivery Note')
                    ->etc()
            )
            ->has('delivery_note')
            ->has("timelines")
            ->has("box_stats")
            ->has("routes")
            ->has(DeliveryNoteTabsEnum::ITEMS->value)
            ->has("tabs");
    });
})->depends('create second delivery note');

test('UI get section route dispatching show', function () {
    $deliveryNote = DeliveryNote::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        'organisation' => $deliveryNote->organisation->slug,
        'warehouse'    => $deliveryNote->warehouse->slug,
        'deliveryNote' => $deliveryNote->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY_DISPATCHING->value)
        ->and($sectionScope->model_slug)->toBe($deliveryNote->warehouse->slug);
});

test('UI Create Replacement Delivery Note', function () {
    $this->withoutExceptionHandling();
    $deliveryNote = $this->order->deliveryNotes()->first();


    $response = get(
        route(
            'grp.org.shops.show.ordering.orders.show.replacement.create',
            [
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
                'order'        => $this->order->slug,
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($deliveryNote) {
        $page
            ->component('Org/Dispatching/CreateReplacement')
            ->where('title', __('Replacement'))
            ->has('breadcrumbs')
            ->has('pageHead', function (AssertableInertia $head) {
                $head->where('model', __('Replacement'))
                    ->has('title')
                    ->has('actions')
                    ->etc();
            })
            ->has('tabs', function (AssertableInertia $tabs) {
                $tabs->has('current')
                    ->has('navigation');
            })
            ->has('delivery_note', function (AssertableInertia $dn) use ($deliveryNote) {
                $dn->where('id', $deliveryNote->id)->etc();
            })
            ->has('routes', function (AssertableInertia $routes) use ($deliveryNote) {
                $routes->where('update.name', 'grp.models.delivery_note.update')
                    ->where('update.parameters.deliveryNote', $deliveryNote->id)
                    ->etc();
            })
            ->has('address')
            ->has('box_stats')
            ->has('notes');
    });
});

test('store picking session', function () {
    $deliveryNote = $this->order->deliveryNotes()->first();
    if (!$deliveryNote) {
        $deliveryNote = SendOrderToWarehouse::make()->action($this->order, ['warehouse_id' => $this->warehouse->id]);
    }
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::UNASSIGNED]);

    $modelData = [
        'delivery_notes' => [$deliveryNote->id],
        'user_id'        => $this->user->id
    ];

    $pickingSession = StorePickingSession::make()->handle($this->warehouse, $modelData);

    expect($pickingSession)->toBeInstanceOf(PickingSession::class)
        ->and($pickingSession->warehouse_id)->toBe($this->warehouse->id)
        ->and($pickingSession->deliveryNotes->pluck('id'))->toContain($deliveryNote->id);

    $deliveryNote->refresh();
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::QUEUED);

    foreach ($deliveryNote->deliveryNoteItems as $item) {
        expect($item->picking_session_id)->toBe($pickingSession->id);
    }

    return $pickingSession;
});

test('update picking session', function () {
    $pickingSession = PickingSession::first();

    $updateData = [
        'state' => PickingSessionStateEnum::HANDLING
    ];

    $updatedPickingSession = UpdatePickingSession::make()->action($pickingSession, $updateData);

    expect($updatedPickingSession->state)->toBe(PickingSessionStateEnum::HANDLING);

    $updateData = [
        'state' => PickingSessionStateEnum::IN_PROCESS
    ];

    UpdatePickingSession::make()->action($pickingSession, $updateData);
    expect($updatedPickingSession->state)->toBe(PickingSessionStateEnum::IN_PROCESS);
});

test('start picking a picking session', function () {
    $pickingSession = PickingSession::first();

    $pickingSession = StartPickPickingSession::run($pickingSession, []);

    expect($pickingSession->state)->toBe(PickingSessionStateEnum::HANDLING);
});

test('picking session calculate picks', function (PickingSession $pickingSession) {
    $deliveryNote = $pickingSession->deliveryNotes()->first();
    if (!$deliveryNote->deliveryNoteItems()->exists()) {
        $historicAsset = HistoricAsset::find(1);
        $stock         = StoreStock::make()->action($this->group, Stock::factory()->definition());
        $stock         = UpdateStock::make()->action($stock, [
            'state' => StockStateEnum::ACTIVE,
        ]);
        $orgStock      = StoreOrgStock::make()->action($this->organisation, $stock);
        $transaction   = StoreTransaction::make()->action($this->order, $historicAsset, Transaction::factory()->definition());

        $deliveryNoteData = [
            'delivery_note_id'  => $deliveryNote->id,
            'org_stock_id'      => $orgStock->id,
            'transaction_id'    => $transaction->id,
            'quantity_required' => 10,
        ];

        StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteData);
    }
    CalculateDeliveryNotePercentage::make()->action($deliveryNote);

    $pickingSession->deliveryNotesItems()->update([
        'quantity_required' => 0,
        'quantity_picked'   => 0,
        'quantity_packed'   => 0,
    ]);

    $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->first();
    $deliveryNoteItem->update([
        'picking_session_id' => $pickingSession->id,
        'quantity_required'  => 10,
        'quantity_picked'    => 5,
        'quantity_packed'    => 2,
    ]);

    $pickingSession = CalculatePickingSessionPicks::make()->action($pickingSession);

    expect(floatval($pickingSession->quantity_picked))->toBe(5.0)
        ->and(floatval($pickingSession->quantity_packed))->toBe(2.0)
        ->and(floatval($pickingSession->picking_percentage))->toBe(50.0)
        ->and(floatval($pickingSession->packing_percentage))->toBe(40.0);
})->depends('store picking session');

test('auto finish packing picking session', function (PickingSession $pickingSession) {
    $deliveryNote = $pickingSession->deliveryNotes()->first();
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::PACKED]);

    $pickingSession->update(['number_delivery_notes' => 1]);

    $pickingSession = AutoFinishPackingPickingSession::run($pickingSession->fresh());

    expect($pickingSession->state)->toBe(PickingSessionStateEnum::PACKING_FINISHED)
        ->and($pickingSession->end_at)->not->toBeNull();
})->depends('store picking session');

test("UI Index dispatching picking sessions", function () {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.org.warehouses.show.dispatching.picking_sessions.index", [
            $this->organisation->slug,
            $this->warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/PickingSessions")
            ->where("title", 'Picking Sessions')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                    ->where("title", "Picking sessions")
                    ->etc()
            )
            ->has("data");
    });
});

test("UI Index dispatching show picking session", function (PickingSession $pickingSession) {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.dispatching.picking_sessions.show", [
            $this->organisation->slug,
            $this->warehouse->slug,
            $pickingSession->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($pickingSession) {
        $page
            ->component("Org/Inventory/PickingSession")
            ->where("title", 'Picking Session')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                    ->where("title", $pickingSession->reference)
                    ->where("model", 'Picking Session')
                    ->etc()
            )
            ->has('data')
            ->has("timelines")
            ->has("tabs");
    });
})->depends('store picking session');

it('can render the shippers index page', function () {
    get(route('grp.org.warehouses.show.dispatching.shippers.current.index', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $this->warehouse->slug,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Org/Dispatching/Shippers')
                ->has('data.data', 1)
        );
});

it('can render the inactive shippers index page', function () {
    $inactiveShipper = StoreShipper::make()->action(
        $this->organisation,
        [
            'code'     => fake()->lexify(),
            'name'     => fake()->name,
            'email'    => fake()->email,
            'trade_as' => fake()->company,
        ]
    );

    $inactiveShipper = UpdateShipper::make()->action($inactiveShipper, [
        'status' => false
    ]);

    expect($inactiveShipper)->toBeInstanceOf(Shipper::class)->and($inactiveShipper->status)->toBeFalse();

    get(route('grp.org.warehouses.show.dispatching.shippers.inactive.index', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $this->warehouse->slug,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Org/Dispatching/Shippers')
                ->has('data.data', 1)
                ->where('data.data.0.name', $inactiveShipper->name)
        );
});

it('can render the create shipper page', function () {
    get(route('grp.org.warehouses.show.dispatching.shippers.create', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $this->warehouse->slug,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('CreateModel')
                ->has('formData.blueprint')
        );
});

it('can render the show shipper page', function () {
    $shipper = Shipper::first();

    get(route('grp.org.warehouses.show.dispatching.shippers.show', [
        'organisation' => $shipper->organisation->slug,
        'warehouse'    => $this->warehouse->slug,
        'shipper'      => $shipper->slug,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Org/Dispatching/Shipper')
                ->where('pageHead.title', $shipper->name)
        );
});

it('can render the edit shipper page', function () {
    $shipper = Shipper::first();
    get(route('grp.org.warehouses.show.dispatching.shippers.edit', [
        'organisation' => $shipper->organisation->slug,
        'warehouse'    => $this->warehouse->slug,
        'shipper'      => $shipper->slug,
    ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('EditModel')
                ->where('formData.blueprint.0.fields.name.value', $shipper->name)
        );
});

it('can get shipper showcase data', function () {
    $shipper  = Shipper::first();
    $showcase = \App\Actions\Dispatching\Shipper\UI\GetShipperShowcase::run($shipper);

    expect($showcase)->toBeArray()
        ->toHaveKey('shipper')
        ->and($showcase['shipper']['name'])->toBe($shipper->name);
});

it('hydrates delivery note tracking number from shipments', function () {
    loadDB();
    Artisan::call('migrate');
    $createShopResult = createShop();
    $organisation     = $createShopResult[0];
    $shop             = $createShopResult[2];
    $warehouse        = createWarehouse();
    $customer         = createCustomer($shop);
    $product          = createProduct($shop)[1];
    $order            = createOrder($customer, $product);

    $arrayData = [
        'reference'        => 'A123456',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $warehouse->id
    ];

    $deliveryNote = StoreDeliveryNote::make()->action($order, $arrayData);

    $shipper = Shipper::create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'code'            => 'SHP1',
        'name'            => 'Shipper 1',
    ]);

    $shipment1 = Shipment::create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'shop_id'         => $shop->id,
        'shipper_id'      => $shipper->id,
        'tracking'        => 'TRK123',
    ]);

    $shipment2 = Shipment::create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'shop_id'         => $shop->id,
        'shipper_id'      => $shipper->id,
        'tracking'        => 'TRK456',
    ]);

    $deliveryNote->shipments()->attach($shipment1, ['model_type' => $deliveryNote->getMorphClass()]);
    $deliveryNote->shipments()->attach($shipment2, ['model_type' => $deliveryNote->getMorphClass()]);

    // Create a shipment with 'na' tracking (should be ignored)
    $shipmentNA = Shipment::create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'shop_id'         => $shop->id,
        'tracking'        => 'na',
    ]);
    $deliveryNote->shipments()->attach($shipmentNA, ['model_type' => $deliveryNote->getMorphClass()]);

    DeliveryNoteHydrateShipments::run($deliveryNote->id);

    $deliveryNote->refresh();

    expect($deliveryNote->tracking_number)->toBe('TRK123, TRK456')->and($deliveryNote->shipping_data)->toBeArray()->toHaveCount(2);
});

it('nullifies delivery note tracking number when no shipments exist', function () {
    loadDB();
    Artisan::call('migrate');
    $createShopResult = createShop();
    $shop             = $createShopResult[2];
    $warehouse        = createWarehouse();
    $customer         = createCustomer($shop);
    $product          = createProduct($shop)[1];
    $order            = createOrder($customer, $product);

    $arrayData = [
        'reference'        => 'A123457',
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $warehouse->id
    ];

    $deliveryNote = StoreDeliveryNote::make()->action($order, $arrayData);
    $deliveryNote->update(['tracking_number' => 'OLD_TRK']);

    DeliveryNoteHydrateShipments::run($deliveryNote->id);

    $deliveryNote->refresh();

    expect($deliveryNote->tracking_number)->toBeNull();
});
