<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalGroupShopsSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    private array $customRangeData = [];

    public function setCustomRangeData(array $customRangeData): self
    {
        $this->customRangeData = $customRangeData;
        return $this;
    }

    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this->resource;

        $salesIntervals = $group->salesIntervals;
        $orderingIntervals = $group->orderingIntervals;

        // Handle custom range data
        if (!empty($this->customRangeData['shops'])) {
            $aggregatedData = $this->aggregateShopsData($this->customRangeData['shops']);

            $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $aggregatedData, 'sales', $group);
            $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $aggregatedData, 'ordering', $group);
        }

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.overview.accounting.invoices.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.overview.crm.customers.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.overview.ordering.orders_in_basket.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'group' => [
                'route_target' => [
                    'name' => 'grp.dashboard.show',
                    'parameters' => [],
                ],
            ],
        ];

        $baskets_created_grp_currency       = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_grp_currency', $routeTargets['inBasket']);
        $baskets_created_org_currency       = [
            'baskets_created_org_currency' => $baskets_created_grp_currency['baskets_created_grp_currency']
        ];

        $baskets_created_grp_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_grp_currency_minified', $routeTargets['inBasket']);
        $baskets_created_org_currency_minified = [
            'baskets_created_org_currency_minified' => $baskets_created_grp_currency_minified['baskets_created_grp_currency_minified']
        ];

        $sales_grp_currency       = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency');
        $sales_grp_currency_delta = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_delta');

        $sales_org_currency = [
            'sales_org_currency' => $sales_grp_currency['sales_grp_currency']
        ];

        $sales_org_currency_delta = [
            'sales_org_currency_delta' => $sales_grp_currency_delta['sales_grp_currency_delta']
        ];

        $sales_grp_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_minified');
        $sales_org_currency_minified = [
            'sales_org_currency_minified' => $sales_grp_currency_minified['sales_grp_currency_minified']
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $group->name,
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $group->code,
                    'tooltip'         => $group->name,
                    'align'           => 'left',
                    ...$routeTargets['group']
                ]
            ],
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_grp_currency,
            $baskets_created_grp_currency_minified,
            $this->getDashboardTableColumn($orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $sales_org_currency,
            $sales_org_currency_minified,
            $sales_org_currency_delta,
            $sales_grp_currency,
            $sales_grp_currency_minified,
            $sales_grp_currency_delta
        );

        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }

    private function aggregateShopsData(array $shopsData): array
    {
        $aggregated = [
            'baskets_created_grp_currency_ctm' => 0,
            'baskets_created_org_currency_ctm' => 0,
            'registrations_ctm' => 0,
            'sales_grp_currency_ctm' => 0,
            'sales_org_currency_ctm' => 0,
            'invoices_ctm' => 0,
            'baskets_created_grp_currency_ctm_ly' => 0,
            'baskets_created_org_currency_ctm_ly' => 0,
            'registrations_ctm_ly' => 0,
            'sales_grp_currency_ctm_ly' => 0,
            'sales_org_currency_ctm_ly' => 0,
            'invoices_ctm_ly' => 0,
        ];

        foreach ($shopsData as $shopId => $shopData) {
            foreach ($aggregated as $key => $value) {
                if (isset($shopData[$key])) {
                    $aggregated[$key] += (float) $shopData[$key];
                }
            }
        }

        return $aggregated;
    }

    private function createCustomRangeIntervalsObject($originalIntervals, array $customData, string $type, Group $group): object
    {
        $intervalsData = [];

        if ($originalIntervals) {
            $intervalsData = $originalIntervals->toArray();
        }

        foreach ($customData as $key => $value) {
            if ($type === 'sales' && (str_starts_with($key, 'baskets_created_') || str_starts_with($key, 'sales_'))) {
                $intervalsData[$key] = $value;
            } elseif ($type === 'ordering' && (str_starts_with($key, 'registrations_') || str_starts_with($key, 'invoices_'))) {
                $intervalsData[$key] = $value;
            }
        }

        $intervalsData['group_currency_code'] = $group->currency->code;
        $intervalsData['group'] = $group;

        return (object) $intervalsData;
    }
}
