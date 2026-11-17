<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\MyWork;

use App\Actions\Api\Group\Fulfilment\IndexMobilePalletReturns;
use App\Actions\Api\Group\GetMobileWarehouses;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

trait WithMobileMyWork
{
    public const array SOURCE_KINDS = [
        'delivery_notes'   => 'delivery_note',
        'picking_sessions' => 'picking_session',
        'returns'          => 'return',
        'pallet_returns'   => 'pallet_return',
    ];

    public const array STEPS = [
        'delivery_note'   => [
            'queued'           => 'to_pick',
            'handling'         => 'picking',
            'handling_blocked' => 'waiting',
            'picked'           => 'to_pack',
            'packing'          => 'packing',
        ],
        'picking_session' => [
            'in_process'       => 'to_start',
            'handling'         => 'picking',
            'handling_blocked' => 'waiting',
        ],
        'return'          => [
            'received'  => 'to_start',
            'returning' => 'returning',
        ],
        'pallet_return'   => [
            'picking' => 'picking',
            'picked'  => 'to_dispatch',
        ],
    ];

    public const array STATE_STARTED_AT = [
        'delivery_note'   => [
            'queued'           => 'queued_at',
            'handling'         => 'handling_at',
            'handling_blocked' => 'handling_blocked_at',
            'picked'           => 'picked_at',
            'packing'          => 'packing_at',
        ],
        'picking_session' => [
            'handling' => 'start_at',
        ],
        'return'          => [
            'returning' => 'returning_at',
        ],
        'pallet_return'   => [
            'picking' => 'picking_at',
            'picked'  => 'picked_at',
        ],
    ];

    protected function ensureAuthorisedWarehouse(User $user, Warehouse $warehouse): void
    {
        abort_unless($user->authorisedWarehouses()->whereKey($warehouse->id)->exists(), 404);
    }

    /**
     * @return array<string, bool>
     */
    protected function visibleSources(User $user, Warehouse $warehouse): array
    {
        $can = app(GetMobileWarehouses::class)->abilities($user, $warehouse);

        return [
            'delivery_notes'   => $can['view_goods_out'],
            'picking_sessions' => $can['view_goods_out'],
            'returns'          => $can['view_returns'],
            'pallet_returns'   => IndexMobilePalletReturns::canViewFulfilment($user, $warehouse),
        ];
    }

    /**
     * @param array<string, bool> $visibleSources
     * @return array{total: int, delivery_notes: int, picking_sessions: int, returns: int, pallet_returns: int}
     */
    protected function myWorkCounts(Warehouse $warehouse, User $user, array $visibleSources): array
    {
        $counts = ['total' => 0];
        foreach (array_keys(self::SOURCE_KINDS) as $source) {
            $counts[$source] = $visibleSources[$source] ? $this->myWorkQuery($source, $warehouse, $user)->count() : 0;
            $counts['total'] += $counts[$source];
        }

        return $counts;
    }

    protected function myWorkQuery(string $source, Warehouse $warehouse, User $user): Builder
    {
        return match ($source) {
            'delivery_notes'   => $this->myDeliveryNotesQuery($warehouse, $user),
            'picking_sessions' => $this->myPickingSessionsQuery($warehouse, $user),
            'returns'          => $this->myReturnsQuery($warehouse, $user),
            'pallet_returns'   => $this->myPalletReturnsQuery($warehouse, $user),
        };
    }

    protected function goodsOutUnassignedCount(Warehouse $warehouse): int
    {
        return $this->aikuShopDeliveryNotesQuery($warehouse)
            ->where('delivery_notes.state', DeliveryNoteStateEnum::UNASSIGNED)
            ->count();
    }

    protected function aikuShopDeliveryNotesQuery(Warehouse $warehouse): Builder
    {
        return DeliveryNote::query()
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereExists(fn (QueryBuilder $query) => $query
                ->select(DB::raw(1))
                ->from('shops')
                ->whereColumn('shops.id', 'delivery_notes.shop_id')
                ->where('shops.is_aiku', true));
    }

    protected function myDeliveryNotesQuery(Warehouse $warehouse, User $user): Builder
    {
        return $this->aikuShopDeliveryNotesQuery($warehouse)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('delivery_notes.picker_user_id', $user->id)
                    ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED])
                    ->whereDoesntHave('pickingSessions'))
                ->orWhere(fn (Builder $query) => $query
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::PACKING)
                    ->where('delivery_notes.packer_user_id', $user->id))
                ->orWhere(fn (Builder $query) => $query
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::PICKED)
                    ->where(fn (Builder $query) => $query
                        ->where('delivery_notes.packer_user_id', $user->id)
                        ->orWhere(fn (Builder $query) => $query
                            ->whereNull('delivery_notes.packer_user_id')
                            ->where('delivery_notes.picker_user_id', $user->id)))));
    }

    protected function myPickingSessionsQuery(Warehouse $warehouse, User $user): Builder
    {
        return PickingSession::query()
            ->where('picking_sessions.warehouse_id', $warehouse->id)
            ->where('picking_sessions.user_id', $user->id)
            ->where(fn (Builder $query) => $query->whereNull('picking_sessions.type')->orWhere('picking_sessions.type', PickingSessionTypeEnum::DROPSHIPPING->value))
            ->whereIn('picking_sessions.state', array_keys(self::STEPS['picking_session']));
    }

    protected function myReturnsQuery(Warehouse $warehouse, User $user): Builder
    {
        return ReturnDeliveryNote::query()
            ->where('return_delivery_notes.warehouse_id', $warehouse->id)
            ->where('return_delivery_notes.handler_user_id', $user->id)
            ->whereIn('return_delivery_notes.state', [ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::RETURNING]);
    }

    protected function myPalletReturnsQuery(Warehouse $warehouse, User $user): Builder
    {
        return PalletReturn::query()
            ->where('pallet_returns.warehouse_id', $warehouse->id)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('pallet_returns.state', PalletReturnStateEnum::PICKING)
                    ->where('pallet_returns.picker_user_id', $user->id))
                ->orWhere(fn (Builder $query) => $query
                    ->where('pallet_returns.state', PalletReturnStateEnum::PICKED)
                    ->where('pallet_returns.packer_user_id', $user->id)));
    }

    protected function stateStartedAtSql(string $kind, string $table): string
    {
        $cases = collect(self::STATE_STARTED_AT[$kind])
            ->map(fn (string $column, string $state) => "WHEN '$state' THEN $table.$column")
            ->implode(' ');

        return "COALESCE(CASE $table.state $cases END, $table.updated_at)";
    }

    protected function waitingFirstSql(string $kind, string $table): string
    {
        $waitingStates = collect(self::STEPS[$kind])
            ->filter(fn (string $step) => $step === 'waiting')
            ->keys()
            ->map(fn (string $state) => "'$state'")
            ->implode(', ');

        return $waitingStates === '' ? '1' : "CASE WHEN $table.state IN ($waitingStates) THEN 0 ELSE 1 END";
    }
}
