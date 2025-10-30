<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    // Note: Experimental Data (Need to be checked)
    public function toArray($request): array
    {
        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->resource->platform->name ?? '',
                    'align'           => 'left'
                ]
            ],
            $this->getDashboardTableColumn($this, 'invoices'),
            $this->getDashboardTableColumn($this, 'new_channels'),
            $this->getDashboardTableColumn($this, 'new_customers'),
            $this->getDashboardTableColumn($this, 'new_portfolios'),
            $this->getDashboardTableColumn($this, 'new_customer_client'),
        );

        if ($this->resource instanceof PlatformShopSalesIntervals) {
            $columns = array_merge($columns, $this->getDashboardTableColumn($this, 'sales'));
        }

        return [
            'slug'      => $this->resource->platform->slug ?? '',
            'state'     => 'active',
            'columns'   => $columns,
            'colour'      => ''
        ];
    }
}
