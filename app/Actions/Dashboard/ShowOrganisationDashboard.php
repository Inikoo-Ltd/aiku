<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Enums\Dashboards\OrganisationDashboardSalesTableTabsEnum;
use App\Enums\UI\Organisation\OrgDashboardIntervalTabsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationDashboard extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDashboardCurrencyTypeSettings;

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $currentTab= Arr::get($userSettings, 'organisation_dashboard_tab', Arr::first(OrganisationDashboardSalesTableTabsEnum::values()));
        if(!in_array($currentTab, OrganisationDashboardSalesTableTabsEnum::values())){
            $currentTab=Arr::first(OrganisationDashboardSalesTableTabsEnum::values());
        }



        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'organisation_dashboard_tab',
                    'intervals' => [
                        'options' => $this->dashboardIntervalOption(),
                        'value'   => Arr::get($userSettings, 'selected_interval', 'all')  // fix this
                    ],
                    'settings'  => [

                        'model_state_type' => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type'    => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'   => $this->dashboardCurrencyTypeSettings($organisation, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => OrganisationDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => OrganisationDashboardSalesTableTabsEnum::tables($organisation),
                            'charts'      => []
                        ],

                    ]

                ]

            ]
        ];


        return Inertia::render(
            'Dashboard/OrganisationDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters(), __('Dashboard')),
                'dashboard'   => $dashboard

            ]
        );
    }


    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request)->withTabDashboardInterval(OrgDashboardIntervalTabsEnum::values());

        return $this->handle($organisation, $request);
    }

    public function getBreadcrumbs(array $routeParameters, $label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => $label,
                    'route' => [
                        'name'       => 'grp.org.dashboard.show',
                        'parameters' => $routeParameters
                    ]
                ]

            ],

        ];
    }
}
