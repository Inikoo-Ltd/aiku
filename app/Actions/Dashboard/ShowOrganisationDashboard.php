<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dashboard;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\Dashboards\WithDashboardTableTabResolution;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Traits\WithDashboard;
use App\Actions\Traits\WithTabsBox;
use App\Enums\Dashboards\OrganisationDashboardSalesTableTabsEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Actions\SupplyChain\Agent\UI\GetAgentCleanHandoverScore;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\UI\Organisation\OrgDashboardIntervalTabsEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Actions\UI\Grp\Layout\GetOrganisationNavigation;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
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
    use WithDashboardTableTabResolution;
    use WithTabsBox;
    use WithPerformanceDateResolution;

    private function canViewSales(Organisation $organisation, User $user): bool
    {
        return $user->authTo(['accounting.'.$organisation->id.'.view', 'org-supervisor.'.$organisation->id, 'shops-view.'.$organisation->id]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()
            ->authorisedOrganisations()
            ->whereKey($this->organisation->id)
            ->exists();
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        $tabValues  = OrganisationDashboardSalesTableTabsEnum::values();
        $currentTab = $this->resolveDashboardTableTab($tabValues, $userSettings, 'organisation_dashboard_tab');

        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        [$fromDate, $toDate] = $this->resolvePerformanceDates($savedInterval, $userSettings);

        $timeSeriesData = GetOrganisationDashboardTimeSeriesData::run($organisation, $fromDate, $toDate);

        $currentTabEnum = OrganisationDashboardSalesTableTabsEnum::from($currentTab);
        $primaryTables = OrganisationDashboardSalesTableTabsEnum::tablesForTabs($organisation, $timeSeriesData, [$currentTabEnum]);

        $tabsBox = $this->getTabsBox($organisation);

        $dashboard = [
            'super_blocks' => [
                [
                    'id'        => 'organisation_dashboard_tab',
                    'intervals' => [
                        'options'        => $this->dashboardIntervalOption(),
                        'value'          => $savedInterval,
                        'range_interval' => DashboardIntervalFilters::run($savedInterval, $userSettings)
                    ],
                    'settings'  => [
                        'model_state_type'    => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                        'data_display_type'   => $this->dashboardDataDisplayTypeSettings($userSettings),
                        'currency_type'       => $this->dashboardCurrencyTypeSettings($organisation, $userSettings),
                    ],
                    'blocks'    => [
                        [
                            'id'          => 'sales_table',
                            'type'        => 'table',
                            'current_tab' => $currentTab,
                            'tabs'        => OrganisationDashboardSalesTableTabsEnum::navigation(),
                            'tables'      => $primaryTables,
                            'charts'      => [],
                            'tab_fetch_route' => [
                                'name'       => 'grp.org.dashboard.tab-data',
                                'parameters' => ['organisation' => $organisation->slug],
                            ],
                        ],
                    ],
                    'tabs_box'  => [
                        'current'    => $this->tab,
                        'navigation' => $tabsBox
                    ],
                ]
            ]
        ];

        return Inertia::render(
            'Dashboard/OrganisationDashboard',
            [
                'title'         => __('Dashboard').' '.$organisation->name,
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters(), __('Dashboard')),
                'dashboard'     => $organisation->type === OrganisationTypeEnum::AGENT || !$this->canViewSales($organisation, $request->user()) ? ['super_blocks' => []] : $dashboard,
                'cleanHandover' => $this->getCleanHandover($organisation, $request->user()),
            ]
        );
    }

    private function getCleanHandover(Organisation $organisation, User $user): ?array
    {
        if ($organisation->type !== OrganisationTypeEnum::AGENT) {
            return null;
        }

        $agent = Agent::where('organisation_id', $organisation->id)->first();
        if (!$agent) {
            return null;
        }

        $score = GetAgentCleanHandoverScore::run($agent);

        return $user->hasGroupAccess() ? $score : Arr::except($score, 'hygiene');
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisation($organisation, $request)->withTabDashboardInterval(OrgDashboardIntervalTabsEnum::values());

        if ($production = $this->onlyProductionReachable($organisation, $request->user())) {
            return redirect()->route('grp.org.productions.show.floor', [$organisation->slug, $production->slug]);
        }

        return $this->handle($organisation, $request);
    }

    private function onlyProductionReachable(Organisation $organisation, User $user): ?Production
    {
        if ($this->canViewSales($organisation, $user)) {
            return null;
        }
        $navigation = GetOrganisationNavigation::run($user, $organisation);
        $sections   = Arr::except($navigation, ['shops_fulfilments_navigation', 'productions_navigation', 'warehouses_navigation']);
        $shops = collect(Arr::get($navigation, 'shops_fulfilments_navigation', []))->pluck('navigation')->flatten(1)->filter();
        if ($sections || $shops->isNotEmpty() || Arr::get($navigation, 'warehouses_navigation')) {
            return null;
        }
        $productions = $user->authorisedProductions()->where('productions.organisation_id', $organisation->id)->get();

        return $productions->count() == 1 ? $productions->first() : null;
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
