<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-16h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\CRM\CustomersTabsEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomersDashboard
{
    use AsObject;
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    public function handle(Shop $parent, ?ActionRequest $request = null): array
    {
        $stats = [];

        $stats['customers'] = [
            'label' => __('Customers'),
            'count' => $parent->crmStats->number_customers
        ];
        foreach (CustomerStateEnum::cases() as $case) {
            $stats['customers']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => CustomerStateEnum::stateIcon()[$case->value],
                'count' => CustomerStateEnum::count($parent)[$case->value],
                'label' => CustomerStateEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'customers_elements[state]' => $case->value,
                        'tab'          => CustomersTabsEnum::CUSTOMERS->value
                    ]
                ]
            ];
        }

        $userSettings = $request?->user()?->settings ?? [];
        $interval     = $this->resolveInterval($request, $userSettings);
        [$from, $to]  = $this->resolvePerformanceDates($interval, $userSettings);

        return array_merge(
            [
                'prospectStats' => $stats,
                'intervals'     => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => $interval->value,
                    'range_interval' => DashboardIntervalFilters::run($interval, $userSettings)
                ]
            ],
            GetCustomerRfmComparison::run($parent, $from, $to)
        );
    }

    protected function resolveInterval(?ActionRequest $request, array $userSettings): DateIntervalEnum
    {
        $savedInterval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        return DateIntervalEnum::tryFrom((string) $request?->query('interval')) ?? $savedInterval;
    }
}
