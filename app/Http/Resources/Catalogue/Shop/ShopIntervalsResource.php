<?php

namespace App\Http\Resources\Catalogue\Shop;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopIntervalsResource extends JsonResource
{
    use WithDashboardIntervalValues;

    private array $customRangeData = [];

    public function setCustomRangeData(array $customRangeData): self
    {
        $this->customRangeData = $customRangeData;

        return $this;
    }

    public function toArray($request): array
    {
        /** @var Shop $shop */
        $shop = $this->resource;

        $salesIntervals = $shop->salesIntervals;
        $orderingIntervals = $shop->orderingIntervals;

        if (!empty($this->customRangeData)) {
            $shopData = $this->customRangeData['shops'][$shop->id] ?? [];

            if (!empty($shopData)) {
                $salesIntervals = $this->createCustomRangeIntervalsObject($salesIntervals, $shopData, 'sales', $shop);
                $orderingIntervals = $this->createCustomRangeIntervalsObject($orderingIntervals, $shopData, 'ordering', $shop);
            }
        }

        return array_merge(
            $this->getDashboardTableColumn($orderingIntervals, 'refunds'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices'),
            $this->getDashboardTableColumn($orderingIntervals, 'invoices_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'visitors'),
            $this->getDashboardTableColumn($orderingIntervals, 'orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_with_orders_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders'),
            $this->getDashboardTableColumn($orderingIntervals, 'registrations_without_orders_delta'),
            $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency'),
            $this->getDashboardTableColumn($salesIntervals, 'sales_org_currency_delta'),
            $this->getDashboardTableColumn($orderingIntervals, 'lost_revenue_other_amount'),
        );
    }

    private function createCustomRangeIntervalsObject($originalIntervals, array $customData, string $type, Shop $shop): object
    {
        $intervalsData = [];

        if ($originalIntervals) {
            $intervalsData = $originalIntervals->toArray();
        }

        foreach ($customData as $key => $value) {
            if ($type === 'sales' && (str_starts_with($key, 'baskets_created_') || str_starts_with($key, 'sales_') || str_starts_with($key, 'revenue_'))) {
                $intervalsData[$key] = $value;
            } elseif ($type === 'ordering' && (
                str_starts_with($key, 'refunds_') || 
                str_starts_with($key, 'invoices_') || 
                str_starts_with($key, 'visitors_') || 
                str_starts_with($key, 'orders_') || 
                str_starts_with($key, 'registrations_') || 
                str_starts_with($key, 'lost_revenue_')
            )) {
                $intervalsData[$key] = $value;
            }
        }

        // Set default values for fields not available in shop_sales_metrics table
        $intervalsData['registrations_with_orders_ctm'] = 0;
        $intervalsData['registrations_with_orders_ctm_ly'] = 0;
        $intervalsData['registrations_without_orders_ctm'] = 0;
        $intervalsData['registrations_without_orders_ctm_ly'] = 0;
        $intervalsData['visitors_ctm'] = 0;
        $intervalsData['visitors_ctm_ly'] = 0;

        $intervalsData['organisation_currency_code'] = $shop->organisation->currency->code;
        $intervalsData['shop'] = $shop;

        return (object) $intervalsData;
    }
}
