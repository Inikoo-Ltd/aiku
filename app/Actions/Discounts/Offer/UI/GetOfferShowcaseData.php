<?php

/*
 * author Louis Perez
 * created on 05-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Discounts\UI\GetOffersInsightsData;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetOfferShowcaseData extends GetOffersInsightsData
{
    private ?Offer $offer = null;

    /**
     * @return array{
     *     currency_code: string,
     *     totals: array<string, int|float>,
     *     trend: array<int, array<string, mixed>>,
     *     first_used_at: string|null,
     *     last_used_at: string|null,
     *     benchmark: array{offer_type: string, number_offers: int, avg_order_value: float, discount_rate: float, return_on_discount: float, avg_discount: float}
     * }
     */
    public function forOffer(Offer $offer): array
    {
        $this->offer = $offer;
        $shop        = $offer->shop;

        $insights         = $this->fetchData($shop, null, null, null, null);
        $redemptionOrders = $this->buildRedemptionOrdersQuery($shop, null, null, null, null);

        $customerUsage = DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as uses');

        $repeatCustomers = (int) DB::query()->fromSub($customerUsage, 'customer_usage')->where('uses', '>', 1)->count();

        $usageDates = DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->selectRaw('MIN(used_at) as first_used_at, MAX(used_at) as last_used_at')
            ->first();

        $this->offer = null;
        $benchmark   = $this->getSameTypeBenchmark($shop, $offer->type);

        $totals      = $insights['totals'];
        $redemptions = $totals['redemptions'];
        $customers   = $totals['customers'];

        $shopOrdersSinceStart = DB::table('orders')
            ->where('shop_id', $shop->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('deleted_at')
            ->when($offer->start_at, fn (Builder $query) => $query->where('date', '>=', $offer->start_at))
            ->when($offer->end_at, fn (Builder $query) => $query->where('date', '<=', $offer->end_at))
            ->count();

        return [
            'currency_code' => $insights['currency_code'],
            'totals'        => [
                ...$totals,
                'avg_order_value'    => $redemptions > 0 ? round($totals['revenue_net_amount'] / $redemptions, 2) : 0,
                'return_on_discount' => $totals['discounted_amount'] > 0 ? round($totals['revenue_net_amount'] / $totals['discounted_amount'], 2) : 0,
                'repeat_customers'   => $repeatCustomers,
                'repeat_rate'        => $customers > 0 ? round($repeatCustomers / $customers * 100, 2) : 0,
                'shop_orders'        => $shopOrdersSinceStart,
                'conversion_rate'    => $shopOrdersSinceStart > 0 ? round($redemptions / $shopOrdersSinceStart * 100, 2) : 0,
            ],
            'trend'         => $insights['trend'],
            'first_used_at' => $usageDates->first_used_at,
            'last_used_at'  => $usageDates->last_used_at,
            'benchmark'     => $benchmark,
        ];
    }

    protected function getSameTypeBenchmark(Shop $shop, string $offerType): array
    {
        $sameTypeInsights = $this->handle(shop: $shop, offerType: $offerType);
        $totals           = $sameTypeInsights['totals'];
        $redemptions      = $totals['redemptions'];

        return [
            'offer_type'         => $offerType,
            'number_offers'      => $sameTypeInsights['offer_counts']['redeemed'],
            'avg_order_value'    => $redemptions > 0 ? round($totals['revenue_net_amount'] / $redemptions, 2) : 0,
            'discount_rate'      => $totals['discount_rate'],
            'return_on_discount' => $totals['discounted_amount'] > 0 ? round($totals['revenue_net_amount'] / $totals['discounted_amount'], 2) : 0,
            'avg_discount'       => $totals['avg_discount'],
        ];
    }

    protected function getOfferCounts(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType): object
    {
        if (!$this->offer) {
            return parent::getOfferCounts($shop, $offerCampaign, $offerType);
        }

        return (object) ['total' => 1, 'active' => 0, 'in_process' => 0, 'finished' => 0, 'suspended' => 0];
    }

    protected function buildRedemptionOrdersQuery(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): Builder
    {
        return parent::buildRedemptionOrdersQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate)
            ->when($this->offer, fn (Builder $query) => $query->where('toa.offer_id', $this->offer->id));
    }
}
