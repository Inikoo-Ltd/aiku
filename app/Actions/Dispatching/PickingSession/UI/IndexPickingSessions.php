<?php

/*
 * author Arya Permana - Kirin
 * created on 04-04-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
 */

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Dispatching\PickingSession\Traits\WithPickingSessionsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Http\Resources\Dispatching\PickingSessionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPickingSessions extends OrgAction
{
    use WithPickingSessionsSubNavigation;

    private ?PickingSessionStateEnum $restriction = null;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picking_Sessions.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickingSession::class);
        $query->where('picking_sessions.warehouse_id', $warehouse->id);
        $query->leftjoin('users', 'picking_sessions.user_id', '=', 'users.id');

        if ($this->restriction) {
            $query->where('picking_sessions.state', $this->restriction);
        }

        return $query->defaultSort('-picking_sessions.id')
                ->select([
                    'picking_sessions.id',
                    'picking_sessions.reference',
                    'picking_sessions.slug',
                    'picking_sessions.state',
                    'picking_sessions.start_at',
                    'picking_sessions.end_at',
                    'picking_sessions.number_delivery_notes',
                    'picking_sessions.number_items',
                    'picking_sessions.quantity_picked',
                    'picking_sessions.quantity_packed',
                    'picking_sessions.picking_percentage',
                    'picking_sessions.packing_percentage',
                    'users.id as user_id',
                    'users.username as user_username',
                    'users.contact_name as user_name',
                ])
                ->defaultSort('picking_sessions.id')
                ->allowedSorts([
                    'reference',
                    'number_delivery_notes',
                    'number_picking_session_items',
                    'number_items', 'picking_percentage',
                    'packing_percentage',
                    'user_name',
                    'start_at',
                    'end_at'
                ])
                ->allowedFilters([$globalSearch])
                ->withPaginator($prefix, tableName: request()->route()->getName())
                ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $pickingSessions, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/PickingSessions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Picking Sessions'),
                'pageHead'    => [
                    'title'         => __('Picking Sessions'),
                    'model'         => __('Goods Out'),
                    'subNavigation' => $this->getSubNavigation(),
                    'icon'          => ['fal', 'fa-arrow-from-left'],
                    'iconRight'     => [
                        'icon' => 'fal fa-truck',
                    ],
                ],
                'data'        => PickingSessionsResource::collection($pickingSessions),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withModelOperations($modelOperations);

            if (!$this->restriction) {
                $table->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_delivery_notes', label: __('delivery notes'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_items', label: __('items'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'picking_percentage', label: __('picking'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'packing_percentage', label: __('packing'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'user_name', label: __('user'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'start_at', label: __('start'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'date');
            $table->column(key: 'end_at', label: __('end'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'date');
            $table->withGlobalSearch();
            $table->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function InProcess(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = PickingSessionStateEnum::IN_PROCESS;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function Picking(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = PickingSessionStateEnum::HANDLING;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function Waiting(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = PickingSessionStateEnum::HANDLING_BLOCKED;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function Picked(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = PickingSessionStateEnum::PICKING_FINISHED;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function Packed(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = PickingSessionStateEnum::PACKING_FINISHED;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Picking sessions'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picking_sessions.index' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.org.warehouses.show.dispatching.picking_sessions.in_process' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('In Process'),
                            'icon'  => 'fal fa-chair',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.in_process',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ),
            'grp.org.warehouses.show.dispatching.picking_sessions.picking' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Picking'),
                            'icon'  => 'fal fa-hand-paper',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.picking',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ),
            'grp.org.warehouses.show.dispatching.picking_sessions.waiting' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Waiting'),
                            'icon'  => 'fal fa-hand-paper',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.waiting',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ),
            'grp.org.warehouses.show.dispatching.picking_sessions.picked' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Picked'),
                            'icon'  => 'fal fa-box-check',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.picked',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ),
            'grp.org.warehouses.show.dispatching.picking_sessions.packed' => array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters,
                    ],
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Packed'),
                            'icon'  => 'fal fa-box-check',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.packed',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ),
            default => []
        };
    }
}
