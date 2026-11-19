<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Api\Group\MyWork\IndexMobileMyWork;
use App\Actions\Dispatching\Trolley\StoreTrolley;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/MobileMyWorkHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    $this->group     = $this->organisation->group;
    $this->customer  = createCustomer($this->shop);
    $this->product   = createProduct($this->shop)[1];
    $this->order     = createOrder($this->customer, $this->product);
    $this->warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());

    SendPalletReturnNotification::shouldRun()->andReturn();
});

test('my work lists the delivery notes I pick outside a picking session or pack, in aiku shops of this warehouse', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $notAikuOrder   = myWorkNotAikuOrder();
    $me             = myWorkUser($this);
    $colleague      = createMobileAppUser($this->group);
    $note           = fn (array $attributes, ?Order $order = null, ?Warehouse $warehouse = null) => goodsOutDeliveryNote($order ?? $this->order, $warehouse ?? $this->warehouse, $attributes);

    $since    = now()->subMinutes(10)->startOfSecond();
    $toPick   = $note(['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $me->id]);
    $picking  = $note(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $me->id, 'handling_at' => $since, 'number_items' => 3]);
    $waiting  = $note(['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED, 'picker_user_id' => $me->id]);
    $toPack   = $note(['state' => DeliveryNoteStateEnum::PICKED, 'picker_user_id' => $colleague->id, 'packer_user_id' => $me->id]);
    $noPacker = $note(['state' => DeliveryNoteStateEnum::PICKED, 'picker_user_id' => $me->id]);
    $packing  = $note(['state' => DeliveryNoteStateEnum::PACKING, 'picker_user_id' => $colleague->id, 'packer_user_id' => $me->id]);

    $note([]);
    $note(['state' => DeliveryNoteStateEnum::UNASSIGNED, 'picker_user_id' => $me->id]);
    $note(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $colleague->id, 'packer_user_id' => $me->id]);
    $note(['state' => DeliveryNoteStateEnum::PICKED, 'picker_user_id' => $me->id, 'packer_user_id' => $colleague->id]);
    $note(['state' => DeliveryNoteStateEnum::PACKING, 'picker_user_id' => $me->id, 'packer_user_id' => $colleague->id]);
    $note(['state' => DeliveryNoteStateEnum::PACKED, 'picker_user_id' => $me->id, 'packer_user_id' => $me->id]);
    $note(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $me->id], warehouse: $otherWarehouse);
    $note(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $me->id], order: $notAikuOrder);
    $inSession = $note(['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $me->id]);
    myWorkSessionNote(myWorkSession($this->warehouse, $colleague, PickingSessionStateEnum::IN_PROCESS), $inSession);

    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MW'.Str::upper(Str::random(6))]);
    $picking->trolleys()->attach($trolley->id, ['group_id' => $picking->group_id, 'organisation_id' => $picking->organisation_id]);

    myWorkGet($this->warehouse)
        ->assertOk()
        ->assertJsonPath('counts', ['total' => 6, 'delivery_notes' => 6, 'picking_sessions' => 0, 'returns' => 0, 'pallet_returns' => 0]);

    expect(myWorkReferences($this->warehouse, 'delivery_note'))->toBe(myWorkSortedReferences([$toPick, $picking, $waiting, $toPack, $noPacker, $packing]));

    $rows = myWorkRowsByReference($this->warehouse);
    expect(collect($rows)->map(fn (array $row) => [$row['step'], $row['step_label']])->all())->toEqual([
        $waiting->reference  => ['waiting', 'Waiting'],
        $toPick->reference   => ['to_pick', 'To pick'],
        $picking->reference  => ['picking', 'Picking'],
        $toPack->reference   => ['to_pack', 'To pack'],
        $noPacker->reference => ['to_pack', 'To pack'],
        $packing->reference  => ['packing', 'Packing'],
    ])
        ->and($rows[$picking->reference])->toBe([
            'kind'        => 'delivery_note',
            'id'          => $picking->id,
            'reference'   => $picking->reference,
            'state'       => 'handling',
            'state_label' => 'Picking',
            'step'        => 'picking',
            'step_label'  => 'Picking',
            'type_group'  => null,
            'items'       => 3,
            'customer'    => $this->customer->name,
            'trolleys'    => [$trolley->name],
            'since'       => $since->toIso8601String(),
        ]);

    expectBadgeMatchesMyWork($this->warehouse);
});

test('my work lists the dropshipping picking sessions I start or pick, and not the notes inside them', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $me             = myWorkUser($this);
    $colleague      = createMobileAppUser($this->group);
    $since          = now()->subMinutes(7)->startOfSecond();

    $toStart = myWorkSession($this->warehouse, $me, PickingSessionStateEnum::IN_PROCESS, attributes: ['number_delivery_notes' => 1]);
    myWorkSessionNote($toStart, goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $me->id]));
    $picking = myWorkSession($this->warehouse, $me, PickingSessionStateEnum::HANDLING, attributes: ['start_at' => $since, 'number_delivery_notes' => 4]);
    $waiting = myWorkSession($this->warehouse, $me, PickingSessionStateEnum::HANDLING_BLOCKED);

    myWorkSession($this->warehouse, $me, PickingSessionStateEnum::PICKING_FINISHED);
    myWorkSession($this->warehouse, $colleague, PickingSessionStateEnum::HANDLING);
    myWorkSession($this->warehouse, $me, PickingSessionStateEnum::HANDLING, PickingSessionTypeEnum::FULFILMENT);
    myWorkSession($otherWarehouse, $me, PickingSessionStateEnum::HANDLING);

    $trolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'MW'.Str::upper(Str::random(6))]);
    $trolley->update(['picking_session_id' => $picking->id]);

    myWorkGet($this->warehouse)
        ->assertOk()
        ->assertJsonPath('counts', ['total' => 3, 'delivery_notes' => 0, 'picking_sessions' => 3, 'returns' => 0, 'pallet_returns' => 0]);

    $rows = myWorkRowsByReference($this->warehouse);
    expect(myWorkReferences($this->warehouse, 'picking_session'))->toBe(myWorkSortedReferences([$toStart, $picking, $waiting]))
        ->and($rows[$toStart->reference])->toMatchArray(['state' => 'in_process', 'step' => 'to_start', 'step_label' => 'To start', 'items' => 1, 'customer' => null, 'trolleys' => []])
        ->and($rows[$waiting->reference])->toMatchArray(['state_label' => 'Waiting', 'step' => 'waiting', 'step_label' => 'Waiting'])
        ->and($rows[$picking->reference])->toBe([
            'kind'        => 'picking_session',
            'id'          => $picking->id,
            'reference'   => $picking->reference,
            'state'       => 'handling',
            'state_label' => 'Picking',
            'step'        => 'picking',
            'step_label'  => 'Picking',
            'type_group'  => null,
            'items'       => 4,
            'customer'    => null,
            'trolleys'    => [$trolley->name],
            'since'       => $since->toIso8601String(),
        ]);

    expectBadgeMatchesMyWork($this->warehouse);
});

test('my work lists the customer returns I hold received or returning in this warehouse', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $me             = myWorkUser($this);
    $colleague      = createMobileAppUser($this->group);

    $received  = myWorkReturn($this, $me);
    $returning = myWorkReturn($this, $me, ReturnDeliveryNoteStateEnum::RETURNING);
    $other     = myWorkReturn($this, $colleague, ReturnDeliveryNoteStateEnum::RETURNING);

    $rows = myWorkRowsByReference($this->warehouse);
    expect(myWorkReferences($this->warehouse, 'return'))->toBe(myWorkSortedReferences([$received, $returning]))
        ->and($rows[$received->reference])->toMatchArray(['state' => 'received', 'step' => 'to_start', 'step_label' => 'To start'])
        ->and($rows[$returning->reference])->toBe([
            'kind'        => 'return',
            'id'          => $returning->id,
            'reference'   => $returning->reference,
            'state'       => 'returning',
            'state_label' => 'Returning to Locations',
            'step'        => 'returning',
            'step_label'  => 'Returning',
            'type_group'  => null,
            'items'       => $returning->returnDeliveryNoteItem()->count(),
            'customer'    => $this->customer->name,
            'trolleys'    => [],
            'since'       => $returning->returning_at->toIso8601String(),
        ]);

    foreach ([
        ['state' => ReturnDeliveryNoteStateEnum::RECEIVED, 'handler_user_id' => null],
        ['state' => ReturnDeliveryNoteStateEnum::RETURNED, 'handler_user_id' => $me->id],
        ['state' => ReturnDeliveryNoteStateEnum::DONE, 'handler_user_id' => $me->id],
        ['state' => ReturnDeliveryNoteStateEnum::RETURNING, 'handler_user_id' => $me->id, 'warehouse_id' => $otherWarehouse->id],
    ] as $attributes) {
        $other->update($attributes);
        expect(myWorkReferences($this->warehouse, 'return'))->toBe(myWorkSortedReferences([$received, $returning]));
    }

    $other->update(['warehouse_id' => $this->warehouse->id]);
    expect(myWorkReferences($this->warehouse, 'return'))->toBe(myWorkSortedReferences([$received, $returning, $other]));

    expectBadgeMatchesMyWork($this->warehouse);
});

test('my work lists the pallet returns I pick, and the picked ones I hold to dispatch', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $picking        = myWorkPalletReturn($this);
    $toDispatch     = myWorkPalletReturn($this, 'items');
    $other          = myWorkPalletReturn($this);
    $me             = myWorkUser($this);
    $colleague      = createMobileAppUser($this->group);

    $picking    = myWorkPalletReturnState($picking, PalletReturnStateEnum::PICKING, $me, null);
    $toDispatch = myWorkPalletReturnState($toDispatch, PalletReturnStateEnum::PICKED, $colleague, $me);

    $rows = myWorkRowsByReference($this->warehouse);
    expect(myWorkReferences($this->warehouse, 'pallet_return'))->toBe(myWorkSortedReferences([$picking, $toDispatch]))
        ->and($rows[$picking->reference])->toBe([
            'kind'        => 'pallet_return',
            'id'          => $picking->id,
            'reference'   => $picking->reference,
            'state'       => 'picking',
            'state_label' => 'Picking',
            'step'        => 'picking',
            'step_label'  => 'Picking',
            'type_group'  => 'pallets',
            'items'       => 1,
            'customer'    => $picking->fulfilmentCustomer->customer->name,
            'trolleys'    => [],
            'since'       => $picking->picking_at->toIso8601String(),
        ])
        ->and($rows[$toDispatch->reference])->toMatchArray([
            'state'       => 'picked',
            'state_label' => 'Picked',
            'step'        => 'to_dispatch',
            'step_label'  => 'To dispatch',
            'type_group'  => 'items',
            'items'       => 1,
            'since'       => $toDispatch->picked_at->toIso8601String(),
        ]);

    foreach ([
        [PalletReturnStateEnum::CONFIRMED, $me, $me, null],
        [PalletReturnStateEnum::PICKING, $colleague, $me, null],
        [PalletReturnStateEnum::PICKED, $me, $colleague, null],
        [PalletReturnStateEnum::DISPATCHED, $me, $me, null],
        [PalletReturnStateEnum::PICKING, $me, null, $otherWarehouse],
    ] as [$state, $picker, $packer, $warehouse]) {
        myWorkPalletReturnState($other, $state, $picker, $packer, $warehouse);
        expect(myWorkReferences($this->warehouse, 'pallet_return'))->toBe(myWorkSortedReferences([$picking, $toDispatch]));
    }

    myWorkPalletReturnState($other, PalletReturnStateEnum::PICKED, $colleague, $me, $this->warehouse);
    expect(myWorkReferences($this->warehouse, 'pallet_return'))->toBe(myWorkSortedReferences([$picking, $toDispatch, $other]));

    expectBadgeMatchesMyWork($this->warehouse);
});

test('my work and its badge skip the sources the user can not open, and the unassigned badge needs goods out', function () {
    $deliveryNote   = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING]);
    $pickingSession = myWorkSession($this->warehouse, $this->user, PickingSessionStateEnum::IN_PROCESS);
    $return         = myWorkReturn($this, null);
    $palletReturn   = myWorkPalletReturn($this);
    goodsOutDeliveryNote($this->order, $this->warehouse);

    $assignTo = function (User $user) use ($deliveryNote, $pickingSession, $return, $palletReturn) {
        $deliveryNote->update(['picker_user_id' => $user->id]);
        $pickingSession->update(['user_id' => $user->id]);
        $return->update(['handler_user_id' => $user->id]);
        myWorkPalletReturnState($palletReturn, PalletReturnStateEnum::PICKING, $user, null);
    };
    $kinds = fn () => collect(myWorkGet($this->warehouse)->assertOk()->json('data'))->pluck('kind')->sort()->values()->all();

    $assignTo(myWorkUser($this));
    myWorkGet($this->warehouse)->assertOk()->assertJsonPath('counts', ['total' => 4, 'delivery_notes' => 1, 'picking_sessions' => 1, 'returns' => 1, 'pallet_returns' => 1]);
    expect($kinds())->toBe(['delivery_note', 'pallet_return', 'picking_session', 'return'])
        ->and(myWorkBadges($this->warehouse)->assertOk()->json('data'))->toBe(['my_work' => 4, 'goods_out_unassigned' => 1, 'goods_in_received' => null, 'inbox_unread' => 0]);

    $assignTo(myWorkUser($this, [RolesEnum::RETURNS_CLERK], []));
    myWorkGet($this->warehouse)->assertOk()->assertJsonPath('counts', ['total' => 1, 'delivery_notes' => 0, 'picking_sessions' => 0, 'returns' => 1, 'pallet_returns' => 0]);
    expect($kinds())->toBe(['return'])
        ->and(myWorkBadges($this->warehouse)->assertOk()->json('data'))->toBe(['my_work' => 1, 'goods_out_unassigned' => null, 'goods_in_received' => null, 'inbox_unread' => 0]);

    $assignTo(myWorkUser($this, [], ["locations.{$this->warehouse->id}.view"]));
    myWorkGet($this->warehouse)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('counts', ['total' => 0, 'delivery_notes' => 0, 'picking_sessions' => 0, 'returns' => 0, 'pallet_returns' => 0]);
    expect(myWorkBadges($this->warehouse)->assertOk()->json('data'))->toBe(['my_work' => 0, 'goods_out_unassigned' => null, 'goods_in_received' => null, 'inbox_unread' => 0]);
});

test('my work puts waiting first, then the oldest, and caps the list while counting everything', function () {
    $me   = myWorkUser($this);
    $base = now()->startOfSecond();

    $queued         = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $me->id, 'queued_at' => $base->copy()->subMinutes(30)]);
    $picking        = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $me->id, 'handling_at' => $base->copy()->subMinutes(10)]);
    $waitingNote    = goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED, 'picker_user_id' => $me->id, 'handling_blocked_at' => $base->copy()->subMinute()]);
    $waitingSession = myWorkSession($this->warehouse, $me, PickingSessionStateEnum::HANDLING_BLOCKED);
    $waitingSession->update(['updated_at' => $base->copy()->subMinutes(5)]);
    $returning = myWorkReturn($this, $me, ReturnDeliveryNoteStateEnum::RETURNING);
    $returning->update(['returning_at' => $base->copy()->subMinutes(20)]);

    expect(collect(myWorkGet($this->warehouse)->assertOk()->json('data'))->pluck('reference')->all())->toBe([
        $waitingSession->reference,
        $waitingNote->reference,
        $queued->reference,
        $returning->reference,
        $picking->reference,
    ]);

    foreach (range(1, IndexMobileMyWork::MAX_ROWS) as $ignored) {
        myWorkSession($this->warehouse, $me, PickingSessionStateEnum::IN_PROCESS);
    }

    $response = myWorkGet($this->warehouse)
        ->assertOk()
        ->assertJsonCount(100, 'data')
        ->assertJsonPath('counts.total', 105)
        ->assertJsonPath('counts.picking_sessions', 101);
    expect(collect($response->json('data'))->take(2)->pluck('reference')->all())->toBe([$waitingSession->reference, $waitingNote->reference])
        ->and(myWorkBadges($this->warehouse)->assertOk()->json('data.my_work'))->toBe(105);
});

test('the unassigned badge counts the unassigned notes of aiku shops in this warehouse, like the delivery note counts', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $notAikuOrder   = myWorkNotAikuOrder();
    myWorkUser($this);
    $colleague      = createMobileAppUser($this->group);
    $before         = myWorkBadges($this->warehouse)->assertOk()->json('data.goods_out_unassigned');

    goodsOutDeliveryNote($this->order, $this->warehouse);
    goodsOutDeliveryNote($this->order, $this->warehouse);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => DeliveryNoteStateEnum::QUEUED, 'picker_user_id' => $colleague->id]);
    goodsOutDeliveryNote($notAikuOrder, $this->warehouse);
    goodsOutDeliveryNote($this->order, $otherWarehouse);

    expect(myWorkBadges($this->warehouse)->assertOk()->json('data.goods_out_unassigned'))
        ->toBe($before + 2)
        ->toBe(getGoodsOut('grp.api.mobile.warehouses.delivery_notes.counts', $this->warehouse->id)->assertOk()->json('data.buckets.unassigned'));
});

test('my work and badges are not found on a warehouse the user is not authorised in', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    myWorkUser($this);

    myWorkGet($otherWarehouse)->assertNotFound();
    myWorkBadges($otherWarehouse)->assertNotFound();
    myWorkGet($this->warehouse)->assertOk();
    myWorkBadges($this->warehouse)->assertOk();
});

test('the goods in badge counts received stock deliveries, the same number the received list shows', function () {
    myWorkUser($this, [], ["incoming.{$this->warehouse->id}.view"]);

    $badge     = myWorkBadges($this->warehouse)->assertOk()->json('data.goods_in_received');
    $listTotal = getGoodsOut('grp.api.mobile.warehouses.stock_deliveries', $this->warehouse->id, ['state' => 'received'])->assertOk()->json('meta.total');

    expect($badge)->toBeInt()->toBe($listTotal);
});
