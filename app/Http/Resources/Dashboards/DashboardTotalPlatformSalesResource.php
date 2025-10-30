<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class DashboardTotalPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        $models = $this->resource;

        $summedData = (object) array_merge(
            $this->sumIntervalValues($models, 'invoices'),
            $this->sumIntervalValues($models, 'new_channels'),
            $this->sumIntervalValues($models, 'new_customers'),
            $this->sumIntervalValues($models, 'new_portfolios'),
            $this->sumIntervalValues($models, 'new_customer_client')
        );

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Platform',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardTableColumn($summedData, 'invoices'),
            $this->getDashboardTableColumn($summedData, 'new_channels'),
            $this->getDashboardTableColumn($summedData, 'new_customers'),
            $this->getDashboardTableColumn($summedData, 'new_portfolios'),
            $this->getDashboardTableColumn($summedData, 'new_customer_client'),
        );

        $firstModel = $models instanceof Collection
            ? $models->first()
            : (is_array($models) && !empty($models) ? reset($models) : null);

        if ($firstModel instanceof PlatformShopSalesIntervals) {
            $summedData = (object) array_merge(
                (array) $summedData,
                $this->sumIntervalValues($models, 'sales')
            );

            $columns = array_merge(
                $columns,
                $this->getDashboardTableColumn($summedData, 'sales')
            );

            $columns['sales_percentage'] = [
                'formatted_value' => '100%',
                'align' => 'right',
            ];
        }

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }
}
