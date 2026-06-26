<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\Traits\Dashboards\Settings\WithDashboardTopCustomersLimitSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\CRM\Customer\GetTopCustomersStats;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCrmDashboardInOverview extends OrgAction
{
    use WithDashboardTopCustomersLimitSettings;
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    private Group|Organisation $parent;

    public function handle(Group|Organisation $parent, ActionRequest $request): Response
    {
        $this->parent = $parent;
        $userSettings = $request->user()->settings;

        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;
        $intervalQuery = $request->query('interval');
        $interval = DateIntervalEnum::tryFrom((string) $intervalQuery) ?? $savedInterval;

        $limitSetting = $this->dashboardTopCustomersLimitSettings($userSettings);
        $limitQuery = $request->query('limit');
        $limit = in_array((int) $limitQuery, [3, 10, 50, 100], true) ? (int) $limitQuery : (int) $limitSetting['value'];

        $performanceDates = $this->resolvePerformanceDates($interval, $userSettings);

        $topCustomers = GetTopCustomersStats::run($this->parent, $performanceDates[0], $performanceDates[1], $limit);

        $topCustomersLimit = $this->dashboardTopCustomersLimitSettings($userSettings);
        $topCustomersLimit['value'] = $limit;

        $topCustomersData = [
            'intervals' => [
                'options'        => $this->dashboardIntervalOption(),
                'value'          => $interval->value,
                'range_interval' => DashboardIntervalFilters::run($interval, $userSettings)
            ],
            'settings' => [
                'top_customers_limit' => $topCustomersLimit,
            ],
            'topCustomers' => $topCustomers,
        ];

        $title = __('Top Customers');

        $isGroup = $this->parent instanceof Group;

        $breadcrumbs = $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters());

        return Inertia::render(
            'Overview/CRM/CrmDashboardOverview',
            [
                'breadcrumbs' => $breadcrumbs,
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-trophy'],
                        'title' => $title
                    ],
                ],
                'isGroup' => $isGroup,
                'top_customers' => $topCustomersData,
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $routeParams = $request->route()->parameters();
        if (isset($routeParams['organisation'])) {
            $organisation = Organisation::where('slug', $routeParams['organisation'])->firstOrFail();
            $this->parent = $organisation;
            $this->initialisation($organisation, $request);
        } else {
            $group = group();
            $this->parent = $group;
            $this->initialisationFromGroup($group, $request);
        }

        return $this->handle($this->parent, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $isGroup = $this->parent instanceof Group;
        $label = __('Top Customers');

        $headCrumb = function (array $routeParameters = []) use ($label) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $label,
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        if ($isGroup) {
            return array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.crm.customers.top_customers',
                        'parameters' => $routeParameters
                    ]
                )
            );
        } else {
            return array_merge(
                ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.overview.customers.top_customers',
                        'parameters' => $routeParameters
                    ]
                )
            );
        }
    }
}
