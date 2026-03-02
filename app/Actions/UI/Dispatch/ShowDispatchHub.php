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
                'dashboard'   => DashboardDispatchHubDashboardResource::make(GetDispatchHubShowcase::make()->handle($warehouse)),
            ]
        );
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
