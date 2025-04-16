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


    public function toArray($request): array
    {
        /** @var Group $group */
        $group = $this;

        $sales_grp_currency              = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency');
        $sales_grp_currency_delta              = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency_delta');

        $sales_invoice_category_currency = [
            'sales_invoice_category_currency' => $sales_grp_currency['sales_grp_currency']
        ];

        $sales_invoice_category_currency_delta = [
            'sales_invoice_category_currency_delta' => $sales_grp_currency_delta['sales_grp_currency_delta']
        ];

        $sales_grp_currency_minified              = $this->getDashboardTableColumn($group->salesIntervals, 'sales_grp_currency_minified');
        $sales_invoice_category_currency_minified = [
            'sales_invoice_category_currency_minified' => $sales_grp_currency_minified['sales_grp_currency_minified']
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $group->name,
                    'align'           => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $group->code,
                    'tooltip'         => $group->name,
                    'align'           => 'left'
                ]
            ],
            $this->getDashboardTableColumn($group->orderingIntervals, 'refunds'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'refunds_minified'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'refunds_delta'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($group->orderingIntervals, 'invoices_delta'),
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
}
