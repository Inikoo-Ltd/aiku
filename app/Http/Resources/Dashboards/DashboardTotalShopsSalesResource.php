<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalShopsSalesResource extends JsonResource
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
        /** @var Organisation $organisation */
        $organisation = $this->resource;

        $salesIntervals = $organisation->salesIntervals;
        $orderingIntervals = $organisation->orderingIntervals;

        // Handle custom range data
        if (!empty($this->customRangeData['shops'])) {
            $aggregatedData = $this->aggregateShopsData($this->customRangeData['shops']);

            $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $aggregatedData, 'sales', $organisation);
            $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $aggregatedData, 'ordering', $organisation);
        }

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'organisation' => [
                'route_target' => [
                    'name' => 'grp.org.dashboard.show',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                ],
            ],
        ];

        $baskets_created_org_currency = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_org_currency', $routeTargets['inBasket']);
        $baskets_created_org_currency_delta = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_org_currency_delta');

        $baskets_created_shop_currency = [
            'baskets_created_shop_currency' => $baskets_created_org_currency['baskets_created_org_currency']
        ];
        $baskets_created_shop_currency_delta = [
            'baskets_created_shop_currency_delta' => $baskets_created_org_currency_delta['baskets_created_org_currency_delta']
        ];

        $baskets_created_org_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'baskets_created_org_currency_minified', $routeTargets['inBasket']);
        $baskets_created_shop_currency_minified = [
            'baskets_created_shop_currency_minified' => $baskets_created_org_currency_minified['baskets_created_org_currency_minified']
        ];

        $sales_org_currency = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency');
        $sales_org_currency_delta = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency_delta');

        $sales_shop_currency = [
            'sales_shop_currency' => $sales_org_currency['sales_org_currency']
        ];

        $sales_shop_currency_delta = [
            'sales_shop_currency_delta' => $sales_org_currency_delta['sales_org_currency_delta']
        ];

        $sales_org_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency_minified');
        $sales_shop_currency_minified = [
            'sales_shop_currency_minified' => $sales_org_currency_minified['sales_org_currency_minified']
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value'   => $organisation->name,
                    'align'             => 'left',
                    'data_display_type' => 'full',
                    ...$routeTargets['organisation'],
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value'   => $organisation->code,
                    'tooltip'           => $organisation->name,
                    'align'             => 'left',
                    'data_display_type' => 'minified',
                    ...$routeTargets['organisation'],
                ]
            ],
            $baskets_created_shop_currency,
            $baskets_created_shop_currency_minified,
            $baskets_created_shop_currency_delta,
            $baskets_created_org_currency,
            $baskets_created_org_currency_minified,
            $baskets_created_org_currency_delta,
            $this->getDashboardTableColumn($orderingIntervals, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $sales_shop_currency,
            $sales_shop_currency_minified,
            $sales_shop_currency_delta,
            $sales_org_currency,
            $sales_org_currency_minified,
            $sales_org_currency_delta
        );

        return [
            'slug'    => $organisation->slug,
            'columns' => $columns
        ];
    }

    private function aggregateShopsData(array $shopsData): array
    {
        $aggregated = [
            'baskets_created_grp_currency_ctm' => 0,
            'baskets_created_org_currency_ctm' => 0,
            'registrations_ctm' => 0,
            'registrations_with_orders_ctm' => 0,
            'registrations_without_orders_ctm' => 0,
            'sales_grp_currency_ctm' => 0,
            'sales_org_currency_ctm' => 0,
            'invoices_ctm' => 0,
            'baskets_created_grp_currency_ctm_ly' => 0,
            'baskets_created_org_currency_ctm_ly' => 0,
            'registrations_ctm_ly' => 0,
            'registrations_with_orders_ctm_ly' => 0,
            'registrations_without_orders_ctm_ly' => 0,
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

    private function createCustomRangeIntervalsObject($originalIntervals, array $customData, string $type, Organisation $organisation): object
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

        $intervalsData['OrganisationCurrencyCode'] = $organisation->currency->code;
        $intervalsData['organisation'] = $organisation;

        return (object) $intervalsData;
    }
}
