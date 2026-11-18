<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionMaterials;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestUnitEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\Production\ProductionMaterialRequestItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * What the factory asked the warehouse for: the open lines to take to the factory, urgent
 * first, and the done ones with who brought what and when.
 */
class IndexProductionMaterialRequestItems extends OrgAction
{
    public const string OPEN = 'open';
    public const string DONE = 'done';

    private string $scope = self::OPEN;

    /**
     * Shelves a material can be taken from for the factory, best first: never the factory
     * itself nor a partner's goods out bay. Needs production_material_request_items and
     * productions in scope.
     */
    private const string SHELVES_SQL = 'from location_org_stocks shelf
        join locations on locations.id = shelf.location_id
        where shelf.org_stock_id = production_material_request_items.org_stock_id
            and shelf.location_id is distinct from productions.factory_location_id
            and not locations.is_goods_out
            and shelf.quantity > 0';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "inventory.{$this->organisation->id}.view",
            "org-supervisor.{$this->organisation->id}",
            "warehouses-view.{$this->organisation->id}",
            "dispatching.{$this->warehouse->id}.view",
        ]) || $this->warehouse->canBeWorkedInBy($request->user());
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = self::OPEN;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, self::OPEN);
    }

    public function done(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = self::DONE;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, self::DONE);
    }

    public function handle(Warehouse $warehouse, string $scope = self::OPEN, string $prefix = 'production_materials'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        $query = $scope === self::DONE ? self::doneItems($warehouse) : self::pendingItems($warehouse)->orderByDesc('production_material_request_items.is_urgent');

        return QueryBuilder::for($query)
            ->join('org_stocks', 'org_stocks.id', 'production_material_request_items.org_stock_id')
            ->leftJoin('locations as factory', 'factory.id', 'productions.factory_location_id')
            ->leftJoin('production_drop_points', 'production_drop_points.id', 'production_material_request_items.production_drop_point_id')
            ->leftJoin('users as asker', 'asker.id', 'production_material_requests.submitted_by_user_id')
            ->leftJoin('users as bringer', 'bringer.id', 'production_material_request_items.delivered_by_user_id')
            ->select([
                'production_material_request_items.*',
                'production_material_requests.plan_date',
                'production_material_requests.submitted_at',
                'productions.code as production_code',
                'productions.factory_location_id',
                'factory.code as factory_location_code',
                'production_drop_points.name as drop_point_name',
                DB::raw('coalesce(asker.contact_name, asker.username) as asked_by'),
                DB::raw('coalesce(bringer.contact_name, bringer.username) as brought_by'),
                DB::raw('coalesce(production_material_request_items.delivered_at, production_material_request_items.short_at) as done_at'),
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                DB::raw('(select locations.code '.self::SHELVES_SQL.' order by shelf.picking_priority nulls last, shelf.quantity desc limit 1) as location_code'),
                DB::raw('(select locations.sort_code '.self::SHELVES_SQL.' order by shelf.picking_priority nulls last, shelf.quantity desc limit 1) as location_sort_code'),
                DB::raw('coalesce((select sum(shelf.quantity) '.self::SHELVES_SQL.'), 0) as warehouse_skos'),
            ])
            ->defaultSort($scope === self::DONE ? '-done_at' : 'submitted_at')
            ->allowedSorts(['location_sort_code', 'org_stock_code', 'submitted_at', 'done_at', 'drop_point_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (ProductionMaterialRequestItem $item) => self::row($item, $scope));
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(ProductionMaterialRequestItem $item, string $scope): array
    {
        $remaining = $item->skos_to_bring === null ? null : max(0, round((float) $item->skos_to_bring - (float) $item->delivered_skos, 3));

        return [
            'id'                 => $item->id,
            'submitted_at'       => $item->submitted_at,
            'asked_by'           => $item->asked_by,
            'production_code'    => $item->production_code,
            'to_location'        => $item->factory_location_code,
            'drop_point'         => $item->drop_point_name,
            'is_urgent'          => $item->is_urgent,
            'note'               => $item->note,
            'org_stock_code'     => $item->org_stock_code,
            'org_stock_name'     => $item->org_stock_name,
            'location_code'      => $item->location_code,
            'bring'              => self::bringLabel($item),
            'skos_to_bring'      => $item->skos_to_bring === null ? null : (float) $item->skos_to_bring,
            'remaining_skos'     => $remaining,
            'delivered_skos'     => (float) $item->delivered_skos,
            'unbooked_skos'      => (float) $item->unbooked_skos,
            'warehouse_skos'     => (float) $item->warehouse_skos,
            'warehouse_short'    => $remaining !== null && (float) $item->warehouse_skos < $remaining,
            'job_orders'         => $item->job_orders ?? [],
            'state'              => $item->state->value,
            'state_label'        => ProductionMaterialRequestItemStateEnum::labels()[$item->state->value],
            'short_reason'       => $item->short_reason,
            'brought_by'         => $item->brought_by,
            'done_at'            => $item->done_at,
            'minutes_to_done'    => $item->done_at && $item->submitted_at ? (int) round(abs(strtotime($item->done_at) - strtotime($item->submitted_at)) / 60) : null,
            'from_locations'     => $scope === self::OPEN ? self::fromLocations($item) : [],
        ];
    }

    /**
     * What the factory asked for, in its words: "3 Boxes", "1 Pallets", "All there is", or the SKOs of the morning check.
     */
    public static function bringLabel(ProductionMaterialRequestItem $item): string
    {
        if ($item->requested_unit === ProductionMaterialRequestUnitEnum::ALL) {
            return ProductionMaterialRequestUnitEnum::labels()['all'];
        }

        $unit     = $item->requested_unit?->value ?? 'sko';
        $quantity = (float) ($item->requested_unit ? $item->requested_quantity : $item->skos_to_bring);
        $labels   = $quantity == 1 ? ProductionMaterialRequestUnitEnum::singularLabels() : ProductionMaterialRequestUnitEnum::labels();

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.').' '.$labels[$unit];
    }

    public static function pendingItems(Warehouse $warehouse): Builder
    {
        return ProductionMaterialRequestItem::query()
            ->join('production_material_requests', 'production_material_requests.id', 'production_material_request_items.production_material_request_id')
            ->join('productions', 'productions.id', 'production_material_requests.production_id')
            ->where('production_material_requests.warehouse_id', $warehouse->id)
            ->where('production_material_requests.state', ProductionMaterialRequestStateEnum::SUBMITTED)
            ->where('production_material_request_items.state', ProductionMaterialRequestItemStateEnum::PENDING);
    }

    public static function doneItems(Warehouse $warehouse): Builder
    {
        return ProductionMaterialRequestItem::query()
            ->join('production_material_requests', 'production_material_requests.id', 'production_material_request_items.production_material_request_id')
            ->join('productions', 'productions.id', 'production_material_requests.production_id')
            ->where('production_material_requests.warehouse_id', $warehouse->id)
            ->whereIn('production_material_request_items.state', [ProductionMaterialRequestItemStateEnum::DELIVERED, ProductionMaterialRequestItemStateEnum::SHORT]);
    }

    /**
     * @return array<int, array{location_org_stock_id: int, code: string, quantity: float}>
     */
    private static function fromLocations(ProductionMaterialRequestItem $item): array
    {
        return DB::table('location_org_stocks as shelf')
            ->join('locations', 'locations.id', 'shelf.location_id')
            ->where('shelf.org_stock_id', $item->org_stock_id)
            ->when($item->factory_location_id, fn ($query, $factoryLocationId) => $query->where('shelf.location_id', '!=', $factoryLocationId))
            ->where('locations.is_goods_out', false)
            ->where('shelf.quantity', '>', 0)
            ->orderByRaw('shelf.picking_priority nulls last')
            ->orderByDesc('shelf.quantity')
            ->limit(5)
            ->get(['shelf.id as location_org_stock_id', 'locations.code', 'shelf.quantity'])
            ->map(fn ($row) => [
                'location_org_stock_id' => (int) $row->location_org_stock_id,
                'code'                  => $row->code,
                'quantity'              => (float) $row->quantity,
            ])
            ->all();
    }

    public function tableStructure(string $scope, string $prefix = 'production_materials'): Closure
    {
        return function (InertiaTable $table) use ($scope, $prefix) {
            $table->name($prefix)->pageName($prefix.'Page');
            $table->withGlobalSearch();
            $table->withLabelRecord([__('material'), __('materials')]);
            $table->withEmptyState([
                'icons' => ['fal fa-industry'],
                'title' => $scope === self::DONE ? __('Nothing brought to production yet') : __('Production is waiting for nothing'),
            ]);

            $table->column(key: 'org_stock_code', label: __('SKO'), canBeHidden: false, sortable: true);
            $table->column(key: 'bring', label: __('Bring'), canBeHidden: false);
            $table->column(key: 'drop_point_name', label: __('To'), canBeHidden: false, sortable: true);
            $table->column(key: 'submitted_at', label: __('Asked'), canBeHidden: false, sortable: true);
            if ($scope === self::DONE) {
                $table->column(key: 'done_at', label: __('Done'), canBeHidden: false, sortable: true);
                $table->defaultSort('-done_at');
            } else {
                $table->column(key: 'location_sort_code', label: __('From'), canBeHidden: false, sortable: true);
                $table->column(key: 'actions', label: __('To do actions'), canBeHidden: false);
                $table->defaultSort('submitted_at');
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();
        $title           = __('For production');

        return Inertia::render(
            'Org/Inventory/ProductionMaterials',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-pallet-alt'],
                        'title' => $title,
                    ],
                    'subNavigation' => $this->getSubNavigation($routeParameters),
                ],
                'scope'       => $this->scope,
                'can_work'    => $this->warehouse->canBeWorkedInBy($request->user()),
                'data'        => $items,
            ]
        )->table($this->tableStructure($this->scope));
    }

    public function getSubNavigation(array $routeParameters): array
    {
        return [
            [
                'label'    => __('To bring'),
                'number'   => self::pendingItems($this->warehouse)->count(),
                'leftIcon' => ['icon' => 'fal fa-pallet-alt'],
                'root'     => 'grp.org.warehouses.show.inventory.production_materials.index',
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.production_materials.index',
                    'parameters' => $routeParameters,
                ],
            ],
            [
                'label'    => __('Done'),
                'leftIcon' => ['icon' => 'fal fa-check'],
                'root'     => 'grp.org.warehouses.show.inventory.production_materials.done',
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.production_materials.done',
                    'parameters' => $routeParameters,
                ],
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.production_materials.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('For production'),
                        'icon'  => 'fal fa-pallet-alt',
                    ],
                ],
            ]
        );
    }
}
