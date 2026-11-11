<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\Location\UI\IndexLocations;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileLocations
{
    use AsController;
    use WithMobileLocationsAccess;

    public const array WEB_SORTS = ['code', 'max_weight', 'max_volume', 'number_org_stock_slots', 'number_empty_stock_slots'];
    public const array APP_SORTS = ['stock_value'];
    public const array SCOPES = ['stocks' => 'allow_stocks', 'fulfilment' => 'allow_fulfilment', 'dropshipping' => 'allow_dropshipping'];
    public const array OCCUPANCIES = ['empty', 'partially_empty', 'goods_out', 'keep_empty_org_stocks'];
    public const array APP_ONLY_FILTERS = ['area_ids', 'statuses', 'scopes', 'occupancies'];

    /**
     * The web locations table (IndexLocations) answers everything it can: code search, the
     * empty and partially empty lists, one area and its sorts. Only the app's extra filters
     * (several areas, status, scope, occupancy, an area together with an occupancy tab, and
     * sorting by stock value) are answered here.
     *
     * @param array<string, mixed> $filters
     */
    public function handle(Warehouse $warehouse, array $filters, ActionRequest $request): LengthAwarePaginator
    {
        $this->useWebTableParameters($filters);

        if (!$this->webTableCovers($filters)) {
            return $this->appFilteredLocations($warehouse, $filters);
        }

        $webTable = IndexLocations::make();

        if ($areaId = Arr::get($filters, 'area_id')) {
            return $webTable->handle(WarehouseArea::findOrFail($areaId));
        }

        return match (Arr::get($filters, 'occupancy')) {
            'empty'           => $webTable->inWarehouseAllEmpty($warehouse->organisation, $warehouse, $request),
            'partially_empty' => $webTable->inWarehousePartialEmpty($warehouse->organisation, $warehouse, $request),
            default           => $webTable->handle($warehouse),
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function webTableCovers(array $filters): bool
    {
        if (collect(self::APP_ONLY_FILTERS)->contains(fn (string $key) => !empty($filters[$key]))) {
            return false;
        }
        if (!empty($filters['area_id']) && !empty($filters['occupancy'])) {
            return false;
        }

        return in_array(ltrim((string) ($filters['sort'] ?? 'code'), '-'), self::WEB_SORTS, true);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function appFilteredLocations(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $search      = trim((string) ($filters['q'] ?? ''));
        $areaIds     = array_values(array_filter([...($filters['area_ids'] ?? []), $filters['area_id'] ?? null]));
        $statuses    = $filters['statuses'] ?? [];
        $scopes      = $filters['scopes'] ?? [];
        $occupancies = $filters['occupancies'] ?? [];
        $sort        = (string) ($filters['sort'] ?? 'code');
        $sortColumn  = ltrim($sort, '-');
        $direction   = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return Location::query()
            ->where('locations.warehouse_id', $warehouse->id)
            ->leftJoin('location_stats', 'location_stats.location_id', 'locations.id')
            ->when($areaIds, fn (Builder $query) => $query->whereIn('locations.warehouse_area_id', $areaIds))
            ->when($statuses, fn (Builder $query) => $query->whereIn('locations.status', $statuses))
            ->when($search !== '', fn (Builder $query) => $query->whereStartWith('locations.code', $search))
            ->when($filters['occupancy'] ?? null, fn (Builder $query, string $occupancy) => $this->whereOccupancy($query, $occupancy))
            ->when($scopes, fn (Builder $query) => $query->where(function (Builder $query) use ($scopes) {
                foreach ($scopes as $scope) {
                    $query->orWhere('locations.'.self::SCOPES[$scope], true);
                }
            }))
            ->when($occupancies, fn (Builder $query) => $query->where(function (Builder $query) use ($occupancies) {
                foreach ($occupancies as $occupancy) {
                    $query->orWhere(fn (Builder $query) => $this->whereOccupancy($query, $occupancy));
                }
            }))
            ->select('locations.id')
            ->when($sortColumn !== 'code', fn (Builder $query) => $query->orderByRaw(
                (in_array($sortColumn, ['number_org_stock_slots', 'number_empty_stock_slots'], true) ? 'location_stats.' : 'locations.').$sortColumn." $direction nulls last"
            ))
            ->orderBy('locations.code', $sortColumn === 'code' ? $direction : 'asc')
            ->paginate(
                perPage: min((int) ($filters['per_page'] ?? config('ui.table.records_per_page')), self::WEB_MAX_PER_PAGE),
                page: max((int) ($filters['page'] ?? 1), 1)
            );
    }

    /**
     * The empty and partially empty rules are the ones IndexLocations uses for its two lists.
     */
    private function whereOccupancy(Builder $query, string $occupancy): Builder
    {
        return match ($occupancy) {
            'empty'                 => $query
                ->where('location_stats.number_org_stock_slots', '>', 0)
                ->whereColumn('location_stats.number_empty_stock_slots', '>=', 'location_stats.number_org_stock_slots'),
            'partially_empty'       => $query
                ->where('location_stats.number_empty_stock_slots', '>', 0)
                ->whereColumn('location_stats.number_empty_stock_slots', '<', 'location_stats.number_org_stock_slots'),
            'goods_out'             => $query->where('locations.is_goods_out', true),
            'keep_empty_org_stocks' => $query->where('locations.keep_empty_org_stocks', true),
        };
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $warehouse);

        $sorts = collect([...self::WEB_SORTS, ...self::APP_SORTS])->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all();

        $filters = Validator::make($request->query(), [
            'area_id'       => ['sometimes', 'nullable', 'integer', Rule::exists('warehouse_areas', 'id')->where('warehouse_id', $warehouse->id)],
            'area_ids'      => ['sometimes', 'array', 'max:200'],
            'area_ids.*'    => ['integer', Rule::exists('warehouse_areas', 'id')->where('warehouse_id', $warehouse->id)],
            'q'             => ['sometimes', 'nullable', 'string', 'max:64'],
            'occupancy'     => ['sometimes', 'nullable', Rule::in(['empty', 'partially_empty'])],
            'occupancies'   => ['sometimes', 'array'],
            'occupancies.*' => [Rule::in(self::OCCUPANCIES)],
            'statuses'      => ['sometimes', 'array'],
            'statuses.*'    => [Rule::enum(LocationStatusEnum::class)],
            'scopes'        => ['sometimes', 'array'],
            'scopes.*'      => [Rule::in(array_keys(self::SCOPES))],
            'sort'          => ['sometimes', 'nullable', Rule::in($sorts)],
            'per_page'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'          => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $locations = $this->handle($warehouse, $filters, $request);

        return response()->json([
            'data' => $this->locationSummaries($locations->items()),
            'meta' => $this->paginationMeta($locations),
        ]);
    }
}
