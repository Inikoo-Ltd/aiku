<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 00:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalGroupInvoiceCategoriesSalesResource extends JsonResource
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
        if (!empty($this->customRangeData['invoice_categories'])) {
            $aggregatedData = $this->aggregateInvoiceCategoriesData($this->customRangeData['invoice_categories']);

            $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $aggregatedData, 'sales', $group);
            $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $aggregatedData, 'ordering', $group);
        }

        $sales_grp_currency = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency');
        $sales_grp_currency_delta = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_delta');

        $sales_invoice_category_currency = [
            'sales_invoice_category_currency' => $sales_grp_currency['sales_grp_currency']
        ];

        $sales_invoice_category_currency_delta = [
            'sales_invoice_category_currency_delta' => $sales_grp_currency_delta['sales_grp_currency_delta']
        ];

        $sales_grp_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'sales_grp_currency_minified');
        $sales_invoice_category_currency_minified = [
            'sales_invoice_category_currency_minified' => $sales_grp_currency_minified['sales_grp_currency_minified']
        ];

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.overview.accounting.invoices.index',
                    'parameters' => [],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'refunds' => [
                'route_target' => [
                    'name' => 'grp.overview.accounting.refunds.index',
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
            $this->getDashboardTableColumn($orderingIntervals, 'refunds', $routeTargets['refunds']),
            $this->getDashboardTableColumn($orderingIntervals, 'refunds_minified', $routeTargets['refunds']),
            $this->getDashboardTableColumn($orderingIntervals, 'refunds_inverse_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'lost_revenue_other_amount_grp_currency'),
            $sales_invoice_category_currency,
            $sales_invoice_category_currency_minified,
            $sales_invoice_category_currency_delta,
            $sales_grp_currency,
            $sales_grp_currency_minified,
            $sales_grp_currency_delta
        );

        return [
            'slug'    => $group->slug,
            'columns' => $columns
        ];
    }

    private function aggregateInvoiceCategoriesData(array $invoiceCategoriesData): array
    {
        $aggregated = [
            'invoices_ctm' => 0,
            'refunds_ctm' => 0,
            'sales_grp_currency_ctm' => 0,
            'sales_invoice_category_currency_ctm' => 0,
            'revenue_grp_currency_ctm' => 0,
            'revenue_invoice_category_currency_ctm' => 0,
            'lost_revenue_grp_currency_ctm' => 0,
            'lost_revenue_invoice_category_currency_ctm' => 0,
            'invoices_ctm_ly' => 0,
            'refunds_ctm_ly' => 0,
            'sales_grp_currency_ctm_ly' => 0,
            'sales_invoice_category_currency_ctm_ly' => 0,
            'revenue_grp_currency_ctm_ly' => 0,
            'revenue_invoice_category_currency_ctm_ly' => 0,
            'lost_revenue_grp_currency_ctm_ly' => 0,
            'lost_revenue_invoice_category_currency_ctm_ly' => 0,
        ];

        foreach ($invoiceCategoriesData as $invoiceCategoryId => $invoiceCategoryData) {
            foreach ($aggregated as $key => $value) {
                if (isset($invoiceCategoryData[$key])) {
                    $aggregated[$key] += (float) $invoiceCategoryData[$key];
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
            if ($type === 'sales' && (str_starts_with($key, 'sales_') || str_starts_with($key, 'revenue_') || str_starts_with($key, 'lost_revenue_'))) {
                $intervalsData[$key] = $value;
            } elseif ($type === 'ordering' && (str_starts_with($key, 'invoices_') || str_starts_with($key, 'refunds_'))) {
                $intervalsData[$key] = $value;
            }
        }

        $intervalsData['group_currency_code'] = $group->currency->code;
        $intervalsData['group'] = $group;

        return (object) $intervalsData;
    }
}
