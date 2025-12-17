<?php

namespace App\Http\Resources\Catalogue\Shop;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopIntervalsResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        return array_merge(
            $this->getDashboardTableColumn($shop->orderingIntervals, 'refunds'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'visitors'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'orders'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'orders_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_with_orders'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_with_orders_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_without_orders'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations_without_orders_delta'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency_delta'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'lost_revenue_other_amount'),
        );
    }
}
