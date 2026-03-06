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
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
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
                    'title' => __('Dispatching backlog'),
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
            ]
        );
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
            'row_totals'  => ['_global' => ['value' => $total]],
            'totals'      => $totals,
            'grand_total' => [
                'value' => $total,
                'icon'  => ['fal', 'fa-arrow-from-left'],
                'tooltip' => 'Total'
            ],
        ];
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
                        'label' => __('Dispatching'),
                    ]
                ]
            ]
        );
    }
}
