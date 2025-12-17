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

class DashboardTotalGroupMasterShopsSalesResource extends JsonResource
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

        if (!empty($this->customRangeData['master_shops'])) {
            $aggregatedData = $this->aggregateMasterShopsData($this->customRangeData['master_shops']);

            $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $aggregatedData, 'sales', $group);
            $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $aggregatedData, 'ordering', $group);
        }

        $routeTargets = [

            'group' => [
                'route_target' => [
                    'name'       => 'grp.dashboard.show',
                    'parameters' => [],
                ],
            ],
        ];


        $baskets_created_grp_currency          = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_grp_currency');
        $baskets_created_grp_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_grp_currency_minified');
        $sales_grp_currency                    = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency');
        $sales_grp_currency_delta              = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_delta');
        $sales_grp_currency_minified           = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_minified');


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
            $baskets_created_grp_currency,
            $baskets_created_grp_currency_minified,
            $this->getDashboardTableColumn($orderingIntervals, 'registrations'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_minified'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $sales_grp_currency,
            $sales_grp_currency_minified,
            $sales_grp_currency_delta
        );


        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }

    private function aggregateMasterShopsData(array $masterShopsData): array
    {
        $aggregated = [
            'baskets_created_grp_currency_ctm'    => 0,
            'registrations_ctm'                   => 0,
            'registrations_with_orders_ctm'       => 0,
            'registrations_without_orders_ctm'    => 0,
            'sales_grp_currency_ctm'              => 0,
            'invoices_ctm'                        => 0,
            'baskets_created_grp_currency_ctm_ly' => 0,
            'registrations_ctm_ly'                => 0,
            'registrations_with_orders_ctm_ly'    => 0,
            'registrations_without_orders_ctm_ly' => 0,
            'sales_grp_currency_ctm_ly'           => 0,
            'invoices_ctm_ly'                     => 0,
        ];

        foreach ($masterShopsData as $masterShopId => $masterShopData) {
            foreach ($aggregated as $key => $value) {
                if (isset($masterShopData[$key])) {
                    $aggregated[$key] += (float) $masterShopData[$key];
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
