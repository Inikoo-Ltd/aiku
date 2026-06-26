<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\Dispatching\PickingSession\Traits\WithPickingSessionsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Http\Resources\Fulfilment\FulfilmentPickingSessionsResource;
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

class IndexFulfilmentPickingSessions extends OrgAction
{
    // Reusing the same sub-nav logic if applicable, or we might need to adapt links
    use WithPickingSessionsSubNavigation;

    private ?PickingSessionStateEnum $restriction = null;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picking_sessions.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickingSession::class);
        $query->where('picking_sessions.warehouse_id', $warehouse->id);

        // Filter ONLY Fulfilment sessions
        $query->where('picking_sessions.type', PickingSessionTypeEnum::FULFILMENT);

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
                    'picking_sessions.number_pallet_returns',
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
                    'number_pallet_returns',
                    'number_items',
                    'picking_percentage',
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
            'Org/Inventory/FulfilmentPickingSessions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Fulfilment Picking Sessions'),
                'pageHead'    => [
                    'title'         => __('Picking Sessions'),
                    'model'         => __('Fulfilment'),
                    // 'subNavigation' => $this->getSubNavigation(), // Need to ensure routes in subnav point to fulfilment
                    'icon'          => ['fal', 'fa-arrow-from-left'],
                    'iconRight'     => [
                        'icon' => 'fal fa-truck-loading',
                    ],
                ],
                'data'        => FulfilmentPickingSessionsResource::collection($pickingSessions),
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
                $table->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_pallet_returns', label: __('Returns'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_items', label: __('Items'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'picking_percentage', label: __('Picking'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'packing_percentage', label: __('Packing'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'user_name', label: __('User'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'start_at', label: __('Start'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'date');
            $table->column(key: 'end_at', label: __('End'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'date');
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

        $base = array_merge(
            ShowWarehouseFulfilmentDashboard::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => 'grp.org.warehouses.show.fulfilment.picking_sessions.index',
                    'parameters' => $routeParameters,
                ],
            ),
        );

        return match ($routeName) {
            'grp.org.warehouses.show.fulfilment.picking_sessions.in_process' => array_merge($base, $headCrumb(['name' => $routeName, 'parameters' => $routeParameters], __('In Process'))),
            'grp.org.warehouses.show.fulfilment.picking_sessions.picking' => array_merge($base, $headCrumb(['name' => $routeName, 'parameters' => $routeParameters], __('Picking'))),
            'grp.org.warehouses.show.fulfilment.picking_sessions.waiting' => array_merge($base, $headCrumb(['name' => $routeName, 'parameters' => $routeParameters], __('Waiting'))),
            'grp.org.warehouses.show.fulfilment.picking_sessions.picked' => array_merge($base, $headCrumb(['name' => $routeName, 'parameters' => $routeParameters], __('Picked'))),
            'grp.org.warehouses.show.fulfilment.picking_sessions.packed' => array_merge($base, $headCrumb(['name' => $routeName, 'parameters' => $routeParameters], __('Packed'))),
            default => $base
        };
    }
}
