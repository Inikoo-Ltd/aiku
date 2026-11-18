<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-51m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Dashboard;

use App\Actions\Api\Group\GoodsIn\IndexMobileReturns;
use App\Actions\Api\Group\GoodsIn\IndexMobileStockDeliveries;
use App\Actions\Api\Group\GoodsOut\WithMobileDeliveryNotes;
use App\Actions\Api\Group\Inventory\IndexMobileOrgStocks;
use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Actions\UI\Incoming\GetIncomingHubStockDeliveryWidget;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileWarehouseDashboard
{
    use AsController;
    use WithMobileDeliveryNotes;
    use WithMobileModuleAccess;

    public const int CACHE_SECONDS = 55;

    /**
     * @return array{generated_at: string, goods_out: array<string, mixed>|null, goods_in: array<string, int|null>|null, inventory: array<string, int>|null}
     */
    public function handle(Warehouse $warehouse, User $user): array
    {
        $figures = Cache::remember(
            "mobile-warehouse-dashboard:$warehouse->id",
            self::CACHE_SECONDS,
            fn () => $this->figures($warehouse, $user)
        );

        return [
            'generated_at' => $figures['generated_at'],
            'goods_out'    => self::canViewGoodsOut($user, $warehouse) ? $figures['goods_out'] : null,
            'goods_in'     => self::canViewGoodsIn($user, $warehouse) ? [
                ...$figures['goods_in'],
                'returns_to_process' => self::canViewReturns($user, $warehouse) ? $figures['returns_to_process'] : null,
            ] : null,
            'inventory'    => self::canViewInventory($user, $warehouse) ? $figures['inventory'] : null,
        ];
    }

    /**
     * @return array{generated_at: string, goods_out: array<string, mixed>, goods_in: array<string, int>, returns_to_process: int, inventory: array<string, int>}
     */
    private function figures(Warehouse $warehouse, User $user): array
    {
        return [
            'generated_at'       => now()->toIso8601String(),
            'goods_out'          => $this->goodsOutFigures($warehouse, $user),
            'goods_in'           => $this->goodsInFigures($warehouse),
            'returns_to_process' => $this->returnsToProcess($warehouse),
            'inventory'          => $this->inventoryFigures($warehouse),
        ];
    }

    /**
     * @return array{unassigned: int, handling: int, handling_blocked: int, picked: int, packed: int, in_warehouse: int, dispatched_today: array{count: int, yesterday_by_now: int, same_day_last_week: int}}
     */
    private function goodsOutFigures(Warehouse $warehouse, User $user): array
    {
        $stateCounts = $this->deliveryNotesQuery($warehouse, 'in_warehouse', 'all', null, $user)
            ->reorder()
            ->toBase()
            ->select('delivery_notes.state')
            ->selectRaw('count(*) as number_delivery_notes')
            ->groupBy('delivery_notes.state')
            ->pluck('number_delivery_notes', 'state')
            ->map(fn ($count) => (int) $count);

        $now        = now($warehouse->organisation->timezone?->name ?? 'UTC');
        $todayStart = $now->copy()->startOfDay();
        $dispatched = fn (Carbon $from, Carbon $to): int => $this->deliveryNotesQuery($warehouse, 'dispatched', 'all', null, $user)
            ->reorder()
            ->toBase()
            ->whereBetween('delivery_notes.dispatched_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->count();

        return [
            'unassigned'       => $stateCounts->get(DeliveryNoteStateEnum::UNASSIGNED->value, 0),
            'handling'         => $stateCounts->get(DeliveryNoteStateEnum::HANDLING->value, 0),
            'handling_blocked' => $stateCounts->get(DeliveryNoteStateEnum::HANDLING_BLOCKED->value, 0),
            'picked'           => $stateCounts->get(DeliveryNoteStateEnum::PICKED->value, 0),
            'packed'           => $stateCounts->get(DeliveryNoteStateEnum::PACKED->value, 0),
            'in_warehouse'     => $stateCounts->sum(),
            'dispatched_today' => [
                'count'              => $dispatched($todayStart, $now),
                'yesterday_by_now'   => $dispatched($todayStart->copy()->subDay(), $now->copy()->subDay()),
                'same_day_last_week' => $dispatched($todayStart->copy()->subWeek(), $now->copy()->subWeek()),
            ],
        ];
    }

    /**
     * @return array{arriving: int, received: int, checked: int, booking_in: int}
     */
    private function goodsInFigures(Warehouse $warehouse): array
    {
        $widget  = GetIncomingHubStockDeliveryWidget::run($warehouse, array_merge(...array_values(IndexMobileStockDeliveries::STOCK_DELIVERY_SOURCES)));
        $figure  = fn (string $metric): int => (int) Arr::get($widget, "data._global.$metric.value", 0);

        return [
            'arriving'   => $figure('arriving'),
            'received'   => $figure('received'),
            'checked'    => $figure('checked'),
            'booking_in' => $figure('booking_in'),
        ];
    }

    private function returnsToProcess(Warehouse $warehouse): int
    {
        return IndexMobileReturns::returnsQuery($warehouse, IndexMobileReturns::TO_PROCESS)->count();
    }

    /**
     * @return array{number_current_org_stocks: int, below_minimum_stock: int, negative_stocks: int, number_abnormalities: int}
     */
    private function inventoryFigures(Warehouse $warehouse): array
    {
        $inventoryStats = $warehouse->organisation->inventoryStats;

        return [
            'number_current_org_stocks' => (int) $inventoryStats?->number_current_org_stocks,
            'below_minimum_stock'       => IndexMobileOrgStocks::numberBelowMinimumStock($warehouse),
            'negative_stocks'           => IndexMobileOrgStocks::numberNegativeStocks($warehouse),
            'number_abnormalities'      => (int) $inventoryStats?->number_org_stocks_state_abnormality,
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->authorisedWarehouses()->whereKey($warehouse->id)->exists(), 404);

        return response()->json(['data' => $this->handle($warehouse, $user)]);
    }
}
