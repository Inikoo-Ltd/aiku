<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalShopInvoiceCategoriesSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this;

        return array_merge(
            [
                'label' => [
                    'formatted_value' => $shop->name,
                    'align'           => 'left',
                ],
            ],
            [
                'label_minified' => [
                    'formatted_value' => $shop->code,
                    'tooltip'         => $shop->name,
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardTableColumn($shop->orderingIntervals, 'refunds'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'visitors'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'orders'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'registrations'),
            $this->getDashboardTableColumn($shop->salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($shop->orderingIntervals, 'lost_revenue_other_amount'),
        );
    }
}
