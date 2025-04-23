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


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        $sales_org_currency              = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency');
        $sales_org_currency_delta              = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_delta');

        $sales_invoice_category_currency = [
            'sales_invoice_category_currency' => $sales_org_currency['sales_org_currency']
        ];

        $sales_invoice_category_currency_delta = [
            'sales_invoice_category_currency_delta' => $sales_org_currency_delta['sales_org_currency_delta']
        ];

        $sales_org_currency_minified              = $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_minified');
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
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'refunds', $routeTargets['refunds']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'refunds_minified', $routeTargets['refunds']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'refunds_delta'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_delta'),
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
}
