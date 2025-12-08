<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 00:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalInvoiceCategoriesSalesResource extends JsonResource
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
        if (!empty($this->customRangeData['invoice_categories'])) {
            $aggregatedData = $this->aggregateInvoiceCategoriesData($this->customRangeData['invoice_categories']);

            $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $aggregatedData, 'sales', $organisation);
            $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $aggregatedData, 'ordering', $organisation);
        }

        $sales_org_currency = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency');
        $sales_org_currency_delta = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency_delta');

        $sales_invoice_category_currency = [
            'sales_invoice_category_currency' => $sales_org_currency['sales_org_currency']
        ];

        $sales_invoice_category_currency_delta = [
            'sales_invoice_category_currency_delta' => $sales_org_currency_delta['sales_org_currency_delta']
        ];

        $sales_org_currency_minified = $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency_minified');
        $sales_invoice_category_currency_minified = [
            'sales_invoice_category_currency_minified' => $sales_org_currency_minified['sales_org_currency_minified']
        ];

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
            'refunds' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.refunds.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
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

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $organisation->name,
                    'align'           => 'left',
                    ...$routeTargets['organisation']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $organisation->code,
                    'tooltip'         => $organisation->name,
                    'align'           => 'left',
                    ...$routeTargets['organisation']
                ]
            ],
            $this->getDashboardTableColumn($orderingIntervals, 'refunds', $routeTargets['refunds']),
            $this->getDashboardTableColumn($orderingIntervals, 'refunds_minified', $routeTargets['refunds']),
            $this->getDashboardTableColumn($orderingIntervals, 'refunds_inverse_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'lost_revenue_other_amount_org_currency'),
            $sales_invoice_category_currency,
            $sales_invoice_category_currency_minified,
            $sales_invoice_category_currency_delta,
            $sales_org_currency,
            $sales_org_currency_minified,
            $sales_org_currency_delta
        );

        return [
            'slug'    => $organisation->slug,
            'columns' => $columns
        ];
    }

    private function aggregateInvoiceCategoriesData(array $invoiceCategoriesData): array
    {
        $aggregated = [
            'invoices_ctm' => 0,
            'refunds_ctm' => 0,
            'sales_org_currency_ctm' => 0,
            'sales_invoice_category_currency_ctm' => 0,
            'revenue_org_currency_ctm' => 0,
            'revenue_invoice_category_currency_ctm' => 0,
            'lost_revenue_org_currency_ctm' => 0,
            'lost_revenue_invoice_category_currency_ctm' => 0,
            'invoices_ctm_ly' => 0,
            'refunds_ctm_ly' => 0,
            'sales_org_currency_ctm_ly' => 0,
            'sales_invoice_category_currency_ctm_ly' => 0,
            'revenue_org_currency_ctm_ly' => 0,
            'revenue_invoice_category_currency_ctm_ly' => 0,
            'lost_revenue_org_currency_ctm_ly' => 0,
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

    private function createCustomRangeIntervalsObject($originalIntervals, array $customData, string $type, Organisation $organisation): object
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

        $intervalsData['organisationCurrencyCode'] = $organisation->currency->code;
        $intervalsData['organisation'] = $organisation;

        return (object) $intervalsData;
    }
}
