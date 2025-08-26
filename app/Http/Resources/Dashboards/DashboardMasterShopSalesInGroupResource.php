<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 16:58:12 Central Standard Time, Mexico-Tokio
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $code
 * @property mixed $slug
 * @property mixed $status
 */
class DashboardMasterShopSalesInGroupResource extends JsonResource
{
    use WithDashboardIntervalValues;


    public function toArray($request): array
    {


        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name,
                    'align'           => 'left',
                    'route_target'    => []
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $this->code,
                    'tooltip'         => $this->name,
                    'align'           => 'left',
                    'route_target'    => []
                ]
            ],
            $this->getDashboardTableColumn($this, 'baskets_created_grp_currency'),
            $this->getDashboardTableColumn($this, 'baskets_created_grp_currency_minified'),
            $this->getDashboardTableColumn($this, 'invoices'),
            $this->getDashboardTableColumn($this, 'invoices_minified'),
            $this->getDashboardTableColumn($this, 'invoices_delta'),
            $this->getDashboardTableColumn($this, 'registrations'),
            $this->getDashboardTableColumn($this, 'registrations_minified'),
            $this->getDashboardTableColumn($this, 'registrations_delta'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_delta'),
        );


        return [
            'slug'    => $this->slug,
            'state'   => $this->status ? 'active' : 'inactive',
            'columns' => $columns,


        ];
    }
}
