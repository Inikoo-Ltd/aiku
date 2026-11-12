<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait WithMobileLocationsAccess
{
    public const int WEB_MAX_PER_PAGE = 150;

    public static function canViewLocations(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id
            && $user->authTo(["locations.$warehouse->id.view", "supervisor-locations.$warehouse->id"]);
    }

    protected function ensureCanViewLocations(User $user, Warehouse $warehouse): void
    {
        abort_unless(self::canViewLocations($user, $warehouse), 403);
    }

    public static function canEditStocks(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id
            && $user->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($warehouse->organisation));
    }

    /**
     * The location edit rights the web Location page checks before moving or removing its SKOs
     * (WithWarehouseEditAuthorisation).
     */
    public static function canEditLocations(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id
            && $user->authTo(["supervisor-locations.$warehouse->id", "locations.$warehouse->id.edit"]);
    }

    /**
     * The web table actions read their search, sort and paging from the request, under the
     * names the web tables use (filter[global], sort, perPage, page).
     *
     * @param array{q?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $parameters
     */
    protected function useWebTableParameters(array $parameters): void
    {
        $search = trim((string) ($parameters['q'] ?? ''));

        request()->headers->set('Accept', 'application/json');
        request()->merge(array_filter([
            'filter'  => $search === '' ? null : ['global' => $search],
            'sort'    => $parameters['sort'] ?? null,
            'perPage' => min((int) ($parameters['per_page'] ?? 0), self::WEB_MAX_PER_PAGE) ?: null,
            'page'    => $parameters['page'] ?? null,
        ]));
    }

    /**
     * @param Closure(): LengthAwarePaginator $fetchPage
     */
    protected function allWebPages(Closure $fetchPage, int $pageLimit = 20): Collection
    {
        $rows = collect();
        $page = 1;

        do {
            request()->merge(['page' => $page, 'perPage' => self::WEB_MAX_PER_PAGE]);
            $paginator = $fetchPage();
            $rows      = $rows->concat($paginator->items());
            $page++;
        } while ($page <= $paginator->lastPage() && $page <= $pageLimit);

        return $rows;
    }

    /**
     * @param LengthAwarePaginator $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    /**
     * The web list selects only the columns its table shows; the app also needs the status,
     * area and slot counts, so each listed location is read in full, in the listed order.
     *
     * @param iterable<Location> $listed
     * @return array<int, array<string, mixed>>
     */
    protected function locationSummaries(iterable $listed): array
    {
        $ids       = collect($listed)->pluck('id')->all();
        $locations = Location::with(['stats', 'warehouseArea'])->findMany($ids)->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $locations->get($id))
            ->filter()
            ->map(fn (Location $location) => $this->locationSummary($location))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function locationSummary(Location $location): array
    {
        $area  = $location->warehouseArea;
        $stats = $location->stats;

        return [
            'id'                       => $location->id,
            'slug'                     => $location->slug,
            'code'                     => $location->code,
            'status'                   => $location->status->value,
            'area'                     => $area ? [
                'id'   => $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ] : null,
            'allow_stocks'             => (bool) $location->allow_stocks,
            'allow_fulfilment'         => (bool) $location->allow_fulfilment,
            'allow_dropshipping'       => (bool) $location->allow_dropshipping,
            'is_goods_out'             => (bool) $location->is_goods_out,
            'keep_empty_org_stocks'    => (bool) $location->keep_empty_org_stocks,
            'max_weight'               => $location->max_weight === null ? null : (float) $location->max_weight,
            'max_volume'               => $location->max_volume === null ? null : (float) $location->max_volume,
            'number_org_stock_slots'   => (int) ($stats?->number_org_stock_slots ?? 0),
            'number_empty_stock_slots' => (int) ($stats?->number_empty_stock_slots ?? 0),
            'number_pallets'           => (int) ($stats?->number_pallets ?? 0),
            'stock_value'              => (float) $location->stock_value,
            'audited_at'               => $location->audited_at?->toIso8601String(),
        ];
    }
}
