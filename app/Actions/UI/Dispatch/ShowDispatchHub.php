<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:44:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\DispatchHubTabsEnum;
use App\Http\Resources\Dispatching\DashboardDispatchHubDashboardResource;
use App\Http\Resources\Dispatching\DispatchPersonnelCurrentWorkResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDispatchHub extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDispatchingAuthorisation;
    use WithDashboardCurrencyTypeSettings;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, [])->withTab(DispatchHubTabsEnum::values());

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        return Inertia::render(
            'Org/Dispatching/DispatchHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => 'dispatch',
                'pageHead'    => [
                    'title' => __('Dispatching Backlog'),
                    'icon'  => [
                        'icon' => ['fal', 'fa-conveyor-belt-alt'],
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => DispatchHubTabsEnum::navigation()
                ],
                'intervals'   => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => DateIntervalEnum::ALL,
                    'range_interval' => DashboardIntervalFilters::run(DateIntervalEnum::ALL, $userSettings),
                ],
                'settings'    => [
                    'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                    'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings),
                ],
                'delivery_note'   => DashboardDispatchHubDashboardResource::make(GetDispatchHubShowcase::make()->handle($warehouse)),
                'picking_session' => $this->getPickingSessionStats($warehouse),
                'pickers_current' => DispatchPersonnelCurrentWorkResource::collection($this->currentWork($warehouse, 'picker_user_id', ['handling', 'handling_blocked'], 'pickers_current')),
                'packers_current' => DispatchPersonnelCurrentWorkResource::collection($this->currentWork($warehouse, 'packer_user_id', ['packing'], 'packers_current')),
                'reports_route'   => [
                    'name'       => 'grp.org.warehouses.show.dispatching.reports',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        )
            ->table($this->currentWorkTableStructure('pickers_current', __('Picker')))
            ->table($this->currentWorkTableStructure('packers_current', __('Packer')));
    }

    private function getPickingSessionStats(Warehouse $warehouse): array
    {
        $stats = $warehouse->stats;
        $routeParams = [
            'organisation' => $this->organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $stateConfig = [
            PickingSessionStateEnum::IN_PROCESS->value       => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.in_process', 'icon' => ['fal', 'fa-chair']],
            PickingSessionStateEnum::HANDLING->value         => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.picking', 'icon' => ['fal', 'fa-hand-paper']],
            PickingSessionStateEnum::HANDLING_BLOCKED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.waiting', 'icon' => ['fal', 'fa-hand-paper']],
            PickingSessionStateEnum::PICKING_FINISHED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.picked', 'icon' => ['fal', 'fa-box-check']],
            PickingSessionStateEnum::PACKING_FINISHED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.packed', 'icon' => ['fal', 'fa-box-check']],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];

        foreach (PickingSessionStateEnum::cases() as $case) {
            $config    = $stateConfig[$case->value];
            $statField = 'number_picking_sessions_state_' . $case->snake();
            $count     = $stats->$statField ?? 0;

            $metrics[] = [
                'key'   => $case->snake(),
                'label' => PickingSessionStateEnum::labels()[$case->value],
                'type'  => 'stat',
                'icon'  => $config['icon'],
                'tooltip' => PickingSessionStateEnum::labels()[$case->value],
            ];

            $dataGlobal[$case->snake()] = [
                'value'        => $count,
                'route_target' => [
                    'name'       => $config['route'],
                    'parameters' => $routeParams,
                ],
            ];

            $totals[$case->snake()] = ['value' => $count];
        }

        $total = $stats->number_picking_sessions ?? 0;

        return [
            'metrics'     => $metrics,
            'data'        => ['_global' => $dataGlobal],
            'row_totals'  => [
                '_global' => [
                    'value'        => $total,
                    'route_target' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value' => $total,
                'icon'  => ['fal', 'fa-arrow-from-left'],
                'tooltip' => 'Total'
            ],
        ];
    }

    private function personnelPaginator(\Illuminate\Database\Query\Builder $base, string $prefix, array $sorts): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for(DeliveryNote::query()->withoutGlobalScopes()->fromSub($base, $prefix))
            ->defaultSort('name')
            ->allowedSorts($sorts)
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    private function currentWork(Warehouse $warehouse, string $userColumn, array $states, string $prefix): LengthAwarePaginator
    {
        $base = DB::table('delivery_notes')
            ->leftJoin('users', 'users.id', '=', "delivery_notes.$userColumn")
            ->leftJoin('trolleys', 'trolleys.current_delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull("delivery_notes.$userColumn")
            ->whereIn('delivery_notes.state', $states)
            ->groupBy("delivery_notes.$userColumn", 'users.contact_name')
            ->select([
                "delivery_notes.$userColumn as user_id",
                'users.contact_name as name',
                DB::raw('COUNT(DISTINCT delivery_notes.id) as number_orders'),
                DB::raw('COUNT(DISTINCT trolleys.id) as number_trolleys'),
                DB::raw("json_agg(DISTINCT jsonb_build_object('reference', delivery_notes.reference, 'slug', delivery_notes.slug)) as orders"),
                DB::raw("json_agg(DISTINCT jsonb_build_object('name', trolleys.name, 'slug', trolleys.slug)) FILTER (WHERE trolleys.id IS NOT NULL) as trolleys"),
            ]);

        return $this->personnelPaginator($base, $prefix, ['name', 'number_orders', 'number_trolleys']);
    }

    private function currentWorkTableStructure(string $prefix, string $dimensionLabel): Closure
    {
        return function (InertiaTable $table) use ($prefix, $dimensionLabel) {
            $table->name($prefix)->pageName($prefix.'Page');
            $table->withEmptyState([
                'icons' => ['fal fa-person-carry'],
                'title' => __('No active work'),
            ]);

            $table->column(key: 'name', label: $dimensionLabel, canBeHidden: false, sortable: true);
            $table->column(key: 'orders', label: __('Orders'), canBeHidden: false);
            $table->column(key: 'trolleys', label: __('Trolleys'), canBeHidden: false);
            $table->defaultSort('name');
        };
    }
    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.backlog',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => ['fal', 'fa-arrow-from-left'],
                        'label' => __('Goods out'),
                    ]
                ]
            ]
        );
    }
}
