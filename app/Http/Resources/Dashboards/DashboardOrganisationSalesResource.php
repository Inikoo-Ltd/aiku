<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $organisation->name,
                    'align'           => 'left'
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $organisation->code,
                    'tooltip'         => $organisation->name,
                    'align'           => 'left'
                ]
            ],
            $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($organisation->salesIntervals, 'baskets_created_org_currency_delta'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_minified'),
            $this->getDashboardTableColumn($organisation->orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($organisation->salesIntervals, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $organisation->slug,
            'state'   => $organisation->state,
            'columns' => $columns


        ];
    }
}
