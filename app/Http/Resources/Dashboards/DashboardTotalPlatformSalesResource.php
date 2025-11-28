<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalPlatformSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        $models = $this->resource->getCollection();

        $firstModel = $models[0] ?? [];

        $summedData = (object) array_merge(
            $firstModel->toArray(),
            $this->sumIntervalValues($models, 'invoices'),
            $this->sumIntervalValues($models, 'invoices', true),
            $this->sumIntervalValues($models, 'new_channels'),
            $this->sumIntervalValues($models, 'new_channels', true),
            $this->sumIntervalValues($models, 'new_customers'),
            $this->sumIntervalValues($models, 'new_customers', true),
            $this->sumIntervalValues($models, 'new_portfolios'),
            $this->sumIntervalValues($models, 'new_portfolios', true),
            $this->sumIntervalValues($models, 'new_customer_client'),
            $this->sumIntervalValues($models, 'new_customer_client', true),
            $this->sumIntervalValues($models, 'sales_grp_currency'),
            $this->sumIntervalValues($models, 'sales_grp_currency', true)
        );

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Platform',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All Platform',
                    'align'           => 'left',
                ],
                'sales_percentage' => [
                    'formatted_value' => '100%',
                    'align' => 'right',
                ],
            ],
            $this->getDashboardTableColumn($summedData, 'invoices'),
            $this->getDashboardTableColumn($summedData, 'invoices_minified'),
            $this->getDashboardTableColumn($summedData, 'invoices_delta'),
            $this->getDashboardTableColumn($summedData, 'new_channels'),
            $this->getDashboardTableColumn($summedData, 'new_channels_minified'),
            $this->getDashboardTableColumn($summedData, 'new_customers'),
            $this->getDashboardTableColumn($summedData, 'new_customers_minified'),
            $this->getDashboardTableColumn($summedData, 'new_portfolios'),
            $this->getDashboardTableColumn($summedData, 'new_portfolios_minified'),
            $this->getDashboardTableColumn($summedData, 'new_customer_client'),
            $this->getDashboardTableColumn($summedData, 'new_customer_client_minified'),
            $this->getDashboardTableColumn($summedData, 'sales_grp_currency'),
            $this->getDashboardTableColumn($summedData, 'sales_grp_currency_minified'),
            $this->getDashboardTableColumn($summedData, 'sales_grp_currency_delta')
        );

        if (!empty($firstModel->shop_id)) {
            $summedData = (object) array_merge(
                (array) $summedData,
                $this->sumIntervalValues($models, 'sales'),
                $this->sumIntervalValues($models, 'sales_org_currency'),
                $this->sumIntervalValues($models, 'sales', true),
                $this->sumIntervalValues($models, 'sales_org_currency', true)
            );

            $columns = array_merge(
                $columns,
                $this->getDashboardTableColumn($summedData, 'sales'),
                $this->getDashboardTableColumn($summedData, 'sales_minified'),
                $this->getDashboardTableColumn($summedData, 'sales_delta'),
                $this->getDashboardTableColumn($summedData, 'sales_org_currency'),
                $this->getDashboardTableColumn($summedData, 'sales_org_currency_minified'),
                $this->getDashboardTableColumn($summedData, 'sales_org_currency_delta'),
            );
        }

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }
}
