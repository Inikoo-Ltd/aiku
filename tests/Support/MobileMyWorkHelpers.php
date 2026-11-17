<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\GoodsIn\ReturnDeliveryNote\ProcessReturnDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/MobileApiHelpers.php';
require_once __DIR__.'/MobileGoodsOutHelpers.php';
require_once __DIR__.'/DispatchingHelpers.php';
require_once __DIR__.'/MobileFulfilmentOutboundHelpers.php';

/**
 * @param array<int, \App\Enums\SysAdmin\Authorisation\RolesEnum> $roles
 * @param array<int, string>|null $permissions
 */
function myWorkUser(object $ctx, array $roles = [], ?array $permissions = null): User
{
    $warehouseId = $ctx->warehouse->id;

    return actingAsGoodsOutUser($ctx->group, $ctx->warehouse, $roles, $permissions ?? [
        "dispatching.$warehouseId.view",
        "returns.$warehouseId",
        "fulfilment.$warehouseId.view",
    ]);
}

function myWorkNotAikuOrder(): Order
{
    [, , $shop] = createOwnShop('mobile-my-work-not-aiku');
    $order      = createOrder(createCustomer($shop), createProduct($shop)[1]);
    $shop->update(['is_aiku' => false]);

    return $order;
}

/**
 * @param array<string, mixed> $attributes
 */
function myWorkSession(Warehouse $warehouse, User $user, PickingSessionStateEnum $state, PickingSessionTypeEnum $type = PickingSessionTypeEnum::DROPSHIPPING, array $attributes = []): PickingSession
{
    return $warehouse->pickingSessions()->create(array_merge([
        'group_id'        => $warehouse->group_id,
        'organisation_id' => $warehouse->organisation_id,
        'reference'       => 'MW'.Str::upper(Str::random(8)),
        'user_id'         => $user->id,
        'state'           => $state,
        'type'            => $type,
    ], $attributes))->refresh();
}

function myWorkSessionNote(PickingSession $pickingSession, DeliveryNote $deliveryNote): void
{
    $pickingSession->deliveryNotes()->attach($deliveryNote->id, [
        'group_id'        => $pickingSession->group_id,
        'organisation_id' => $pickingSession->organisation_id,
    ]);
}

function myWorkReturn(object $ctx, ?User $handler, ReturnDeliveryNoteStateEnum $state = ReturnDeliveryNoteStateEnum::RECEIVED): ReturnDeliveryNote
{
    [$deliveryNote, $deliveryNoteItem] = handlingDeliveryNoteWithPicking($ctx);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::DISPATCHED]);
    $deliveryNoteItem->update(['quantity_dispatched' => 3]);

    $returnDeliveryNote = ProcessReturnDeliveryNote::make()->handle($deliveryNote->refresh(), []);
    $returnDeliveryNote->update([
        'state'           => $state,
        'handler_user_id' => $handler?->id,
        'returning_at'    => $state === ReturnDeliveryNoteStateEnum::RETURNING ? now()->subMinutes(3)->startOfSecond() : null,
    ]);

    return $returnDeliveryNote->refresh();
}

function myWorkPalletReturn(object $ctx, string $typeGroup = 'pallets'): PalletReturn
{
    $fulfilmentCustomer = mfoCustomer(mfoFulfilment($ctx->organisation, $ctx->warehouse));
    $pallet             = mfoPallet($fulfilmentCustomer, $ctx->warehouse);

    if ($typeGroup === 'items') {
        return mfoItemsReturn($fulfilmentCustomer, $ctx->warehouse, [[mfoStock($pallet, mfoStoredItem($fulfilmentCustomer), 5), 2]]);
    }

    return mfoPalletReturn($fulfilmentCustomer, $ctx->warehouse, [$pallet]);
}

function myWorkPalletReturnState(PalletReturn $palletReturn, PalletReturnStateEnum $state, ?User $picker, ?User $packer, ?Warehouse $warehouse = null): PalletReturn
{
    $palletReturn->update([
        'state'          => $state,
        'picker_user_id' => $picker?->id,
        'packer_user_id' => $packer?->id,
        'warehouse_id'   => $warehouse?->id ?? $palletReturn->warehouse_id,
        'picking_at'     => now()->subMinutes(8)->startOfSecond(),
        'picked_at'      => $state === PalletReturnStateEnum::PICKING ? null : now()->subMinutes(4)->startOfSecond(),
    ]);

    return $palletReturn->refresh();
}

function myWorkGet(Warehouse $warehouse): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.my_work', $warehouse->id);
}

function myWorkBadges(Warehouse $warehouse): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.badges', $warehouse->id);
}

/**
 * @return array<int, string>
 */
function myWorkReferences(Warehouse $warehouse, string $kind): array
{
    return collect(myWorkGet($warehouse)->assertOk()->json('data'))
        ->where('kind', $kind)
        ->pluck('reference')
        ->sort()
        ->values()
        ->all();
}

/**
 * @param array<int, DeliveryNote|PickingSession|ReturnDeliveryNote|PalletReturn> $models
 * @return array<int, string>
 */
function myWorkSortedReferences(array $models): array
{
    return collect($models)->pluck('reference')->sort()->values()->all();
}

/**
 * @return array<string, array<string, mixed>>
 */
function myWorkRowsByReference(Warehouse $warehouse): array
{
    return collect(myWorkGet($warehouse)->assertOk()->json('data'))->keyBy('reference')->all();
}

function expectBadgeMatchesMyWork(Warehouse $warehouse): void
{
    expect(myWorkBadges($warehouse)->assertOk()->json('data.my_work'))
        ->toBe(myWorkGet($warehouse)->assertOk()->json('counts.total'));
}
