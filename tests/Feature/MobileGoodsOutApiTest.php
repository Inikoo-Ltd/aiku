<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteHandler;
use App\Actions\Dispatching\PickedBay\AttachDeliveryNoteToPickedBay;
use App\Actions\Dispatching\PickedBay\StorePickedBay;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Trolley\StoreTrolley;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    $this->group            = $this->organisation->group;
    $this->customer         = createCustomer($this->shop);
    $this->order            = createOrder($this->customer, createProduct($this->shop)[1]);
    $this->warehouse        = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $this->originalSettings = $this->organisation->settings;
});

afterEach(function () {
    $this->organisation->update(['settings' => $this->originalSettings]);
});

test('mobile delivery notes list filters by bucket, search, date, picker and warehouse, and sorts and pages like the web', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague      = createMobileAppUser($this->group);

    $older     = goodsOutDeliveryNote($this->order, $this->warehouse, ['date' => '2026-01-03 10:00:00']);
    $newer     = goodsOutDeliveryNote($this->order, $this->warehouse, ['date' => '2026-01-05 10:00:00']);
    $mine      = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);
    $theirs    = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $colleague->id]);
    $nobodys   = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING]);
    $elsewhere = goodsOutDeliveryNote($this->order, $otherWarehouse);

    $response = getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id)
        ->assertOk()
        ->assertJsonPath('data.can_work_in_warehouse', true)
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('data.delivery_notes.0.reference', $older->reference)
        ->assertJsonPath('data.delivery_notes.0.state', 'unassigned')
        ->assertJsonPath('data.delivery_notes.0.state_label', 'To do')
        ->assertJsonPath('data.delivery_notes.0.shop.id', $this->shop->id)
        ->assertJsonPath('data.delivery_notes.0.shop.type', 'b2b')
        ->assertJsonPath('data.delivery_notes.0.channel', 'b2b')
        ->assertJsonPath('data.delivery_notes.0.picker', null);

    expect(array_keys($response->json('data.delivery_notes.0')))->toBe([
        'id', 'slug', 'reference', 'type', 'state', 'state_label', 'date', 'last_modified_at', 'customer_name', 'is_customer_vip',
        'shop', 'channel', 'number_items', 'estimated_weight_kg', 'parcels', 'is_premium_dispatch', 'is_priority', 'is_collection',
        'is_export', 'has_extra_packing', 'picker', 'packer', 'trolleys', 'picked_bays', 'waiting_warehouse_count', 'waiting_crm_count',
        'number_of_days_in_warehouse', 'can_add_to_picking_session',
    ])
        ->and(goodsOutReferences($this->warehouse, ['sort' => '-date']))->toBe([$newer->reference, $older->reference])
        ->and(goodsOutReferences($this->warehouse, ['search' => $newer->reference]))->toBe([$newer->reference])
        ->and(goodsOutReferences($this->warehouse, ['from' => '2026-01-04', 'to' => '2026-01-06']))->toBe([$newer->reference])
        ->and(goodsOutReferences($this->warehouse, ['to' => '2026-01-04']))->toBe([$older->reference])
        ->and(goodsOutReferences($this->warehouse, ['bucket' => 'all']))->not->toContain($elsewhere->reference)
        ->and(goodsOutReferences($this->warehouse, ['bucket' => 'handling', 'picker' => 'me']))->toBe([$mine->reference])
        ->and(goodsOutReferences($this->warehouse, ['bucket' => 'handling', 'picker' => 'others']))->toBe([$theirs->reference])
        ->and(goodsOutReferences($this->warehouse, ['bucket' => 'handling', 'picker' => 'none']))->toBe([$nobodys->reference])
        ->and(goodsOutReferences($this->warehouse, ['bucket' => 'in_warehouse']))->toHaveCount(5);

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['bucket' => 'handling', 'picker' => 'me'])
        ->assertJsonPath('data.delivery_notes.0.picker', ['id' => $clerk->id, 'name' => $clerk->contact_name ?? $clerk->username])
        ->assertJsonPath('data.delivery_notes.0.state_label', 'Picking');

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['per_page' => 1, 'page' => 2])
        ->assertOk()
        ->assertJsonPath('pagination', ['current_page' => 2, 'last_page' => 2, 'per_page' => 1, 'total' => 2])
        ->assertJsonPath('data.delivery_notes.0.reference', $newer->reference);

    foreach (['number_items', '-number_items', 'effective_weight', '-effective_weight', 'customer_name', '-customer_name', 'date'] as $sort) {
        expect(goodsOutReferences($this->warehouse, ['sort' => $sort]))->toHaveCount(2);
    }

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['bucket' => 'nope', 'per_page' => 101])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bucket', 'per_page']);
});

test('mobile delivery notes list filters by the dispatching channel', function () {
    $dropshippingOrder = goodsOutDropshippingOrder();
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    $wholesale    = goodsOutDeliveryNote($this->order, $this->warehouse);
    $dropshipping = goodsOutDeliveryNote($dropshippingOrder, $this->warehouse);

    expect(goodsOutReferences($this->warehouse, ['channel' => 'b2b']))->toBe([$wholesale->reference])
        ->and(goodsOutReferences($this->warehouse, ['channel' => 'dropshipping']))->toBe([$dropshipping->reference])
        ->and(goodsOutReferences($this->warehouse, ['channel' => 'b2c']))->toBe([])
        ->and(goodsOutReferences($this->warehouse, ['channel' => 'all']))->toHaveCount(2);

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['channel' => 'dropshipping'])
        ->assertJsonPath('data.delivery_notes.0.channel', 'dropshipping')
        ->assertJsonPath('data.delivery_notes.0.shop.type', 'dropshipping');
});

test('mobile delivery notes list and counts need the web dispatching right on the warehouse', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_VIEWER]);

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id)->assertForbidden();
    getGoodsOut('grp.api.mobile.warehouses.delivery_notes.counts', $this->warehouse->id)->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id)
        ->assertOk()
        ->assertJsonPath('data.can_work_in_warehouse', false);
});

test('mobile delivery note counts match the list, per bucket and per channel', function () {
    $dropshippingOrder = goodsOutDropshippingOrder();
    $otherWarehouse    = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $clerk             = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    goodsOutDeliveryNote($this->order, $this->warehouse);
    goodsOutDeliveryNote($this->order, $this->warehouse);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::DISPATCHED]);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::CANCELLED]);
    goodsOutDeliveryNote($dropshippingOrder, $this->warehouse, ['state' => DeliveryNoteStateEnum::PACKED]);
    goodsOutDeliveryNote($this->order, $otherWarehouse);

    $counts = getGoodsOut('grp.api.mobile.warehouses.delivery_notes.counts', $this->warehouse->id)->assertOk()->json('data');

    expect($counts['buckets'])->toBe([
        'unassigned' => 2, 'queued' => 0, 'handling' => 1, 'handling_blocked' => 0, 'picked' => 0,
        'packing' => 0, 'packed' => 1, 'finalised' => 0, 'dispatched' => 1, 'in_warehouse' => 4,
    ]);

    foreach ($counts['buckets'] as $bucket => $count) {
        getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['bucket' => $bucket])->assertJsonPath('pagination.total', $count);
    }

    expect(collect($counts['channels'])->map(fn (array $channel) => [$channel['channel'], $channel['label']])->all())
        ->toBe([['b2b', 'Wholesale'], ['dropshipping', 'Dropshipping']]);

    foreach ($counts['channels'] as $channel) {
        foreach ($channel['buckets'] as $bucket => $count) {
            getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $this->warehouse->id, ['bucket' => $bucket, 'channel' => $channel['channel']])
                ->assertJsonPath('pagination.total', $count);
        }
    }

    getGoodsOut('grp.api.mobile.warehouses.delivery_notes.counts', $this->warehouse->id, ['picker' => 'me'])
        ->assertOk()
        ->assertJsonPath('data.buckets.handling', 1)
        ->assertJsonPath('data.buckets.unassigned', 0)
        ->assertJsonPath('data.buckets.in_warehouse', 1);
});

test('mobile delivery note page sends the header and the lines in walk order while handling', function () {
    $clerk       = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $suffix      = Str::upper(Str::random(5));
    $lastByCode  = goodsOutOrgStock($this->group, $this->order, 'ZZ'.$suffix, 12);
    $firstByCode = goodsOutOrgStock($this->group, $this->order, 'AA'.$suffix);

    $deliveryNote = goodsOutDeliveryNote($this->order, $this->warehouse, ['shipping_notes' => 'Leave with neighbour', 'customer_notes' => 'Please wrap']);
    $nearLine     = goodsOutLine($deliveryNote, $this->order, $lastByCode, goodsOutLocation($this->warehouse, '001'), 1 + 5 / 12);
    goodsOutLine($deliveryNote, $this->order, $firstByCode, goodsOutLocation($this->warehouse, '002'), 2);
    $nearLine->update(['quantity_picked' => 1]);

    $page = fn () => getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk();

    expect(collect($page()->json('data.lines'))->pluck('org_stock.code')->all())->toBe([$firstByCode->code, $lastByCode->code]);

    $deliveryNote->update(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);

    $response = $page()
        ->assertJsonPath('data.id', $deliveryNote->id)
        ->assertJsonPath('data.reference', $deliveryNote->reference)
        ->assertJsonPath('data.state', 'handling')
        ->assertJsonPath('data.state_label', 'Picking')
        ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
        ->assertJsonPath('data.shop', ['id' => $this->shop->id, 'name' => $this->shop->name, 'type' => 'b2b'])
        ->assertJsonPath('data.channel', 'b2b')
        ->assertJsonPath('data.order', ['id' => $this->order->id, 'reference' => $this->order->reference])
        ->assertJsonPath('data.customer.name', $this->customer->name)
        ->assertJsonPath('data.picker', ['id' => $clerk->id, 'name' => $clerk->contact_name ?? $clerk->username])
        ->assertJsonPath('data.packer', null)
        ->assertJsonPath('data.notes.shipping', ['text' => 'Leave with neighbour', 'editable' => true])
        ->assertJsonPath('data.notes.customer', ['text' => 'Please wrap', 'editable' => false])
        ->assertJsonPath('data.line_counts', ['all' => 2, 'todo' => 2, 'done' => 0])
        ->assertJsonPath('data.is_editable', true)
        ->assertJsonPath('data.allow_actions', true)
        ->assertJsonPath('data.read_only_notice', null)
        ->assertJsonPath('data.warning', null)
        ->assertJsonPath('data.lines.0.org_stock.code', $lastByCode->code)
        ->assertJsonPath('data.lines.0.org_stock.packed_in', 12)
        ->assertJsonPath('data.lines.0.org_stock.image_url', null)
        ->assertJsonPath('data.lines.0.quantity_required_label', '1 5/12')
        ->assertJsonPath('data.lines.0.quantity_picked_label', '1')
        ->assertJsonPath('data.lines.0.quantity_to_pick_label', '5/12')
        ->assertJsonPath('data.lines.0.locations.0.stock_label', '30')
        ->assertJsonPath('data.lines.0.locations.0.type', 'picking')
        ->assertJsonPath('data.lines.0.is_picked', false)
        ->assertJsonPath('data.lines.1.org_stock.code', $firstByCode->code)
        ->assertJsonPath('data.lines.1.quantity_to_pick_label', '2');

    expect(collect($response->json('data.timeline'))->pluck('state')->all())->toContain('unassigned', 'handling')
        ->and($response->json('data.delivery_address.lines'))->not->toBeEmpty()
        ->and($response->json('data.lines.0.quantity_to_pick'))->toEqualWithDelta(5 / 12, 0.0001)
        ->and(array_keys($response->json('data.lines.0')))->toBe([
            'id', 'state', 'state_label', 'org_stock', 'quantity_required', 'quantity_required_label', 'quantity_picked', 'quantity_picked_label',
            'quantity_to_pick', 'quantity_to_pick_label', 'quantity_not_picked', 'quantity_packed', 'quantity_packed_label', 'quantity_to_pack',
            'quantity_to_pack_label', 'quantity_waiting_warehouse', 'quantity_waiting_crm', 'is_handled', 'is_picked', 'is_done_packing', 'is_packed',
            'locations', 'pickings', 'boxes', 'warehouse_area', 'note_to_pickers', 'note_to_packers', 'un_numbers', 'replacement_reason', 'indivisible_set',
        ])
        ->and(array_keys($response->json('data.lines.0.locations.0')))->toBe([
            'location_org_stock_id', 'location_id', 'code', 'stock', 'stock_label', 'type', 'quantity_picked', 'picking_id',
        ]);
});

test('mobile delivery note page lets only the handler act and shows view-only staff a read-only page', function () {
    $clerk     = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group);

    $mine   = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);
    $theirs = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $colleague->id]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $mine->id)->assertOk()
        ->assertJsonPath('data.is_editable', true)
        ->assertJsonPath('data.allow_actions', true);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $theirs->id)->assertOk()
        ->assertJsonPath('data.is_editable', true)
        ->assertJsonPath('data.allow_actions', false)
        ->assertJsonPath('data.picker', ['id' => $colleague->id, 'name' => $colleague->contact_name ?? $colleague->username]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);

    $response = getGoodsOut('grp.api.mobile.delivery_notes.show', $mine->id)->assertOk()
        ->assertJsonPath('data.is_editable', false)
        ->assertJsonPath('data.allow_actions', false)
        ->assertJsonPath('data.notes.warehouse.editable', false);

    expect($response->json('data.read_only_notice'))->toBeString()->not->toBeEmpty();
});

test('mobile delivery note page hides notes of other warehouses and needs the view right', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    $elsewhere = goodsOutDeliveryNote($this->order, $otherWarehouse);
    $here      = goodsOutDeliveryNote($this->order, $this->warehouse);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $elsewhere->id)->assertNotFound();
    getGoodsOut('grp.api.mobile.delivery_notes.show', 999999999)->assertNotFound();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_VIEWER]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $here->id)->assertForbidden();
});

test('mobile scan finds an sko by barcode, a location by code and a delivery note by reference or tracking', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK], ["inventory.{$this->organisation->id}.view"]);

    $orgStock     = goodsOutOrgStock($this->group, $this->order, 'SC'.Str::upper(Str::random(6)), 6);
    $location     = goodsOutLocation($this->warehouse);
    $deliveryNote = goodsOutDeliveryNote($this->order, $this->warehouse, ['tracking_number' => 'GOTRK1'.Str::upper(Str::random(4)).', GOTRK2X']);
    StoreLocationOrgStock::make()->action(orgStock: $orgStock, location: $location, modelData: ['quantity' => 18, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()], strict: false);

    $scan = fn (string $code) => getGoodsOut('grp.api.mobile.warehouses.scan', $this->warehouse->id, ['code' => $code])->assertOk();

    $sko = $scan(' '.$orgStock->barcode.' ')
        ->assertJsonPath('data.code', $orgStock->barcode)
        ->assertJsonPath('data.type', 'sko')
        ->assertJsonPath('data.sko.id', $orgStock->id)
        ->assertJsonPath('data.sko.matched_on', 'sko')
        ->assertJsonPath('data.sko.packed_in', 6)
        ->assertJsonPath('data.location', null)
        ->assertJsonPath('data.delivery_note', null)
        ->json('data.sko');

    expect(array_keys($sko))->toBe(['id', 'code', 'name', 'state', 'barcode', 'unit_barcode', 'packed_in', 'quantity_in_locations', 'image_url', 'locations', 'matched_on'])
        ->and($sko['locations'])->toEqual([['code' => $location->code, 'quantity' => 18]]);

    $scan(strtolower($location->code))
        ->assertJsonPath('data.type', 'location')
        ->assertJsonPath('data.location.id', $location->id)
        ->assertJsonPath('data.location.code', $location->code)
        ->assertJsonPath('data.location.number_org_stocks', 1);

    $scan(strtolower($deliveryNote->reference))
        ->assertJsonPath('data.type', 'delivery_note')
        ->assertJsonPath('data.delivery_note.id', $deliveryNote->id)
        ->assertJsonPath('data.delivery_note.channel', 'b2b');

    $scan('gotrk2x')
        ->assertJsonPath('data.type', 'delivery_note')
        ->assertJsonPath('data.delivery_note.id', $deliveryNote->id);

    $scan('NOTHING-'.Str::random(8))
        ->assertJsonPath('data.type', 'unknown')
        ->assertJsonPath('data.sko', null)
        ->assertJsonPath('data.location', null)
        ->assertJsonPath('data.delivery_note', null);

    getGoodsOut('grp.api.mobile.warehouses.scan', $this->warehouse->id)->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('mobile scan hides each kind without its view right', function () {
    $orgStock     = goodsOutOrgStock($this->group, $this->order, 'SH'.Str::upper(Str::random(6)));
    $location     = goodsOutLocation($this->warehouse);
    $deliveryNote = goodsOutDeliveryNote($this->order, $this->warehouse);
    $warehouseId  = $this->warehouse->id;

    $scanType = fn (string $code) => getGoodsOut('grp.api.mobile.warehouses.scan', $this->warehouse->id, ['code' => $code])->assertOk()->json('data.type');

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["dispatching.$warehouseId.view"]);
    expect($scanType($orgStock->barcode))->toBe('unknown')
        ->and($scanType($location->code))->toBe('unknown')
        ->and($scanType($deliveryNote->reference))->toBe('delivery_note');

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["locations.$warehouseId.view"]);
    expect($scanType($orgStock->barcode))->toBe('unknown')
        ->and($scanType($location->code))->toBe('location')
        ->and($scanType($deliveryNote->reference))->toBe('unknown');

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["stocks.$warehouseId.view"]);
    expect($scanType($orgStock->barcode))->toBe('sko')
        ->and($scanType($location->code))->toBe('unknown')
        ->and($scanType($deliveryNote->reference))->toBe('unknown');

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["incoming.$warehouseId.view"]);
    getGoodsOut('grp.api.mobile.warehouses.scan', $this->warehouse->id, ['code' => $deliveryNote->reference])->assertForbidden();
});

test('mobile delivery note offers the picking actions of the web, by state and by user', function () {
    $clerk     = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group);
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true, 'allow_picker_set_not_picked' => false, 'allow_scan_to_pick' => true, 'allow_picker_choose_location' => false, 'allow_scan_to_pack' => false]);

    [$toDo] = goodsOutPickingNote($this);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $toDo->id)->assertOk()
        ->assertJsonPath('data.available_actions', [['key' => 'start_picking', 'label' => 'Start picking'], ['key' => 'assign_picker', 'label' => 'Assign picker'], ['key' => 'edit_delivery_address', 'label' => 'Edit delivery address']])
        ->assertJsonPath('data.can_handle', false)
        ->assertJsonPath('data.allow_actions', false)
        ->assertJsonPath('data.incomplete_sets', [])
        ->assertJsonPath('data.settings', ['allow_scan_to_pick' => true, 'allow_waiting' => true, 'allow_picker_set_not_picked' => false, 'allow_picker_choose_location' => false, 'allow_scan_to_pack' => false]);

    [$theirs] = goodsOutPickingNote($this, [2], $colleague);
    [$mine, [$line]] = goodsOutPickingNote($this, [2], $clerk);

    expect(goodsOutActionKeys($theirs))->toBe(['take_over', 'edit_delivery_address'])
        ->and(goodsOutActionKeys($mine))->toBe(['change_trolley', 'edit_delivery_address']);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$mine->id, $line->id])->assertOk();
    expect(goodsOutActionKeys($mine))->toBe(['set_as_picked', 'change_trolley', 'edit_delivery_address']);

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $mine->id)->assertOk()->assertJsonPath('data.state', 'picked');
    expect(goodsOutActionKeys($mine))->toBe(['start_packing', 'undo_set_as_picked', 'change_trolley', 'change_picked_bay', 'edit_delivery_address']);

    [$waiting, [$waitingLine]] = goodsOutPickingNote($this, [2], $clerk);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$waiting->id, $waitingLine->id], ['kind' => 'warehouse', 'quantity' => 2])->assertOk()
        ->assertJsonPath('data.state', 'handling_blocked');
    expect(goodsOutActionKeys($waiting))->toBe(['auto_finish_waiting', 'change_trolley', 'change_picked_bay', 'edit_delivery_address']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    expect(goodsOutActionKeys($waiting))->toBe(['take_over', 'auto_finish_waiting', 'back_to_picking', 'edit_delivery_address', 'cancel'])
        ->and(goodsOutActionKeys($toDo))->toBe(['start_picking', 'assign_picker', 'edit_delivery_address', 'cancel']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    foreach ([$toDo, $theirs, $mine, $waiting] as $deliveryNote) {
        expect(goodsOutActionKeys($deliveryNote))->toBe([]);
    }
});

test('mobile start picking takes a free trolley of the warehouse, or none, and makes the user the picker', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    [$withTrolley]    = goodsOutPickingNote($this);
    [$withoutTrolley] = goodsOutPickingNote($this);
    [$holdingTrolley] = goodsOutPickingNote($this);

    $trolley      = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MT'.Str::random(6)]);
    $busyTrolley  = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MB'.Str::random(6)]);
    $elsewhere    = StoreTrolley::make()->handle($otherWarehouse, ['name' => 'ME'.Str::random(6)]);
    $busyTrolley->update(['current_delivery_note_id' => $holdingTrolley->id]);

    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $withTrolley->id, ['trolley_id' => $busyTrolley->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trolley_id' => 'That trolley is in use.']);
    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $withTrolley->id, ['trolley_id' => $elsewhere->id])->assertNotFound();
    expect($withTrolley->refresh()->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED);

    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $withTrolley->id, ['trolley_id' => $trolley->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'handling')
        ->assertJsonPath('data.picker.id', $clerk->id)
        ->assertJsonPath('data.can_handle', true)
        ->assertJsonPath('data.allow_actions', true)
        ->assertJsonPath('data.trolleys.0.id', $trolley->id);
    expect($trolley->refresh()->current_delivery_note_id)->toBe($withTrolley->id);

    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $withoutTrolley->id, ['trolley_id' => null])->assertOk()
        ->assertJsonPath('data.state', 'handling')
        ->assertJsonPath('data.trolleys', []);

    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $withTrolley->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $holdingTrolley->id)->assertForbidden();
});

test('mobile take over lets any warehouse worker pick and scan for an hour without changing the picker', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => true, 'allow_picker_choose_location' => false]);
    $picker = createMobileAppUser($this->group);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [3], $picker);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $location = goodsOutLineLocation($line);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], ['location_org_stock_id' => $location->id, 'quantity' => 1])->assertForbidden();
    postGoodsOut('grp.api.mobile.delivery_notes.scan_pick', $deliveryNote->id, ['barcode' => $line->orgStock->barcode, 'quantity' => 1])->assertForbidden();

    postGoodsOut('grp.api.mobile.delivery_notes.take_over', $deliveryNote->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.can_handle', true)
        ->assertJsonPath('data.allow_actions', true)
        ->assertJsonPath('data.picker.id', $picker->id);
    expect(Cache::get(WithDeliveryNoteHandler::mobileDeliveryNoteClaimKey($clerk))['delivery_note_id'])->toBe($deliveryNote->id);

    postGoodsOut('grp.api.mobile.delivery_notes.take_over', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], ['location_org_stock_id' => $location->id, 'quantity' => 1])->assertOk()
        ->assertJsonPath('data.lines.0.quantity_picked', 1);
    postGoodsOut('grp.api.mobile.delivery_notes.scan_pick', $deliveryNote->id, ['barcode' => $line->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'picked');

    expect($deliveryNote->refresh()->picker_user_id)->toBe($picker->id)
        ->and((float) $line->refresh()->quantity_picked)->toBe(2.0);

    $this->travel(61)->minutes();
    getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()
        ->assertJsonPath('data.can_handle', false)
        ->assertJsonPath('data.available_actions.0.key', 'take_over');
    $this->travelBack();
});

test('mobile pick sets the absolute quantity taken from a location of the line, and pick all takes the rest', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_picker_choose_location' => false]);
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line, $otherLine]] = goodsOutPickingNote($this, [5, 4], $clerk);
    $location      = goodsOutLineLocation($line);
    $otherLocation = goodsOutLineLocation($otherLine);
    $pick          = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], $body);
    $lineOf        = fn ($response) => collect($response->json('data.lines'))->firstWhere('id', $line->id);

    $first   = $pick(['location_org_stock_id' => $location->id, 'quantity' => 2])->assertOk()->assertJsonPath('result.status', 'done');
    $picking = $lineOf($first)['pickings'][0];
    expect($lineOf($first)['quantity_picked'])->toEqual(2)
        ->and($picking)->toMatchArray(['type' => 'pick', 'location_org_stock_id' => $location->id, 'quantity' => 2, 'quantity_label' => '2', 'not_picked_reason' => null])
        ->and($lineOf($first)['locations'][0]['picking_id'])->toBe($picking['id']);

    $second = $pick(['location_org_stock_id' => $location->id, 'quantity' => 3])->assertOk();
    expect($lineOf($second)['quantity_picked'])->toEqual(3)
        ->and($lineOf($second)['pickings'])->toHaveCount(1)
        ->and($lineOf($second)['pickings'][0]['id'])->toBe($picking['id']);

    $pick(['location_org_stock_id' => $location->id, 'quantity' => 3])->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $pick(['location_org_stock_id' => $otherLocation->id, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id']);
    $elsewhere = StoreLocationOrgStock::make()->action(
        orgStock: $line->orgStock,
        location: goodsOutLocation($otherWarehouse),
        modelData: ['quantity' => 10, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );
    $pick(['location_org_stock_id' => $elsewhere->id, 'quantity' => 1])->assertNotFound();
    $pick(['location_org_stock_id' => $location->id])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

    $secondShelf = StoreLocationOrgStock::make()->action(
        orgStock: $line->orgStock,
        location: goodsOutLocation($this->warehouse),
        modelData: ['quantity' => 10, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );
    setGoodsOutPickingSettings($this->organisation, ['allow_picker_choose_location' => true]);
    $pick(['location_org_stock_id' => $secondShelf->id, 'quantity' => 1])->assertOk()->assertJsonPath('result.status', 'done');
    expect((float) $line->refresh()->quantity_picked)->toBe(4.0);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $otherLine->id], ['location_org_stock_id' => null])->assertOk()
        ->assertJsonPath('result.status', 'done');
    expect((float) $otherLine->refresh()->quantity_picked)->toBe(4.0)
        ->and($otherLine->is_handled)->toBeTrue();

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $otherLine->id])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');
});

test('mobile line actions mark a line not picked, set it waiting and undo a pick, each behind its setting', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true, 'allow_picker_set_not_picked' => false]);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$notPicked, $waiting, $undone]] = goodsOutPickingNote($this, [3, 4, 2], $clerk);
    $lineOf = fn ($response, DeliveryNoteItem $line) => collect($response->json('data.lines'))->firstWhere('id', $line->id);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $notPicked->id], ['reason' => 'out-of-stock'])->assertForbidden();

    setGoodsOutPickingSettings($this->organisation, ['allow_picker_set_not_picked' => true]);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $notPicked->id], ['quantity' => 4, 'reason' => 'out-of-stock'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $notPicked->id], ['quantity' => 1, 'reason' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $response = postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $notPicked->id], ['quantity' => null, 'reason' => 'out-of-stock', 'note' => 'Shelf empty'])
        ->assertOk()
        ->assertJsonPath('result.status', 'done');
    expect($lineOf($response, $notPicked)['quantity_not_picked'])->toEqual(3)
        ->and($lineOf($response, $notPicked)['is_handled'])->toBeTrue()
        ->and($lineOf($response, $notPicked)['pickings'][0])->toMatchArray(['type' => 'not-pick', 'location_org_stock_id' => null, 'quantity' => 3, 'not_picked_reason' => 'out-of-stock', 'not_picked_reason_label' => 'Out of Stock']);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $notPicked->id], ['reason' => 'out-of-stock'])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');

    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => false]);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $waiting->id], ['kind' => 'warehouse', 'quantity' => 1])->assertForbidden();
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true]);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $waiting->id], ['kind' => 'shelf', 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kind', 'quantity']);

    $response = postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $waiting->id], ['kind' => 'warehouse', 'quantity' => 4, 'note' => 'Behind the pallet'])
        ->assertOk()
        ->assertJsonPath('result.status', 'done');
    expect($lineOf($response, $waiting)['quantity_waiting_warehouse'])->toEqual(4)
        ->and($waiting->refresh()->notes)->toBe('Behind the pallet');

    $response = postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $waiting->id], ['kind' => 'crm', 'quantity' => 1])->assertOk();
    expect($lineOf($response, $waiting)['quantity_waiting_warehouse'])->toEqual(3)
        ->and($lineOf($response, $waiting)['quantity_waiting_crm'])->toEqual(1);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $undone->id], ['location_org_stock_id' => goodsOutLineLocation($undone)->id, 'quantity' => 2])->assertOk();
    $picking = Picking::where('delivery_note_item_id', $undone->id)->firstOrFail();

    deleteGoodsOut('grp.api.mobile.delivery_notes.lines.pickings.delete', [$deliveryNote->id, $notPicked->id, $picking->id])->assertNotFound();
    deleteGoodsOut('grp.api.mobile.delivery_notes.lines.pickings.delete', [$deliveryNote->id, $undone->id, $picking->id])->assertOk()
        ->assertJsonPath('result.status', 'done');
    expect(Picking::find($picking->id))->toBeNull()
        ->and((float) $undone->refresh()->quantity_picked)->toBe(0.0);
});

test('mobile line actions are refused to a user who is not handling the note, and outside picking', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true, 'allow_picker_set_not_picked' => true, 'allow_picker_choose_location' => false]);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group);

    [$theirs, [$theirLine]] = goodsOutPickingNote($this, [2], $colleague);
    [$toDo, [$toDoLine]]    = goodsOutPickingNote($this);
    [$mine]                 = goodsOutPickingNote($this, [2], $clerk);

    $calls = fn (DeliveryNote $deliveryNote, DeliveryNoteItem $line) => [
        fn () => postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], ['location_org_stock_id' => goodsOutLineLocation($line)->id, 'quantity' => 1]),
        fn () => postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $line->id]),
        fn () => postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $line->id], ['reason' => 'out-of-stock']),
        fn () => postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $line->id], ['kind' => 'warehouse', 'quantity' => 1]),
    ];

    $theirPicking = StorePicking::make()->action($theirLine, $colleague, ['location_org_stock_id' => goodsOutLineLocation($theirLine)->id, 'quantity' => 1, 'picker_user_id' => $colleague->id]);

    foreach ($calls($theirs, $theirLine) as $call) {
        $call()->assertForbidden();
    }
    deleteGoodsOut('grp.api.mobile.delivery_notes.lines.pickings.delete', [$theirs->id, $theirLine->id, $theirPicking->id])->assertForbidden();
    foreach ($calls($toDo, $toDoLine) as $call) {
        $call()->assertUnprocessable()->assertJsonValidationErrors(['state' => 'This delivery note is not being picked.']);
    }
    foreach ($calls($mine, $theirLine) as $call) {
        $call()->assertNotFound();
    }

    expect((float) $theirLine->refresh()->quantity_picked)->toBe(1.0)
        ->and($theirLine->pickings()->pluck('id')->all())->toBe([$theirPicking->id]);
});

test('mobile set as picked leaves the note in a picked bay, waits on a blocking line and is refused while lines are left', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [2], $clerk);
    [$blocked, [$blockedLine]] = goodsOutPickingNote($this, [2], $clerk);
    $pickedBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'MPB'.Str::random(5)]);

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id, ['picked_bay_id' => $pickedBay->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $line->id])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id, ['picked_bay_id' => 999999999])->assertNotFound();
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id, ['picked_bay_id' => $pickedBay->id])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Set as picked.'])
        ->assertJsonPath('data.state', 'picked')
        ->assertJsonPath('data.picked_bays', [['id' => $pickedBay->id, 'name' => $pickedBay->code]]);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$blocked->id, $blockedLine->id])->assertOk();
    $blockedLine->refresh()->update(['quantity_waiting_crm' => 1]);
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $blocked->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'handling_blocked');
});

test('mobile undo set as picked, auto finish waiting and back to picking move a note between picked, waiting and picking', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true]);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$picked, [$pickedLine]]   = goodsOutPickingNote($this, [2], $clerk);
    [$waiting, [$waitingLine]] = goodsOutPickingNote($this, [4], $clerk);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$picked->id, $pickedLine->id])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.undo_set_as_picked', $picked->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $picked->id)->assertOk()->assertJsonPath('data.state', 'picked');
    postGoodsOut('grp.api.mobile.delivery_notes.undo_set_as_picked', $picked->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'handling');

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$waiting->id, $waitingLine->id], ['location_org_stock_id' => goodsOutLineLocation($waitingLine)->id, 'quantity' => 2])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$waiting->id, $waitingLine->id], ['kind' => 'warehouse', 'quantity' => 2])->assertOk()
        ->assertJsonPath('data.state', 'handling_blocked');

    postGoodsOut('grp.api.mobile.delivery_notes.auto_finish_waiting', $waiting->id)->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed')
        ->assertJsonPath('data.state', 'handling_blocked');
    postGoodsOut('grp.api.mobile.delivery_notes.back_to_picking', $waiting->id)->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    postGoodsOut('grp.api.mobile.delivery_notes.back_to_picking', $waiting->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'handling');
    expect((float) $waitingLine->refresh()->quantity_waiting_warehouse)->toBe(0.0);
    postGoodsOut('grp.api.mobile.delivery_notes.back_to_picking', $waiting->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    Sanctum::actingAs($clerk, ['mobile']);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$waiting->id, $waitingLine->id], ['location_org_stock_id' => goodsOutLineLocation($waitingLine)->id, 'quantity' => 4])->assertOk();
    $waiting->refresh()->update(['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED]);
    postGoodsOut('grp.api.mobile.delivery_notes.auto_finish_waiting', $waiting->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'picked');
});

test('mobile put back clears the parts of a set sold only complete when another part was not found', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => false]);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$found, $missing]] = goodsOutPickingNote($this, [2, 2], $clerk);
    $product = $found->transaction->model;

    try {
        postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $found->id])->assertOk();
        postGoodsOut('grp.api.mobile.delivery_notes.lines.not_picked', [$deliveryNote->id, $missing->id], ['reason' => 'out-of-stock'])->assertOk();
        postGoodsOut('grp.api.mobile.delivery_notes.put_back_incomplete_sets', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
        $product->update(['is_indivisible' => true]);

        $page = getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()
            ->assertJsonPath('data.incomplete_sets', [[
                'code'           => $found->orgStock->code,
                'name'           => $found->orgStock->name,
                'quantity'       => 2,
                'quantity_label' => '2',
                'locations'      => [goodsOutLineLocation($found)->location->code],
            ]]);
        expect(collect($page->json('data.available_actions'))->pluck('label', 'key')->all())
            ->toBe(['put_back_incomplete_sets' => 'Parts put back', 'change_trolley' => 'Change trolley', 'edit_delivery_address' => 'Edit delivery address']);

        postGoodsOut('grp.api.mobile.delivery_notes.put_back_incomplete_sets', $deliveryNote->id)->assertOk()
            ->assertJsonPath('result.status', 'done')
            ->assertJsonPath('data.incomplete_sets', []);
        expect((float) $found->refresh()->quantity_picked)->toBe(0.0)
            ->and((float) $found->quantity_not_picked)->toBe(2.0)
            ->and(goodsOutActionKeys($deliveryNote))->toBe(['set_as_picked', 'change_trolley', 'edit_delivery_address']);
    } finally {
        $product->update(['is_indivisible' => false]);
    }
});

test('mobile change trolley, picked bay and picker use free equipment of the warehouse and the web picker list', function () {
    $picker = goodsOutPickerUser($this);
    $clerk  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [2], $clerk);
    [$other]                 = goodsOutPickingNote($this);
    [$toDo]                  = goodsOutPickingNote($this);

    $trolley     = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MC'.Str::random(6)]);
    $nextTrolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MN'.Str::random(6)]);
    $busyTrolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MU'.Str::random(6)]);
    $busyTrolley->update(['current_delivery_note_id' => $other->id]);

    postGoodsOut('grp.api.mobile.delivery_notes.trolley', $deliveryNote->id, ['trolley_id' => $trolley->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.trolleys.0.id', $trolley->id);
    postGoodsOut('grp.api.mobile.delivery_notes.trolley', $deliveryNote->id, ['trolley_id' => $busyTrolley->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trolley_id' => 'That trolley is in use.']);
    postGoodsOut('grp.api.mobile.delivery_notes.trolley', $deliveryNote->id, ['trolley_id' => $nextTrolley->id])->assertOk()
        ->assertJsonPath('data.trolleys', [['id' => $nextTrolley->id, 'name' => $nextTrolley->name]]);
    expect($trolley->refresh()->current_delivery_note_id)->toBeNull()
        ->and($nextTrolley->refresh()->current_delivery_note_id)->toBe($deliveryNote->id);
    postGoodsOut('grp.api.mobile.delivery_notes.trolley', $deliveryNote->id, ['trolley_id' => null])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');

    $bay     = StorePickedBay::make()->handle($this->warehouse, ['code' => 'MBA'.Str::random(5)]);
    $nextBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'MBB'.Str::random(5)]);
    postGoodsOut('grp.api.mobile.delivery_notes.picked_bay', $deliveryNote->id, ['picked_bay_id' => $bay->id])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $line->id])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id, ['picked_bay_id' => $bay->id])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.picked_bay', $deliveryNote->id, ['picked_bay_id' => $nextBay->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.picked_bays', [['id' => $nextBay->id, 'name' => $nextBay->code]]);
    postGoodsOut('grp.api.mobile.delivery_notes.picked_bay', $deliveryNote->id, ['picked_bay_id' => $nextBay->id])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');

    postGoodsOut('grp.api.mobile.delivery_notes.picker', $toDo->id, ['user_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $toDo->id, ['user_id' => $clerk->id])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $toDo->id, ['user_id' => $picker->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'queued')
        ->assertJsonPath('data.picker.id', $picker->id);
    expect(goodsOutActionKeys($toDo))->toBe(['start_picking', 'assign_picker', 'remove_picker', 'edit_delivery_address'])
        ->and(getGoodsOut('grp.api.mobile.delivery_notes.show', $toDo->id)->json('data.available_actions.0.label'))->toBe('I will pick this');
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $toDo->id, ['user_id' => null])->assertOk()
        ->assertJsonPath('data.state', 'unassigned')
        ->assertJsonPath('data.picker', null);
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $deliveryNote->id, ['user_id' => $picker->id])->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile scan to pick picks through the web scan and reports what the line really gained', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => true]);
    $dropshippingOrder = goodsOutDropshippingOrder();
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [2], $clerk);
    $location = goodsOutLineLocation($line)->location;
    $scan     = fn (array $body, ?DeliveryNote $note = null) => postGoodsOut('grp.api.mobile.delivery_notes.scan_pick', ($note ?? $deliveryNote)->id, $body);

    $scan(['barcode' => $line->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('result.message', "Picked 1 x {$line->orgStock->code} from $location->code, 1 still to pick")
        ->assertJsonPath('result.item.id', $line->id)
        ->assertJsonPath('data.lines.0.quantity_picked', 1);
    $scan(['barcode' => $line->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('result.message', "Picked 1 x {$line->orgStock->code} from $location->code")
        ->assertJsonPath('data.lines.0.quantity_picked', 2);
    $scan(['barcode' => $line->orgStock->barcode])->assertOk()->assertJsonPath('result.status', 'already_picked');
    $scan(['barcode' => 'NOTHERE'.Str::random(6)])->assertOk()
        ->assertJsonPath('result.status', 'not_found')
        ->assertJsonPath('result.item', null);
    $scan([])->assertUnprocessable()->assertJsonValidationErrors(['barcode']);

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id)->assertOk();
    $scan(['barcode' => $line->orgStock->barcode])->assertOk()
        ->assertJsonPath('result.status', 'wrong_state')
        ->assertJsonPath('data.state', 'picked');

    $dropshipping = goodsOutDeliveryNote($dropshippingOrder, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);
    $scan(['barcode' => $line->orgStock->barcode], $dropshipping)->assertForbidden()
        ->assertJsonPath('message', 'Dropshipping delivery notes are not picked by scanning.');

    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => false]);
    $scan(['barcode' => $line->orgStock->barcode])->assertForbidden();
});

test('mobile equipment lists show the free trolleys, the picked bays with their notes and the pickers', function () {
    $picker  = goodsOutPickerUser($this);
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this);

    $free = StoreTrolley::make()->handle($this->warehouse, ['name' => 'A'.Str::random(6)]);
    $busy = StoreTrolley::make()->handle($this->warehouse, ['name' => 'B'.Str::random(6)]);
    $busy->update(['current_delivery_note_id' => $deliveryNote->id]);
    $fullBay  = StorePickedBay::make()->handle($this->warehouse, ['code' => 'A'.Str::random(6)]);
    $emptyBay = StorePickedBay::make()->handle($this->warehouse, ['code' => 'B'.Str::random(6)]);
    AttachDeliveryNoteToPickedBay::run($fullBay, $deliveryNote);

    getGoodsOut('grp.api.mobile.warehouses.trolleys', $this->warehouse->id, ['available' => 1])->assertOk()
        ->assertJsonPath('data', [['id' => $free->id, 'name' => $free->name, 'current_delivery_note' => null, 'current_picking_session' => null]]);
    getGoodsOut('grp.api.mobile.warehouses.trolleys', $this->warehouse->id)->assertOk()
        ->assertJsonPath('data.1', [
            'id'                      => $busy->id,
            'name'                    => $busy->name,
            'current_delivery_note'   => ['id' => $deliveryNote->id, 'reference' => $deliveryNote->reference],
            'current_picking_session' => null,
        ])
        ->assertJsonCount(2, 'data');

    getGoodsOut('grp.api.mobile.warehouses.picked_bays', $this->warehouse->id)->assertOk()
        ->assertJsonPath('data', [
            ['id' => $fullBay->id, 'code' => $fullBay->code, 'number_delivery_notes' => 1, 'number_waiting' => 0, 'delivery_notes' => [
                ['id' => $deliveryNote->id, 'reference' => $deliveryNote->reference, 'state' => 'unassigned', 'state_label' => 'To do'],
            ]],
            ['id' => $emptyBay->id, 'code' => $emptyBay->code, 'number_delivery_notes' => 0, 'number_waiting' => 0, 'delivery_notes' => []],
        ]);

    $pickers = getGoodsOut('grp.api.mobile.warehouses.pickers', $this->warehouse->id)->assertOk()->json('data');
    expect(collect($pickers)->firstWhere('user_id', $picker->id))->toBe(['user_id' => $picker->id, 'name' => $picker->contact_name, 'alias' => $picker->employees()->first()->alias])
        ->and(array_keys($pickers[0]))->toBe(['user_id', 'name', 'alias']);

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["stocks.{$this->warehouse->id}.view"]);
    getGoodsOut('grp.api.mobile.warehouses.trolleys', $this->warehouse->id)->assertForbidden();
    getGoodsOut('grp.api.mobile.warehouses.picked_bays', $this->warehouse->id)->assertForbidden();
    getGoodsOut('grp.api.mobile.warehouses.pickers', $this->warehouse->id)->assertForbidden();
});

test('mobile picking writes are refused on a note locked in Aurora', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$toDo]                  = goodsOutPickingNote($this);
    [$handling, [$line]]     = goodsOutPickingNote($this, [2], $clerk);
    $toDo->update(['handled_in_aurora' => true]);
    $handling->update(['handled_in_aurora' => true]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $toDo->id)->assertOk()
        ->assertJsonPath('data.is_editable', false)
        ->assertJsonPath('data.available_actions', []);

    postGoodsOut('grp.api.mobile.delivery_notes.start_picking', $toDo->id)->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$handling->id, $line->id])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.delivery_notes.scan_pick', $handling->id, ['barcode' => $line->orgStock->barcode])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);

    expect($toDo->refresh()->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED)
        ->and((float) $line->refresh()->quantity_picked)->toBe(0.0);
});

test('mobile delivery note lines only list locations in the note\'s own warehouse', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $orgStock       = goodsOutOrgStock($this->group, $this->order, 'WH'.Str::upper(Str::random(6)));
    $deliveryNote   = goodsOutDeliveryNote($this->order, $this->warehouse);
    $hereLocation   = goodsOutLocation($this->warehouse, '001');
    goodsOutLine($deliveryNote, $this->order, $orgStock, $hereLocation, 1);
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: goodsOutLocation($otherWarehouse, '000'),
        modelData: ['quantity' => 50, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $clerk->id]);

    $locations = getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()->json('data.lines.0.locations');

    expect(collect($locations)->pluck('location_id')->all())->toBe([$hereLocation->id]);
});

test('mobile sets a fully picked dropshipping note as packed instead of picked', function () {
    $clerk                   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [2], $clerk);
    $shop                    = $deliveryNote->shop;
    $originalType            = $shop->type;
    $shop->update(['type' => ShopTypeEnum::DROPSHIPPING]);

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_packed', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $picked = postGoodsOut('grp.api.mobile.delivery_notes.lines.pick_all', [$deliveryNote->id, $line->id])->assertOk();
    expect(collect($picked->json('data.available_actions'))->pluck('key')->all())->toContain('set_as_packed')->not->toContain('set_as_picked');

    expect(collect($picked->json('data.available_actions'))->pluck('key')->all())->not->toContain('change_trolley');
    postGoodsOut('grp.api.mobile.delivery_notes.trolley', $deliveryNote->id, ['trolley_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_picked', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_packed', $deliveryNote->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery note packed.'])
        ->assertJsonPath('data.state', 'packed');

    $shop->update(['type' => $originalType]);
});
