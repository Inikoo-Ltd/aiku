<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Trolley\StoreTrolley;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';

function mobileSessionShelf(object $ctx): LocationOrgStock
{
    $orgStock = goodsOutOrgStock($ctx->group, $ctx->order, 'PS'.Str::upper(Str::random(6)));

    return StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: goodsOutLocation($ctx->warehouse, 'S'.Str::random(4)),
        modelData: ['quantity' => 30, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );
}

function mobileSessionNote(object $ctx, LocationOrgStock $shelf, float $quantityRequired): DeliveryNote
{
    $order        = goodsOutSubmittedOrder($ctx);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $ctx->warehouse->id]);
    $deliveryNote->deliveryNoteItems()->delete();

    StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $shelf->org_stock_id,
        'transaction_id'    => $order->transactions()->first()->id,
        'quantity_required' => $quantityRequired,
    ]);

    return $deliveryNote->refresh();
}

/**
 * @param array<int, DeliveryNote> $deliveryNotes
 */
function mobileSessionOf(Warehouse $warehouse, array $deliveryNotes): int
{
    return postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $warehouse->id, ['delivery_note_ids' => collect($deliveryNotes)->pluck('id')->all()])
        ->assertCreated()
        ->json('data.id');
}

/**
 * @return array<int, bool>
 */
function mobileSessionCandidates(Warehouse $warehouse): array
{
    return collect(getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $warehouse->id, ['bucket' => 'in_warehouse'])->assertOk()->json('data.delivery_notes'))
        ->pluck('can_add_to_picking_session', 'id')
        ->sortKeys()
        ->all();
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    $this->shop->update(['type' => ShopTypeEnum::B2C]);
    $this->group            = $this->organisation->group;
    $this->customer         = createCustomer($this->shop);
    $this->order            = createOrder($this->customer, createProduct($this->shop)[1]);
    $this->warehouse        = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $this->originalSettings = $this->organisation->settings;
});

afterEach(function () {
    $this->organisation->update(['settings' => $this->originalSettings]);
});

test('mobile makes a picking session from selected notes, lists it by state with the counts and shows its notes, pick list and actions', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => true]);
    $clerk  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf  = mobileSessionShelf($this);
    $first  = mobileSessionNote($this, $shelf, 2);
    $second = mobileSessionNote($this, $shelf, 3);

    expect(mobileSessionCandidates($this->warehouse))->toBe([$first->id => true, $second->id => true]);

    $created = postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $this->warehouse->id, ['delivery_note_ids' => [$first->id, $second->id]])
        ->assertCreated()
        ->assertJsonPath('data.state', 'in_process')
        ->assertJsonPath('data.state_label', 'In Process')
        ->assertJsonPath('data.picker', ['id' => $clerk->id, 'name' => $clerk->contact_name ?? $clerk->username])
        ->assertJsonPath('data.number_delivery_notes', 2)
        ->assertJsonPath('data.can_work', true)
        ->assertJsonPath('data.trolleys', [])
        ->assertJsonPath('data.can_change_trolleys', true)
        ->assertJsonPath('data.settings', ['allow_scan_to_pick' => true, 'allow_scan_to_pack' => false, 'allow_waiting' => false, 'allow_picker_set_not_picked' => true])
        ->assertJsonPath('data.pick_list', [])
        ->assertJsonPath('data.available_actions', [
            ['key' => 'start_picking', 'label' => 'Start picking'],
            ['key' => 'add_delivery_notes', 'label' => 'Add delivery notes'],
            ['key' => 'remove_delivery_notes', 'label' => 'Remove delivery notes'],
        ]);
    $sessionId = $created->json('data.id');

    expect(array_keys($created->json('data')))->toBe([
        'id', 'reference', 'state', 'state_label', 'picker', 'number_delivery_notes', 'number_items', 'number_trolleys', 'picking_percentage',
        'packing_percentage', 'is_waiting_ready', 'start_at', 'end_at', 'can_work', 'trolleys', 'can_change_trolleys', 'settings',
        'available_actions', 'delivery_notes', 'pick_list',
    ])
        ->and($created->json('data.delivery_notes.0'))->toBe([
            'id'                 => $first->id,
            'reference'          => $first->reference,
            'state'              => 'queued',
            'state_label'        => DeliveryNoteStateEnum::labels()['queued'],
            'number_items'       => 1,
            'picking_percentage' => 0,
            'packing_percentage' => 0,
            'has_waiting_items'  => false,
            'is_for_collection'  => false,
        ])
        ->and(mobileSessionCandidates($this->warehouse))->toBe([$first->id => false, $second->id => false]);

    $listed = getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id)->assertOk()
        ->assertJsonPath('data.0.id', $sessionId)
        ->assertJsonPath('data.0.picking_percentage', 0)
        ->assertJsonPath('pagination', ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1]);
    $stats = $this->warehouse->refresh()->stats;
    expect(array_keys($listed->json('data.0')))->toBe([
        'id', 'reference', 'state', 'state_label', 'picker', 'number_delivery_notes', 'number_items', 'number_trolleys', 'picking_percentage',
        'packing_percentage', 'is_waiting_ready', 'start_at', 'end_at',
    ])
        ->and($listed->json('counts'))->toBe([
            'in_process'       => (int) $stats->number_picking_sessions_state_in_process,
            'handling'         => (int) $stats->number_picking_sessions_state_handling,
            'handling_blocked' => (int) $stats->number_picking_sessions_state_handling_blocked,
            'picking_finished' => (int) $stats->number_picking_sessions_state_picking_finished,
            'packing_finished' => (int) $stats->number_picking_sessions_state_packing_finished,
        ]);
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id, ['state' => 'handling'])->assertOk()->assertJsonPath('pagination.total', 0);
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id, ['state' => 'in_process'])->assertOk()->assertJsonPath('data.0.id', $sessionId);
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id, ['state' => 'picked'])->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $started = postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Picking started.'])
        ->assertJsonPath('data.state', 'handling')
        ->assertJsonPath('data.state_label', 'Picking')
        ->assertJsonPath('data.available_actions', [['key' => 'scan_pick', 'label' => 'Scan to pick']]);

    expect($started->json('data.pick_list'))->toHaveCount(1)
        ->and($started->json('data.pick_list.0'))->toMatchArray([
            'key'                    => $shelf->location_id.'-'.$shelf->org_stock_id,
            'location'               => ['id' => $shelf->location_id, 'code' => $shelf->location->code],
            'quantity_required'      => 5,
            'quantity_picked'        => 0,
            'quantity_to_pick'       => 5,
            'quantity_to_pick_label' => '5',
        ])
        ->and($started->json('data.pick_list.0.org_stock'))->toMatchArray(['id' => $shelf->org_stock_id, 'code' => $shelf->orgStock->code, 'barcode' => $shelf->orgStock->barcode])
        ->and(collect($started->json('data.pick_list.0.lines'))->sortBy('delivery_note_id')->map(fn (array $line) => [$line['delivery_note_reference'], $line['quantity_to_pick']])->values()->all())
        ->toBe([[$first->reference, 2], [$second->reference, 3]])
        ->and($first->refresh()->state)->toBe(DeliveryNoteStateEnum::HANDLING)
        ->and($first->picker_user_id)->toBe($clerk->id);

    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile refuses to put in a picking session a wholesale note, a note already in one or one locked in Aurora', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf     = mobileSessionShelf($this);
    $inSession = mobileSessionNote($this, $shelf, 1);
    $locked    = mobileSessionNote($this, $shelf, 1);
    $free      = mobileSessionNote($this, $shelf, 1);
    $existing  = mobileSessionOf($this->warehouse, [$inSession]);
    $locked->update(['handled_in_aurora' => true]);

    expect(mobileSessionCandidates($this->warehouse))->toBe([$inSession->id => false, $locked->id => false, $free->id => true]);

    postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $this->warehouse->id, ['delivery_note_ids' => [$free->id, $inSession->id, $locked->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_note_ids' => "These delivery notes can not go in a picking session: $inSession->reference, $locked->reference."]);
    postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $this->warehouse->id, ['delivery_note_ids' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['delivery_note_ids']);

    $this->shop->update(['type' => ShopTypeEnum::B2B]);
    expect(mobileSessionCandidates($this->warehouse)[$free->id])->toBeFalse();
    postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $this->warehouse->id, ['delivery_note_ids' => [$free->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_note_ids' => "These delivery notes can not go in a picking session: $free->reference."]);

    expect($free->refresh()->pickingSessions()->exists())->toBeFalse()
        ->and($inSession->refresh()->pickingSessions()->pluck('picking_sessions.id')->all())->toBe([$existing]);
});

test('mobile scan-picks into the notes of a started picking session and refuses it before picking starts', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => true]);
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf     = mobileSessionShelf($this);
    $first     = mobileSessionNote($this, $shelf, 2);
    $second    = mobileSessionNote($this, $shelf, 1);
    $sessionId = mobileSessionOf($this->warehouse, [$first, $second]);
    $code      = $shelf->orgStock->code;
    $scan      = fn (array $body) => postGoodsOut('grp.api.mobile.picking_sessions.scan_pick', $sessionId, $body);

    $scan(['barcode' => $shelf->orgStock->barcode])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertOk();

    $scan(['barcode' => $shelf->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('result.message', "Picked 1 x $code from {$shelf->location->code} for $first->reference, 1 still to pick")
        ->assertJsonPath('result.delivery_note', ['id' => $first->id, 'reference' => $first->reference, 'state' => 'handling'])
        ->assertJsonPath('result.item.id', $first->deliveryNoteItems()->first()->id)
        ->assertJsonPath('data.pick_list.0.quantity_picked', 1)
        ->assertJsonPath('data.pick_list.0.quantity_to_pick', 2);

    $scan(['barcode' => 'NOTHERE'.Str::random(6)])->assertOk()
        ->assertJsonPath('result.status', 'not_found')
        ->assertJsonPath('result.item', null);
    $scan([])->assertUnprocessable()->assertJsonValidationErrors(['barcode']);

    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => false]);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()->assertJsonPath('data.available_actions', []);
    $scan(['barcode' => $shelf->orgStock->barcode])->assertForbidden();
});

test('mobile scan-packs a picking session once its picking is finished', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pick' => true, 'allow_scan_to_pack' => true]);
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf        = mobileSessionShelf($this);
    $deliveryNote = mobileSessionNote($this, $shelf, 2);
    $sessionId    = mobileSessionOf($this->warehouse, [$deliveryNote]);
    $line         = $deliveryNote->deliveryNoteItems()->first();
    $pack         = fn (array $body) => postGoodsOut('grp.api.mobile.picking_sessions.scan_pack', $sessionId, $body);

    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertOk();
    $pack(['barcode' => $shelf->orgStock->barcode])->assertUnprocessable()->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.picking_sessions.scan_pick', $sessionId, ['barcode' => $shelf->orgStock->barcode])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('data.state', 'picking_finished')
        ->assertJsonPath('data.pick_list', [])
        ->assertJsonPath('data.available_actions', [['key' => 'scan_pack', 'label' => 'Scan to pack']]);

    $pack(['barcode' => $shelf->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'packed')
        ->assertJsonPath('result.message', "Packed 1 x {$shelf->orgStock->code} for $deliveryNote->reference, 1 still to pack")
        ->assertJsonPath('result.item.id', $line->id)
        ->assertJsonPath('result.delivery_note.id', $deliveryNote->id);
    expect((float) $line->refresh()->packings()->sum('quantity'))->toBe(1.0);

    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pack' => false]);
    $pack(['barcode' => $shelf->orgStock->barcode])->assertForbidden();
});

test('mobile sends a waiting picking session back to picking for its picker but not for another clerk', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true]);
    $picker       = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf        = mobileSessionShelf($this);
    $deliveryNote = mobileSessionNote($this, $shelf, 2);
    $sessionId    = mobileSessionOf($this->warehouse, [$deliveryNote]);
    $line         = $deliveryNote->deliveryNoteItems()->first();
    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertOk();

    postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $line->id], ['kind' => 'warehouse', 'quantity' => 2])->assertOk()
        ->assertJsonPath('data.state', 'handling_blocked');
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()
        ->assertJsonPath('data.state', 'handling_blocked')
        ->assertJsonPath('data.state_label', 'Waiting')
        ->assertJsonPath('data.delivery_notes.0.has_waiting_items', true)
        ->assertJsonPath('data.pick_list.0.quantity_to_pick', 0)
        ->assertJsonPath('data.available_actions', [['key' => 'back_to_picking', 'label' => 'Back to picking']]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()->assertJsonPath('data.available_actions', []);
    postGoodsOut('grp.api.mobile.picking_sessions.back_to_picking', $sessionId)->assertForbidden();

    Sanctum::actingAs($picker, ['mobile']);
    postGoodsOut('grp.api.mobile.picking_sessions.back_to_picking', $sessionId)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'The picking session is back to picking.'])
        ->assertJsonPath('data.state', 'handling');
    expect($deliveryNote->refresh()->state)->toBe(DeliveryNoteStateEnum::HANDLING);
    postGoodsOut('grp.api.mobile.picking_sessions.back_to_picking', $sessionId)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile gives a picking session free trolleys of its warehouse and takes them back', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf          = mobileSessionShelf($this);
    $deliveryNote   = mobileSessionNote($this, $shelf, 1);
    $otherNote      = mobileSessionNote($this, $shelf, 1);
    $sessionId      = mobileSessionOf($this->warehouse, [$deliveryNote]);
    $trolley        = StoreTrolley::make()->handle($this->warehouse, ['name' => 'A'.Str::random(6)]);
    $busy           = StoreTrolley::make()->handle($this->warehouse, ['name' => 'B'.Str::random(6)]);
    $elsewhere      = StoreTrolley::make()->handle($otherWarehouse, ['name' => 'C'.Str::random(6)]);
    $busy->update(['current_delivery_note_id' => $otherNote->id]);
    $attach = fn (int $trolleyId) => postGoodsOut('grp.api.mobile.picking_sessions.trolleys.attach', $sessionId, ['trolley_id' => $trolleyId]);

    $attach($trolley->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Trolley added.'])
        ->assertJsonPath('data.trolleys', [['id' => $trolley->id, 'name' => $trolley->name]])
        ->assertJsonPath('data.number_trolleys', 1);
    $attach($trolley->id)->assertOk()->assertJsonPath('result.status', 'nothing_changed');
    $attach($busy->id)->assertUnprocessable()->assertJsonValidationErrors(['trolley']);
    $attach($elsewhere->id)->assertNotFound();

    deleteGoodsOut('grp.api.mobile.picking_sessions.trolleys.detach', [$sessionId, $busy->id])->assertNotFound();
    deleteGoodsOut('grp.api.mobile.picking_sessions.trolleys.detach', [$sessionId, $trolley->id])->assertOk()
        ->assertJsonPath('result.message', 'Trolley removed.')
        ->assertJsonPath('data.trolleys', [])
        ->assertJsonPath('data.number_trolleys', 0);
    expect($trolley->refresh()->picking_session_id)->toBeNull();

    PickingSession::find($sessionId)->update(['state' => PickingSessionStateEnum::PACKING_FINISHED]);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()->assertJsonPath('data.can_change_trolleys', false);
    $attach($trolley->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile adds notes to a picking session in process and removes them', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf     = mobileSessionShelf($this);
    $first     = mobileSessionNote($this, $shelf, 1);
    $second    = mobileSessionNote($this, $shelf, 1);
    $third     = mobileSessionNote($this, $shelf, 1);
    $sessionId = mobileSessionOf($this->warehouse, [$first]);
    $add       = fn (array $ids) => postGoodsOut('grp.api.mobile.picking_sessions.delivery_notes.add', $sessionId, ['delivery_note_ids' => $ids]);
    $remove    = fn (array $ids) => postGoodsOut('grp.api.mobile.picking_sessions.delivery_notes.remove', $sessionId, ['delivery_note_ids' => $ids]);

    $add([$second->id])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery notes added.'])
        ->assertJsonPath('data.number_delivery_notes', 2)
        ->assertJsonPath('data.delivery_notes.1.id', $second->id)
        ->assertJsonPath('data.delivery_notes.1.state', 'queued');
    $add([$first->id])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note_ids' => "These delivery notes can not go in a picking session: $first->reference."]);

    $remove([$second->id])->assertOk()
        ->assertJsonPath('result.message', 'Delivery notes removed.')
        ->assertJsonPath('data.number_delivery_notes', 1);
    expect($second->refresh()->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED);
    $remove([$third->id])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note_ids' => "These delivery notes are not in this picking session: $third->reference."]);

    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertOk();
    $add([$third->id])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    $remove([$first->id])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    expect($third->refresh()->pickingSessions()->exists())->toBeFalse();
});

test('mobile refuses every picking session write while one of its notes is locked in Aurora', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf     = mobileSessionShelf($this);
    $first     = mobileSessionNote($this, $shelf, 1);
    $locked    = mobileSessionNote($this, $shelf, 1);
    $sessionId = mobileSessionOf($this->warehouse, [$first, $locked]);
    $trolley   = StoreTrolley::make()->handle($this->warehouse, ['name' => 'A'.Str::random(6)]);
    $locked->update(['handled_in_aurora' => true]);

    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()->assertJsonPath('data.available_actions', []);
    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.picking_sessions.trolleys.attach', $sessionId, ['trolley_id' => $trolley->id])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.picking_sessions.delivery_notes.remove', $sessionId, ['delivery_note_ids' => [$first->id]])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);

    expect(PickingSession::find($sessionId)->state)->toBe(PickingSessionStateEnum::IN_PROCESS)
        ->and($trolley->refresh()->picking_session_id)->toBeNull();
});

test('mobile picking sessions are read by dispatching viewers and worked only by warehouse staff', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $shelf        = mobileSessionShelf($this);
    $deliveryNote = mobileSessionNote($this, $shelf, 1);
    $free         = mobileSessionNote($this, $shelf, 1);
    $sessionId    = mobileSessionOf($this->warehouse, [$deliveryNote]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id)->assertOk()->assertJsonPath('data.0.id', $sessionId);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertOk()
        ->assertJsonPath('data.can_work', false)
        ->assertJsonPath('data.can_change_trolleys', false)
        ->assertJsonPath('data.available_actions', []);
    expect(mobileSessionCandidates($this->warehouse)[$free->id])->toBeFalse();
    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertForbidden();
    postGoodsOut('grp.api.mobile.picking_sessions.delivery_notes.add', $sessionId, ['delivery_note_ids' => [$free->id]])->assertForbidden();
    postGoodsOut('grp.api.mobile.warehouses.picking_sessions.store', $this->warehouse->id, ['delivery_note_ids' => [$free->id]])->assertForbidden();

    actingAsGoodsOutUser($this->group, $otherWarehouse, [RolesEnum::DISPATCH_CLERK]);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertNotFound();
    postGoodsOut('grp.api.mobile.picking_sessions.start_picking', $sessionId)->assertNotFound();
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id)->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    PickingSession::find($sessionId)->update(['type' => PickingSessionTypeEnum::FULFILMENT]);
    getGoodsOut('grp.api.mobile.picking_sessions.show', $sessionId)->assertNotFound();
    getGoodsOut('grp.api.mobile.warehouses.picking_sessions', $this->warehouse->id)->assertOk()->assertJsonPath('pagination.total', 0);
});
