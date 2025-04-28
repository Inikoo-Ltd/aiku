<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:30:55 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Warehouse\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Enums\UI\Inventory\WarehousesTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\WarehousesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWarehouses extends OrgAction
{
    use WithWarehouseManagementAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(WarehousesTabsEnum::values());

        return $this->handle($organisation, 'warehouses');
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request)->withTab(WarehousesTabsEnum::values());

        return $this->handle(group(), WarehousesTabsEnum::WAREHOUSES->value);
    }

    protected function getElementGroups(Organisation|Group $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    WarehouseStateEnum::labels(),
                    WarehouseStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('warehouses.state', $elements);
                }
            ],
        ];
    }

    public function handle(Group|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('warehouses.name', $value)
                    ->orWhereStartWith('warehouses.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Warehouse::class);

        if ($parent instanceof Group) {
            $queryBuilder->where('warehouses.group_id', $parent->id);
        } else {
            $queryBuilder->where('organisation_id', $parent->id);
        }


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('warehouses.code')
            ->select([
                'warehouses.code as code',
                'warehouses.id',
                'warehouses.name',
                'warehouse_stats.number_warehouse_areas',
                'warehouse_stats.number_locations',
                'warehouses.slug as slug',
                'warehouses.state as state',
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name'
            ])
            ->leftJoin('warehouse_stats', 'warehouse_stats.warehouse_id', 'warehouses.id')
            ->leftJoin('organisations', 'warehouses.organisation_id', 'organisations.id')
            ->allowedSorts(['code', 'name', 'number_warehouse_areas', 'number_locations'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No warehouses'),
                        'description' => $this->canEdit ? __('Get started by creating a new warehouse.') : null,
                        'count'       => $parent->inventoryStats->number_warehouses,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new warehouse'),
                            'label'   => __('warehouse'),
                            'route'   => [
                                'name'       => 'grp.org.warehouses.create',
                                'parameters' => $parent->slug
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'state', label: '', canBeHidden: false, sortable: false, searchable: false, type: 'avatar')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'number_warehouse_areas', label: __('warehouse areas'), canBeHidden: false, sortable: true)
                ->column(key: 'number_locations', label: __('locations'), canBeHidden: false, sortable: true)
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $warehouses): AnonymousResourceCollection
    {
        return WarehousesResource::collection($warehouses);
    }


    public function htmlResponse(LengthAwarePaginator $warehouses, ActionRequest $request): Response
    {
        if (str_starts_with($request->route()->getName(), 'grp.overview')) {
            $parent = $this->group;
        } else {
            $parent = $this->organisation;
        }

        return Inertia::render(
            'Org/Warehouse/Warehouses',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('warehouses'),
                'pageHead'    => [
                    'title'   => __('warehouses'),
                    'icon'    => [
                        'title' => __('warehouses'),
                        'icon'  => 'fal fa-warehouse'
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New warehouse'),
                            'label'   => __('warehouse'),
                            'route'   => [
                                'name'       => 'grp.org.warehouses.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WarehousesTabsEnum::navigation(),
                ],

                WarehousesTabsEnum::WAREHOUSES->value => $this->tab == WarehousesTabsEnum::WAREHOUSES->value ?
                    fn () => WarehousesResource::collection($warehouses)
                    : Inertia::lazy(fn () => WarehousesResource::collection($warehouses)),


                WarehousesTabsEnum::WAREHOUSES_HISTORIES->value => $this->tab == WarehousesTabsEnum::WAREHOUSES_HISTORIES->value ?
                    fn () => HistoryResource::collection(IndexHistory::run(Warehouse::class, 'hst'))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(Warehouse::class, 'hst')))


            ]
        )->table(
            $this->tableStructure(
                parent: $parent,
                prefix: WarehousesTabsEnum::WAREHOUSES->value
            )
        )->table(IndexHistory::make()->tableStructure(prefix: WarehousesTabsEnum::WAREHOUSES_HISTORIES->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) use ($suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Warehouses'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.overview.inventory.warehouses.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }
}
