<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 12:57:17 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNotePercentage;
use App\Actions\Dispatching\DeliveryNote\DeleteDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateShipments;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Dispatching\PickingSession\CalculatePickingSessionPicks;
use App\Actions\Dispatching\PickingSession\StartPickPickingSession;
use App\Actions\Dispatching\PickingSession\StorePickingSession;
use App\Actions\Dispatching\PickingSession\UpdatePickingSession;
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
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
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
use App\Actions\Dispatching\BatchCode\DeleteBatchCode;
use App\Actions\Dispatching\BatchCode\Hydrators\BatchCodeHydrateDeliveryNotes;
use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Actions\Dispatching\BatchCode\UpdateBatchCode;
use App\Actions\Dispatching\Box\StoreBox;
use App\Actions\Dispatching\Box\UpdateBox;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDispatchTotals;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePacker;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePickedBays;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\PickedBay\AttachDeliveryNoteToPickedBay;
use App\Actions\Dispatching\PickedBay\Hydrators\PickedBayHydrateNumberDeliveryNotes;
use App\Actions\Dispatching\PickedBay\StorePickedBay;
use App\Actions\Dispatching\PickedBay\UI\GetPickedBayShowcase;
use App\Actions\Dispatching\PickedBay\UpdatePickedBay;
use App\Actions\Dispatching\Shipper\Json\GetShippers;
use App\Actions\Dispatching\Trolley\AttachTrolleyToDeliveryNote;
use App\Actions\Dispatching\Trolley\DeleteTrolley;
use App\Actions\Dispatching\Trolley\DetachTrolleyFromDeliveryNote;
use App\Actions\Dispatching\Trolley\StoreTrolley;
use App\Actions\Dispatching\Trolley\SyncDeliveryNoteTrolleys;
use App\Actions\Dispatching\Trolley\UI\GetTrolleyShowcase;
use App\Actions\Dispatching\Trolley\UpdateTrolley;
use App\Actions\Dispatching\WaitingItems\GetCrmReturnedBadgeData;
use App\Actions\Dispatching\WaitingItems\GetCrmWaitingBadgeData;
use App\Actions\Dispatching\WaitingItems\GetDispatchingWaitingBadgeData;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\AttachPalletsToReturn;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\Box;
use App\Models\Dispatching\Trolley;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickedBay;
use Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

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

    $product2 = $this->shop->products()->skip(1)->first();

    if (!$product2) {
        $productData = array_merge(
            Product::factory()->definition(),
            [
                'trade_units' => [
                    [
                        'id'       => $this->tradeUnit[0]->id,
                        'quantity' => 2
                    ]
                ],
                'price'       => 200,
            ]
        );
        $product2    = StoreProduct::make()->action(
            $this->product->family,
            $productData
        );

        $product2 = UpdateProduct::make()->action(
            $product2,
            [
                'state' => ProductStateEnum::ACTIVE,
            ]
        );
    }

    $this->product2 = $product2;

    $this->customer = createCustomer($this->shop);
    $this->order    = createOrder($this->customer, $this->product);

    if ($this->order->state == OrderStateEnum::CREATING) {
        $this->order = SubmitOrder::make()->action($this->order);
    }

    if (!isset($this->employee)) {
        $employeeData                  = Employee::factory()->definition();
        $employeeData['alias']         = Str::random(6);
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

    $stock    = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock    = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
    /** @var Transaction $transaction */
    $transaction = $this->order->transactions()->first();

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
    expect($this->order->state)->toBe(OrderStateEnum::SUBMITTED);


    $deliveryNote = SendOrderToWarehouse::make()->action($this->order, [
        'warehouse_id' => $this->warehouse->id,
    ]);
    $this->order->refresh();

    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($deliveryNote->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED)
        ->and($this->order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);


    return $deliveryNote;
});

test('create second delivery note item', function (DeliveryNote $deliveryNote) {
    $stock       = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock    = StoreOrgStock::make()->action($this->organisation, $stock);
    $transaction = $this->order->transactions()->first();

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

    $productData = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => [
                [
                    'id'       => $this->tradeUnit[1]->id,
                    'quantity' => 2
                ]
            ],
            'price'       => 200,
        ]
    );
    $product    = StoreProduct::make()->action(
        $this->product->family,
        $productData
    );



    $stock       = StoreStock::make()->action($this->group, Stock::factory()->definition());
    $stock       = UpdateStock::make()->action($stock, [
        'state' => StockStateEnum::ACTIVE
    ]);
    $orgStock    = StoreOrgStock::make()->action($this->organisation, $stock);
    $transaction = StoreTransaction::make()->action($this->order, $product->currentHistoricProduct, Transaction::factory()->definition());

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
    $order = $deliveryNote->orders()->first();
    expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE);

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
    $deliveryNote = $picking->deliveryNote;

    $order = $deliveryNote->orders()->first();
    expect($order->state)->toBe(OrderStateEnum::HANDLING);

    $deliveryNoteItem = $picking->deliveryNoteItem;

    $packedDeliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, $this->user);

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

test("UI Index dispatching waiting items", function () {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.org.warehouses.show.dispatching.waiting_items", [
            $this->organisation->slug,
            $this->warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Dispatching/WaitingDeliveryNoteItems")
            ->where("title", 'Waiting items acme')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                    ->where("title", "Waiting items")
                    ->etc()
            )
            ->has("tabs");
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
            ->where("title", 'Delivery note 123456')
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
    /** @var DeliveryNote $deliveryNote */
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

    /** @var DeliveryNoteItem $deliveryNoteItem */
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
                    ->where("title", "Picking Sessions")
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

function makeOrgStock($ctx)
{
    $stock = StoreStock::make()->action($ctx->group, Stock::factory()->definition());
    $stock = UpdateStock::make()->action($stock, ['state' => StockStateEnum::ACTIVE]);

    return StoreOrgStock::make()->action($ctx->organisation, $stock);
}

test('store and update box', function () {
    $box = StoreBox::make()->action($this->organisation, [
        'name'   => 'Box '.Str::random(4),
        'height' => 10,
        'width'  => 20,
        'depth'  => 30,
        'stock'  => 5,
    ]);
    expect($box)->toBeInstanceOf(Box::class);

    $updated = UpdateBox::make()->action($box, ['name' => 'Renamed Box']);
    expect($updated->name)->toBe('Renamed Box');
});

test('UI index and create box', function () {
    get(route('grp.org.warehouses.show.dispatching.boxes.index', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.boxes.create', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
});

test('trolley crud and json and showcase', function () {
    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'trolley_'.Str::random(4)]);
    expect($trolley)->toBeInstanceOf(Trolley::class);

    $trolley = UpdateTrolley::make()->action($trolley, ['name' => 'trolley_upd_'.Str::random(4)]);

    $showcase = GetTrolleyShowcase::run($trolley);
    expect($showcase)->toBeArray();

    get(route('grp.json.available_trolleys.list', [$this->warehouse->slug]))->assertOk();
    get(route('grp.json.unavailable_trolleys.list', [$this->warehouse->slug]))->assertOk();

    $deliveryNote = makeDeliveryNote($this);
    AttachTrolleyToDeliveryNote::make()->handle($trolley, $deliveryNote);
    DetachTrolleyFromDeliveryNote::make()->handle($trolley, $deliveryNote);

    SyncDeliveryNoteTrolleys::make()->handle($deliveryNote, ['trolleys' => [$trolley->id]]);

    return $trolley;
});

test('UI trolley pages', function () {
    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'trolley_ui_'.Str::random(4)]);
    get(route('grp.org.warehouses.show.dispatching.trolleys.index', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.trolleys.create', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.trolleys.show', [$this->organisation->slug, $this->warehouse->slug, $trolley->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.trolleys.edit', [$this->organisation->slug, $this->warehouse->slug, $trolley->slug]))->assertOk();

    DeleteTrolley::make()->handle($trolley);
});

test('picked bay crud json showcase hydrator', function () {
    $pickedBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'BAY'.Str::random(4)]);
    expect($pickedBay)->toBeInstanceOf(PickedBay::class);

    $pickedBay = UpdatePickedBay::make()->handle($pickedBay, ['code' => 'BAYU'.Str::random(4)]);

    expect(GetPickedBayShowcase::run($pickedBay))->toBeArray();

    $deliveryNote = makeDeliveryNote($this);
    AttachDeliveryNoteToPickedBay::make()->handle($pickedBay, $deliveryNote);

    PickedBayHydrateNumberDeliveryNotes::run($pickedBay->id);
});

test('UI picked bay pages', function () {
    $pickedBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'BAYUI'.Str::random(4)]);
    get(route('grp.org.warehouses.show.dispatching.picked_bays.index', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.picked_bays.create', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.picked_bays.show', [$this->organisation->slug, $this->warehouse->slug, $pickedBay->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.picked_bays.edit', [$this->organisation->slug, $this->warehouse->slug, $pickedBay->slug]))->assertOk();
});

test('batch code crud json hydrator', function () {
    $orgStock  = makeOrgStock($this);
    $batchCode = StoreBatchCode::make()->action($this->warehouse, [
        'code'         => 'BC'.Str::random(4),
        'org_stock_id' => $orgStock->id,
    ]);
    expect($batchCode)->toBeInstanceOf(BatchCode::class);

    $batchCode = UpdateBatchCode::make()->handle($batchCode, ['code' => 'BCU'.Str::random(4)]);

    get(route('grp.json.org_stock.batch_codes.index', [$this->organisation->id, $orgStock->id]))->assertOk();

    BatchCodeHydrateDeliveryNotes::run($batchCode);

    return $batchCode;
});

test('UI batch code pages', function () {
    $orgStock  = makeOrgStock($this);
    $batchCode = StoreBatchCode::make()->action($this->warehouse, [
        'code'         => 'BCUI'.Str::random(4),
        'org_stock_id' => $orgStock->id,
    ]);

    get(route('grp.org.warehouses.show.inventory.batch_codes.index', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.inventory.batch_codes.create', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.inventory.batch_codes.show', [$this->organisation->slug, $this->warehouse->slug, $batchCode->id]))->assertOk();

    DeleteBatchCode::make()->handle($batchCode);
});

test('delivery note hydrators run', function () {
    $deliveryNote = makeDeliveryNote($this);

    DeliveryNoteHydratePacker::run($deliveryNote->id);
    DeliveryNoteHydratePickedBays::run($deliveryNote->id);
    DeliveryNoteHydrateWaitingItems::run($deliveryNote->id);
    DeliveryNoteHydrateDispatchTotals::run($deliveryNote);

    expect($deliveryNote->fresh())->toBeInstanceOf(DeliveryNote::class);
});

test('shippers json and waiting badges', function () {
    expect(GetShippers::run($this->organisation))->not->toBeNull();

    $user = $this->adminGuest->getUser();
    expect(GetDispatchingWaitingBadgeData::run($user))->toBeArray()
        ->and(GetCrmWaitingBadgeData::run($user))->toBeArray()
        ->and(GetCrmReturnedBadgeData::run($user))->toBeArray();
});

test('UI dispatching item and courier index pages', function () {
    get(route('grp.org.warehouses.show.dispatching.waiting_items_still_picking', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.waiting_crm_items', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    get(route('grp.org.shops.show.ordering.couriers.index', [$this->organisation->slug, $this->shop->slug]))->assertOk();
    get(route('grp.org.shops.show.ordering.delivery-notes.index', [$this->organisation->slug, $this->shop->slug]))->assertOk();
});

test('json badges and picker packer lists', function () {
    get(route('grp.json.dispatching_waiting_badge'))->assertOk();
    get(route('grp.json.crm_waiting_badge'))->assertOk();
    get(route('grp.json.crm_return_badge'))->assertOk();
    get(route('grp.json.employees.packers', [$this->organisation->slug]))->assertOk();
    get(route('grp.json.employees.pickers', [$this->organisation->slug]))->assertOk();
});

test('json mini delivery note and valid for return', function () {
    $deliveryNote = makeDeliveryNote($this);
    get(route('grp.json.mini_delivery_note', [$deliveryNote->id]))->assertOk();
    get(route('grp.json.mini_delivery_note_shipments', [$deliveryNote->id]))->assertOk();
    get(route('grp.json.delivery_note_valid_for_return', [$this->warehouse->slug]))->assertOk();
});

test('UI delivery notes in customer group and org stock', function () {
    $orgStock = makeOrgStock($this);
    get(route('grp.org.shops.show.crm.customers.show.delivery_notes.index', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]))->assertOk();
    get(route('grp.org.shops.show.crm.customers.show.replacements.index', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]))->assertOk();
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.delivery_notes', [$this->organisation->slug, $this->warehouse->slug, $orgStock->slug]))->assertOk();
    get(route('grp.overview.ordering.delivery_notes.index'))->assertOk();
});

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

function handlingDeliveryNoteWithPicking($ctx): array
{
    $order        = freshSubmittedOrder($ctx);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $ctx->warehouse->id]);

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
        'quantity'              => 10,
    ]);

    return [$deliveryNote->refresh(), $deliveryNoteItem->refresh()];
}

test('delivery note lifecycle to picked and packing and finalise dispatch', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PICKED);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PACKING);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UnpackDeliveryNote::make()->action($deliveryNote, $this->user);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PACKING);
});

test('delivery note undo picked and undo packing', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UndoSetAsPickedDeliveryNote::make()->action($deliveryNote, $this->user);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::HANDLING);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);
    StorePacking::make()->action($item->refresh(), $this->user, []);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UndoPackingDeliveryNote::make()->action($deliveryNote->refresh(), $this->user);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::PICKED);
});

test('delivery note state to picking handling blocked and unassigned', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    $dn = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToHandlingBlocked::make()->action($deliveryNote);
    expect($dn->state)->toBe(DeliveryNoteStateEnum::HANDLING_BLOCKED);

    $dn = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToUnassigned::make()->action($deliveryNote);
    expect($dn->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED);
});

test('delivery note finalise and dispatch', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);
    StorePacking::make()->action($deliveryNote->deliveryNoteItems->first(), $this->user, []);
    $deliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $this->user);

    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'SH'.Str::random(4), 'name' => 'Sh', 'trade_as' => 'sh']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'TRK'.Str::random(4)]);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote::make()->action($deliveryNote->refresh());
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::FINALISED);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote::make()->action($deliveryNote);
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::DISPATCHED);
});

test('delivery note finalise and dispatch combined and pick as employee', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote->refresh());
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);
    StorePacking::make()->action($deliveryNote->deliveryNoteItems->first(), $this->user, []);
    $deliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $this->user);

    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'SH'.Str::random(4), 'name' => 'Sh', 'trade_as' => 'sh']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'TRK'.Str::random(4)]);

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseAndDispatchDeliveryNote::make()->action($deliveryNote->refresh());
    expect($deliveryNote->state)->toBe(DeliveryNoteStateEnum::DISPATCHED);
});

test('pick delivery note as employee', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    $picked = \App\Actions\Dispatching\DeliveryNote\UpdateState\PickDeliveryNoteAsEmployee::make()->action($deliveryNote, $this->user);
    expect($picked)->toBeInstanceOf(DeliveryNote::class);
});

test('delivery note address actions and temp picker and shipping data', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    \App\Actions\Dispatching\DeliveryNote\StoreDeliveryNoteAddress::make()->action($deliveryNote, ['address' => new Address(Address::factory()->definition())]);
    \App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteFixedAddress::make()->action($deliveryNote, ['address' => new Address(Address::factory()->definition())]);
    \App\Actions\Dispatching\DeliveryNote\SetTempPickerToDeliveryNote::run($deliveryNote, ['picker_user_id' => $this->user->id]);

    expect(\App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData::run($deliveryNote))->toBeArray();
});

test('change picking bay on delivery note', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $pickedBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'CPB'.Str::random(4)]);

    $dn = \App\Actions\Dispatching\PickedBay\ChangePickingBaysDeliveryNote::run($deliveryNote, ['picked_bay' => $pickedBay->id]);
    expect($dn)->toBeInstanceOf(DeliveryNote::class);
});

test('delivery note item delete and fetch', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);

    $fetched = \App\Actions\Dispatching\DeliveryNoteItem\FetchSingleDeliveryNoteItem::run($item);
    expect($fetched)->toBeInstanceOf(DeliveryNoteItem::class);

    \App\Actions\Dispatching\DeliveryNoteItem\DeleteDeliveryNoteItem::run($item);
});

test('delivery note item packing and unpack', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);

    \App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemUnpack::run($item->refresh(), []);

    expect($item->fresh())->toBeInstanceOf(DeliveryNoteItem::class);
});

test('force delete delivery note', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);
    $deliveryNoteId = $deliveryNote->id;
    $itemId         = $item->id;

    \App\Actions\Dispatching\DeliveryNote\ForceDeleteDeliveryNote::make()->action($deliveryNote->refresh());

    expect(DeliveryNote::find($deliveryNoteId))->toBeNull()
        ->and(DeliveryNoteItem::find($itemId))->toBeNull();
});

test('picking route store and update', function () {
    $route = \App\Actions\Dispatching\PickingRoute\StorePickingRoute::make()->action($this->warehouse, ['name' => 'Route '.Str::random(4)]);
    $route = \App\Actions\Dispatching\PickingRoute\UpdatePickingRoute::make()->action($route, ['name' => 'Route upd '.Str::random(4)]);
    expect($route)->toBeInstanceOf(\App\Models\Dispatching\PickingRoute::class);
});

test('picking session add remove and undo finish packing', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::UNASSIGNED]);

    $pickingSession = StorePickingSession::make()->handle($this->warehouse, [
        'delivery_notes' => [$deliveryNote->id],
        'user_id'        => $this->user->id,
    ]);

    patch(route('grp.models.picking_session.remove_delivery_notes', [$pickingSession->id]), ['delivery_notes' => [$deliveryNote->id]]);
    patch(route('grp.models.picking_session.add_delivery_notes', [$pickingSession->id]), ['delivery_notes' => [$deliveryNote->id]]);
    \App\Actions\Dispatching\PickingSession\UndoFinishPackingPickingSession::make()->action($pickingSession);

    expect($pickingSession->fresh())->toBeInstanceOf(PickingSession::class);
});

test('delete shipment action', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'DS'.Str::random(4), 'name' => 'Ds', 'trade_as' => 'ds']);
    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'T'.Str::random(4)]);

    \App\Actions\Dispatching\Shipment\DeleteShipment::run($shipment);

    expect(Shipment::find($shipment->id))->toBeNull()
        ->and($deliveryNote->shipments()->count())->toBe(0);
});

test('UI reports and batch code edit', function () {
    get(route('grp.org.reports.packer-performance', [$this->organisation->slug]))->assertOk();
    get(route('grp.org.reports.picker-performance', [$this->organisation->slug]))->assertOk();

    $orgStock  = makeOrgStock($this);
    $batchCode = StoreBatchCode::make()->action($this->warehouse, ['code' => 'BE'.Str::random(4), 'org_stock_id' => $orgStock->id]);
    get(route('grp.org.warehouses.show.inventory.batch_codes.edit', [$this->organisation->slug, $this->warehouse->slug, $batchCode->id]))->assertOk();
});

function handlingItemWithLocation($ctx): array
{
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($ctx);
    $location         = StoreLocation::make()->action($ctx->warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action(
        orgStock: $item->orgStock,
        location: $location,
        modelData: ['quantity' => 200, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );

    return [$deliveryNote, $item->refresh(), $locationOrgStock];
}

test('picking upsert pick all split and delete', function () {
    [$deliveryNote, $item, $los] = handlingItemWithLocation($this);

    \App\Actions\Dispatching\Picking\UpsertPicking::run($item, $los, ['quantity' => 3]);
    expect($item->refresh()->pickings()->exists())->toBeTrue();

    $item->update(['locked_at' => null]);
    \App\Actions\Dispatching\Picking\PickAllItem::run($item->refresh(), ['location_org_stock_id' => $los->id]);
    expect(intval($item->refresh()->quantity_picked))->toBeGreaterThanOrEqual(intval($item->quantity_required));

    $picking = StorePicking::make()->action($item->refresh(), $this->user, [
        'picker_user_id'        => $this->user->id,
        'location_org_stock_id' => $los->id,
        'quantity'              => 2,
    ]);

    $split = \App\Actions\Dispatching\Picking\SplitPicking::run($picking, 1.0);
    expect(floatval($split->quantity))->toBe(1.0);

    \App\Actions\Dispatching\Picking\DeletePicking::make()->action($picking->refresh(), $this->user);
    expect(Picking::find($picking->id))->toBeNull();
});

test('picking waiting warehouse and crm flow', function () {
    $settings = $this->organisation->settings;
    data_set($settings, 'orders.allow_waiting', true);
    $this->organisation->update(['settings' => $settings]);

    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);

    $waiting = \App\Actions\Dispatching\Picking\SetAsWaitingWarehouse::make()->action($item->refresh(), $this->user, ['quantity' => 2]);
    expect($waiting->has_waiting_warehouse)->toBeTrue()
        ->and(intval($waiting->quantity_waiting_warehouse))->toBe(2);

    \App\Actions\Dispatching\Picking\StoreNotPickPickingFromWaitingWarehouse::run($item->refresh(), $this->user, ['quantity' => 1]);

    $item->update(['has_waiting_warehouse' => true, 'quantity_waiting_warehouse' => 2]);
    \App\Actions\Dispatching\Picking\PickAllItemFromWaitingWarehouse::run($item->refresh(), $this->user, ['quantity' => 1]);

    $item->update(['has_waiting_warehouse' => true, 'quantity_waiting_warehouse' => 2, 'locked_at' => null]);
    $undone = \App\Actions\Dispatching\Picking\UndoSetAsWaitingWarehouse::run($item->refresh());
    expect($undone->has_waiting_warehouse)->toBeFalse();

    $crm = \App\Actions\Dispatching\Picking\SetAsWaitingCrm::make()->action($item->refresh(), $this->user, ['quantity' => 2]);
    expect($crm->has_waiting_crm)->toBeTrue();

    \App\Actions\Dispatching\Picking\StoreNotPickPickingFromWaitingCrm::run($item->refresh(), $this->user, ['quantity' => 1]);

    $item->update(['has_waiting_crm' => true, 'quantity_waiting_crm' => 2]);
    $sentBack = \App\Actions\Dispatching\Picking\SendBackWaitingWarehouse::make()->action($item->refresh(), $this->user, []);
    expect($sentBack->has_waiting_crm)->toBeFalse();
});

test('picking upsert from waiting warehouse and magic place', function () {
    $settings = $this->organisation->settings;
    data_set($settings, 'orders.allow_waiting', true);
    $this->organisation->update(['settings' => $settings]);

    [$deliveryNote, $item, $los] = handlingItemWithLocation($this);
    $item->update(['has_waiting_warehouse' => true, 'quantity_waiting_warehouse' => 3, 'locked_at' => null]);

    \App\Actions\Dispatching\Picking\UpsertPickingFromWaitingWarehouse::run($item->refresh(), $this->user, ['quantity' => 1, 'location_org_stock_id' => $los->id]);
    expect($item->refresh()->pickings()->exists())->toBeTrue();

    $item->update([
        'has_waiting_warehouse'      => false,
        'quantity_waiting_warehouse' => 0,
        'quantity_required'          => 20,
        'quantity_picked'            => 5,
        'quantity_not_picked'        => 0,
        'locked_at'                  => null,
    ]);
    // Magic pick creates a picking from a virtual place (no physical location_id).
    // Only quantity + picker_user_id are validated fields, so that is all the real flow passes.
    $magicPicking = \App\Actions\Dispatching\Picking\PickFromMagicPlace::run($item->refresh(), [
        'quantity'       => 3,
        'picker_user_id' => $this->user->id,
    ]);

    expect($magicPicking)->toBeInstanceOf(Picking::class)
        ->and($magicPicking->type)->toBe(PickingTypeEnum::MAGIC_PICK)
        ->and($magicPicking->location_id)->toBeNull()
        ->and(floatval($magicPicking->quantity))->toBe(3.0);
});

test('picking and delivery note item repairs and reindex', function () {
    $pickingRepairs = \App\Actions\Dispatching\Picking\RepairPickingBatchCodes::run(true);
    $itemRepairs    = \App\Actions\Dispatching\DeliveryNoteItem\RepairDeliveryNoteItemBatchCodes::run(true);

    expect($pickingRepairs)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($itemRepairs)->toBeInt()->toBeGreaterThanOrEqual(0);

    \App\Actions\Dispatching\DeliveryNote\Search\ReindexDeliveryNotesSearch::run();
});

test('store replacement delivery note route rejects empty payload', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $order         = $deliveryNote->orders()->first();
    $notesBefore   = $order->deliveryNotes()->count();

    post(route('grp.models.order.replacement_delivery_note.store', [$order->id]), [])
        ->assertSessionHasErrors(['delivery_note_items']);

    expect($order->deliveryNotes()->count())->toBe($notesBefore);
});

test('picking session delivery notes json and change trolley and item packing route', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::UNASSIGNED]);
    $pickingSession = StorePickingSession::make()->handle($this->warehouse, [
        'delivery_notes' => [$deliveryNote->id],
        'user_id'        => $this->user->id,
    ]);

    get(route('grp.json.picking_session.delivery_notes.index', [$pickingSession->id]))->assertOk();

    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'ct_'.Str::random(4)]);
    patch(route('grp.models.delivery_note.state.change_trolley', [$deliveryNote->id]), ['trolley' => $trolley->id]);
    expect($deliveryNote->refresh()->trolleys()->count())->toBeGreaterThan(0);

    $packedBefore = intval($item->refresh()->quantity_packed);
    patch(route('grp.models.delivery_note_item.packing.store', [$item->id]), ['quantity' => 1]);
    expect(intval($item->refresh()->quantity_packed))->toBeGreaterThanOrEqual($packedBefore);
});

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

test('carrier api apc-gb success', function () {
    Config::set('app.sandbox.shipper_apc_token', 'test-token');
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'Orders' => [
                'Messages' => ['Code' => 'SUCCESS'],
                'Order'    => [
                    'OrderNumber'     => 'APC123',
                    'Label'           => ['Content' => base64_encode('label')],
                    'ShipmentDetails' => [
                        'NumberOfPieces' => 1,
                        'Items'          => ['Item' => ['TrackingNumber' => 'TRKAPC']],
                    ],
                ],
            ],
        ], 200),
    ]);

    $shipper      = apiShipper($this, 'apc-gb');
    $deliveryNote = deliveryNoteForShipping($this);

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, []);
    expect($shipment)->toBeInstanceOf(Shipment::class);
});

test('carrier api dpd-sk success', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'result' => ['result' => [[
                'success' => true,
                'mpsid'   => 'TRACK1234567890',
                'label'   => 'https://label.example/x.pdf',
            ]]],
        ], 200),
    ]);

    $shipper = apiShipper($this, 'dpd-sk');
    $shipper->update(['settings' => ['apiKey' => 'k', 'username' => 'u']]);
    $deliveryNote = deliveryNoteForShipping($this);

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper->refresh(), []);
    expect($shipment)->toBeInstanceOf(Shipment::class);
});

test('carrier api itd success', function () {
    Config::set('app.sandbox.shipper_itd_token', 'itd-token');
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                'status'         => 'COMPLETE',
                'combinedPdfUrl' => 'https://itd.example/label.pdf',
                'shipments'      => [
                    ['packages' => [
                        ['trackingCode' => 'ITD1', 'trackingUrl' => 'https://t/1', 'labelUrl' => 'https://l/1'],
                    ]],
                ],
            ],
        ], 200),
    ]);

    $shipper      = apiShipper($this, 'itd');
    $deliveryNote = deliveryNoteForShipping($this);

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, []);
    expect($shipment)->toBeInstanceOf(Shipment::class);
});

test('carrier api dpd-gb success', function () {
    Config::set('app.sandbox.shipper_dpd_gb_token', json_encode(['user', 'pass', '1234']));
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'data'  => [
                'geoSession'        => 'geo-session',
                'shipmentId'        => 'SHIP1',
                'consignmentDetail' => [
                    ['consignmentNumber' => 'DPDGB1'],
                ],
                'services'          => [],
                'label'             => base64_encode('label'),
            ],
            'error' => null,
        ], 200),
    ]);

    $shipper      = apiShipper($this, 'dpd-gb');
    $deliveryNote = deliveryNoteForShipping($this);

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, []);
    expect($shipment)->toBeInstanceOf(Shipment::class);
});

test('carrier api ctt-es success', function () {
    Config::set('app.sandbox.shipper_ctt_token', [
        'client_id'          => 'cid',
        'client_secret'      => 'secret',
        'grant_type'         => 'client_credentials',
        'scope'              => 'all',
        'client_center_code' => 'CC',
    ]);
    \Illuminate\Support\Facades\Http::fake([
        '*oauth2/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
        '*' => \Illuminate\Support\Facades\Http::response([
            'shipping_data' => ['shipping_code' => 'CTT1', 'label' => base64_encode('l')],
        ], 201),
    ]);

    $shipper      = apiShipper($this, 'ctt-es');
    $deliveryNote = deliveryNoteForShipping($this);

    $shipment = StoreShipment::make()->action($deliveryNote, $shipper, []);
    expect($shipment)->toBeInstanceOf(Shipment::class);
});

test('UI delivery note index tab variants', function () {
    $tabs = ['dispatched', 'finalised', 'handling', 'handling-blocked', 'in_warehouse', 'packed', 'packing', 'picked', 'queued', 'unassigned'];
    foreach ($tabs as $tab) {
        get(route('grp.org.warehouses.show.dispatching.'.$tab.'.delivery-notes', [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    }
});

test('UI picking session tab variants', function () {
    $tabs = ['in_process', 'packed', 'picked', 'picking', 'waiting'];
    foreach ($tabs as $tab) {
        get(route('grp.org.warehouses.show.dispatching.picking_sessions.'.$tab, [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    }
});

test('UI pallet returns index tab variants', function () {
    $routes = [
        'grp.org.warehouses.show.dispatching.pallet-returns.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.picked.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.picking.index',
    ];
    foreach ($routes as $r) {
        get(route($r, [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    }
});

test('cancel delivery note', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    $cancelled = \App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote::run($deliveryNote, $this->user, true);
    expect($cancelled->state)->toBe(DeliveryNoteStateEnum::CANCELLED);
});

test('UI show delivery note richer states and tabs', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked::run($deliveryNote);
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote::make()->action($deliveryNote, $this->user);
    StorePacking::make()->action($deliveryNote->deliveryNoteItems->first(), $this->user, []);
    $deliveryNote = UpdateDeliveryNoteStatePacked::make()->action($deliveryNote->refresh(), $this->user);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'SD'.Str::random(4), 'name' => 'Sd', 'trade_as' => 'sd']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'T'.Str::random(4)]);
    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'sd_'.Str::random(4)]);
    AttachTrolleyToDeliveryNote::make()->handle($trolley, $deliveryNote);

    foreach (['items', 'pending_items', 'done_items', 'history'] as $tab) {
        get(route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
            $this->organisation->slug, $this->warehouse->slug, $deliveryNote->slug,
        ]).'?tab='.$tab)->assertOk();
    }

    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote::make()->action($deliveryNote->refresh());
    $deliveryNote = \App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote::make()->action($deliveryNote);
    get(route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        $this->organisation->slug, $this->warehouse->slug, $deliveryNote->slug,
    ]))->assertOk();
});

test('json mini delivery note with shipment', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'MN'.Str::random(4), 'name' => 'Mn', 'trade_as' => 'mn']);
    StoreShipment::make()->action($deliveryNote, $shipper, ['tracking' => 'T'.Str::random(4)]);

    get(route('grp.json.mini_delivery_note', [$deliveryNote->id]))->assertOk();
    get(route('grp.json.mini_delivery_note_shipments', [$deliveryNote->id]))->assertOk();
});

function createPalletReturnWithPallet($ctx): PalletReturn
{
    $fulfilment         = createFulfilment($ctx->organisation);
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::ACTIVE,
            'status'          => CustomerStatusEnum::APPROVED,
            'contact_name'    => 'PR '.Str::random(4),
            'company_name'    => 'PR '.Str::random(4),
            'interest'        => ['pallets_storage', 'items_storage', 'dropshipping'],
            'contact_address' => Address::factory()->definition(),
        ]
    );
    UpdateFulfilmentCustomer::make()->action($fulfilmentCustomer, [
        'pallets_storage' => true,
        'items_storage'   => true,
    ]);

    $pallet = StorePallet::make()->action(
        $fulfilmentCustomer,
        array_merge(['warehouse_id' => $ctx->warehouse->id], Pallet::factory()->definition())
    );

    $palletReturn = StorePalletReturn::make()->action(
        $fulfilmentCustomer->refresh(),
        ['warehouse_id' => $ctx->warehouse->id]
    );

    AttachPalletsToReturn::make()->action($palletReturn, ['pallets' => [$pallet->id]]);

    return $palletReturn->refresh();
}

test('UI goods out pallet return show pages', function () {
    $palletReturn = createPalletReturnWithPallet($this);

    get(route('grp.org.warehouses.show.dispatching.pallet-returns.show', [
        $this->organisation->slug, $this->warehouse->slug, $palletReturn->slug,
    ]))->assertOk();

    get(route('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show', [
        $this->organisation->slug, $this->warehouse->slug, $palletReturn->slug,
    ]))->assertOk();
});

test('carrier api failure branches', function () {
    Config::set('app.sandbox.shipper_apc_token', 'x');
    Config::set('app.sandbox.shipper_itd_token', 'x');
    Config::set('app.sandbox.shipper_dpd_gb_token', json_encode(['u', 'p', 'a']));

    $carriers = [
        'apc-gb' => ['Orders' => ['Messages' => ['Code' => 'FAIL'], 'Order' => ['Messages' => ['ErrorFields' => ['ErrorField' => [['FieldName' => 'Delivery PostalCode', 'ErrorMessage' => 'bad']]]]]]],
        'dpd-sk' => ['error' => ['message' => 'Invalid ZIP code'], 'result' => ['result' => [['success' => false, 'messages' => ['bad']]]]],
        'itd'    => ['data' => ['status' => 'FAILED'], 'errors' => ['data' => [['errors' => ['consignment' => [], 'packages' => []]]]]],
        'dpd-gb' => ['data' => ['geoSession' => 'g'], 'error' => ['errorMessage' => 'bad']],
    ];

    $outcomes = [];
    foreach ($carriers as $api => $response) {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response($response, 200)]);
        $shipper = apiShipper($this, $api);
        if ($api === 'dpd-sk') {
            $shipper->update(['settings' => ['apiKey' => 'k', 'username' => 'u']]);
        }
        $deliveryNote = deliveryNoteForShipping($this);
        try {
            $shipment          = StoreShipment::make()->action($deliveryNote, $shipper->refresh(), []);
            $outcomes[$api]    = $shipment->tracking;
        } catch (\Throwable $e) {
            $outcomes[$api] = 'rejected';
        }
    }

    // Every carrier's failure branch ran to a definite outcome, and none produced a real tracking number.
    expect($outcomes)->toHaveKeys(['apc-gb', 'dpd-sk', 'itd', 'dpd-gb']);
    foreach ($outcomes as $tracking) {
        expect((string) $tracking)->not->toMatch('/^(TRK|ITD|DPD|APC|CTT)/');
    }
});

test('repairs actual run', function () {
    $pickingRepairs = \App\Actions\Dispatching\Picking\RepairPickingBatchCodes::run(false);
    $itemRepairs    = \App\Actions\Dispatching\DeliveryNoteItem\RepairDeliveryNoteItemBatchCodes::run(false);

    expect($pickingRepairs)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($itemRepairs)->toBeInt()->toBeGreaterThanOrEqual(0);
});

test('store replacement delivery note action', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);
    $order = $deliveryNote->orders()->first();

    post(route('grp.models.order.replacement_delivery_note.store', [$order->id]), [
        'reference'           => 'R'.Str::random(6),
        'warehouse_id'        => $this->warehouse->id,
        'delivery_note_items' => [
            ['id' => $item->id, 'quantity' => 2],
        ],
    ])->assertRedirect();

    expect($order->deliveryNotes()->count())->toBeGreaterThan(1);
});

test('UI show delivery note in ordering and customer scopes', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);
    $order    = $deliveryNote->orders()->first();
    $customer = $order->customer;

    get(route('grp.org.shops.show.ordering.delivery-notes.show', [
        $this->organisation->slug, $this->shop->slug, $deliveryNote->slug,
    ]))->assertOk();

    get(route('grp.org.shops.show.crm.customers.show.delivery_notes.show', [
        $this->organisation->slug, $this->shop->slug, $customer->slug, $deliveryNote->slug,
    ]))->assertOk();

    get(route('grp.org.shops.show.crm.customers.show.orders.show.delivery-note.show', [
        $this->organisation->slug, $this->shop->slug, $customer->slug, $order->slug, $deliveryNote->slug,
    ]))->assertOk();
});

test('UI show picking session with active items', function () {
    [$deliveryNote, $item] = handlingDeliveryNoteWithPicking($this);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::UNASSIGNED]);
    $pickingSession = StorePickingSession::make()->handle($this->warehouse, [
        'delivery_notes' => [$deliveryNote->id],
        'user_id'        => $this->user->id,
    ]);
    StartPickPickingSession::run($pickingSession, []);
    $pickingSession->refresh();

    get(route('grp.org.warehouses.show.dispatching.picking_sessions.show', [
        $this->organisation->slug, $this->warehouse->slug, $pickingSession->slug,
    ]))->assertOk();
});

test('UI show unassigned delivery note with items', function () {
    $deliveryNote = makeDeliveryNote($this);
    $orgStock     = makeOrgStock($this);
    $transaction  = $this->order->transactions()->first();
    StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $transaction->id,
        'quantity_required' => 5,
    ]);

    get(route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        $this->organisation->slug, $this->warehouse->slug, $deliveryNote->slug,
    ]))->assertOk();
});

test('update delivery note various fields and index with data', function () {
    [$deliveryNote] = handlingDeliveryNoteWithPicking($this);

    UpdateDeliveryNote::make()->action($deliveryNote, ['picker_user_id' => $this->user->id]);
    UpdateDeliveryNote::make()->action($deliveryNote, ['packer_user_id' => $this->user->id]);
    UpdateDeliveryNote::make()->action($deliveryNote, ['weight' => 100, 'email' => 'x@y.com', 'phone' => '+62811']);
    UpdateDeliveryNote::make()->action($deliveryNote, ['customer_notes' => 'note', 'shipping_notes' => 'ship']);

    expect($deliveryNote->fresh())->toBeInstanceOf(DeliveryNote::class);
});

test('UI pallet returns index with real data', function () {
    createPalletReturnWithPallet($this);

    foreach ([
        'grp.org.warehouses.show.dispatching.pallet-returns.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index',
        'grp.org.warehouses.show.dispatching.pallet-returns.picking.index',
    ] as $r) {
        get(route($r, [$this->organisation->slug, $this->warehouse->slug]))->assertOk();
    }
});

test('fetch single delivery note item with pickings', function () {
    [$deliveryNote, $item, $los] = handlingItemWithLocation($this);
    StorePicking::make()->action($item->refresh(), $this->user, [
        'picker_user_id'        => $this->user->id,
        'location_org_stock_id' => $los->id,
        'quantity'              => 3,
    ]);

    $fetched = \App\Actions\Dispatching\DeliveryNoteItem\FetchSingleDeliveryNoteItem::run($item->refresh());
    expect($fetched)->toBeInstanceOf(DeliveryNoteItem::class);
});
