<?php

namespace App\Actions\Inventory\PickingTrolley\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\UI\Inventory\PickingTrolleysTabsEnum;
use App\Http\Resources\Inventory\PickingTrolleyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\PickingTrolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPickingTrolleys extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picking_trolleys.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickingTrolley::class)
            ->where('picking_trolleys.warehouse_id', $warehouse->id);

        return $query
            ->select([
                'picking_trolleys.id',
                'picking_trolleys.code',
                'picking_trolleys.slug',
            ])
            ->defaultSort('picking_trolleys.code')
            ->allowedSorts(['code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title'       => __('No picking trolleys found'),
                    'description' => $this->canEdit ? __('Get started by creating a new picking trolley. ✨') : null,
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New picking trolley'),
                        'label'   => __('picking trolley'),
                        'route'   => [
                            'name'       => 'grp.org.warehouses.show.dispatching.picking_trolleys.create',
                            'parameters' => [
                                request()->route('organisation'),
                                request()->route('warehouse'),
                            ],
                        ],
                    ] : null,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $pickingTrolleys, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Warehouse/PickingTrolleys',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Picking trolleys'),
                'pageHead'    => [
                    'title'  => __('Picking trolleys'),
                    'icon'   => ['fal', 'fa-shopping-cart'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_trolleys.create',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PickingTrolleysTabsEnum::navigation(),
                ],
                PickingTrolleysTabsEnum::TROLLEYS->value => PickingTrolleyResource::collection($pickingTrolleys),
            ]
        )->table($this->tableStructure(prefix: PickingTrolleysTabsEnum::TROLLEYS->value));
    }
    
    public function jsonResponse(LengthAwarePaginator $pickingTrolleys): AnonymousResourceCollection
    {
        return PickingTrolleyResource::collection($pickingTrolleys);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickingTrolleysTabsEnum::values());

        return $this->handle($warehouse, PickingTrolleysTabsEnum::TROLLEYS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Picking trolleys'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picking_trolleys.index' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb([
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_trolleys.index',
                    'parameters' => $routeParameters,
                ])
            ),
            default => []
        };
    }
}
