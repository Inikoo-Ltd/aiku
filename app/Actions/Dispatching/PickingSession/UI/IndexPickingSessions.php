<?php

/*
 * author Arya Permana - Kirin
 * created on 04-04-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dispatch\ShowDispatchHub;
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

        return $query->defaultSort('-picking_sessions.id')
                ->select([
                'picking_sessions.id',
                'picking_sessions.reference',
                'picking_sessions.slug',
                'picking_sessions.state',
                'picking_sessions.start_at',
                'picking_sessions.end_at',
                'picking_sessions.number_delivery_notes',
                'picking_sessions.number_picking_session_items',
                'users.id as user_id',
                'users.username as user_username',
                'users.contact_name as user_name'
                ])
                ->defaultSort('picking_sessions.id')
                ->allowedSorts(['reference', 'number_delivery_notes', 'number_picking_session_items'])
                ->allowedFilters([$globalSearch])
                ->withPaginator($prefix, tableName: request()->route()->getName())
                ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $pickingSessions, ActionRequest $request): Response
    {
        $icon          = ['fal', 'fa-arrow-from-left'];
        $title      = __('Picking sessions');
        $iconRight = [
                    'icon' => 'fal fa-truck',
                ];

        $model     = __('Goods Out');

        $actions = [];
        return Inertia::render(
            'Org/Inventory/PickingSessions',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Picking Sessions'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'actions'       => $actions

                ],
                'data'        => PickingSessionsResource::collection($pickingSessions),
            ]
        )->table($this->tableStructure());
    }


    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_delivery_notes', label: __('delivery notes'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_picking_session_items', label: __('items'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'user_name', label: __('user'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'start_at', label: __('start'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'date')
                ->column(key: 'end_at', label: __('end'), canBeHidden: false, sortable: true, searchable: true,  align: 'right', type: 'date')
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
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
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picking_sessions.index' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
