<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:39:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Actions\Utils\Abbreviate;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInvoiceCategoriesSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Shop $invoiceCategory */
        $invoiceCategory = $this;

        $columns = array_merge(
            [
                'invoiceCategory' => [
                    'formatted_value' => $invoiceCategory->name
                ]
            ],
            [
                'shop_minified' => [
                    'formatted_value' => Abbreviate::run($invoiceCategory->name),
                    'tooltip'         => $invoiceCategory->name
                ]
            ],
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'refunds'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'refunds_minified'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'refunds_delta'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($invoiceCategory->salesIntervals, 'sales_invoice_category_currency'),
            $this->getDashboardTableColumn($invoiceCategory->salesIntervals, 'sales_invoice_category_currency_minified'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'sales_invoice_category_currency_delta'),
            $this->getDashboardTableColumn($invoiceCategory->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($invoiceCategory->salesIntervals, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($invoiceCategory->orderingIntervals, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $invoiceCategory->slug,
            'state'   => $invoiceCategory->state,
            'columns' => $columns


        ];
    }
}
