<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\UI;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOffersInsightsData
{
    use AsObject;

    public function handle(Shop $shop, ?OfferCampaign $offerCampaign = null, ?string $offerType = null, ?string $fromDate = null, ?string $toDate = null, ?bool $useCache = null): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($shop, $offerCampaign, $offerType, $fromDate, $toDate);
        }

        $cacheKey = $this->getCacheKey($shop, $offerCampaign, $offerType, $fromDate, $toDate);

        return Cache::tags(["dashboard-shop-{$shop->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($shop, $offerCampaign, $offerType, $fromDate, $toDate) {
                return $this->fetchData($shop, $offerCampaign, $offerType, $fromDate, $toDate);
            });
    }

    protected function getCacheKey(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): string
    {
        [$normalizedFrom, $normalizedTo] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:offers_insights:%s:%s:%s:%s:%s',
            $shop->id,
            $offerCampaign?->id ?? 'all',
            $offerType ?? 'all',
            $normalizedFrom,
            $normalizedTo
        );
    }

    protected function normalizeDateBounds(?string $fromDate, ?string $toDate): array
    {
        if (empty($fromDate) && empty($toDate)) {
            return ['all', 'all'];
        }

        return [
            empty($fromDate) ? 'open' : Carbon::parse($fromDate)->toDateString(),
            empty($toDate) ? 'open' : Carbon::parse($toDate)->toDateString(),
        ];
    }

    public static function clearCache(Shop $shop): void
    {
        Cache::tags(["dashboard-shop-{$shop->id}"])->flush();
    }

    protected function fetchData(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): array
    {
        $offerCounts = $this->getOfferCounts($shop, $offerCampaign, $offerType);

        $redemptionOrders = $this->buildRedemptionOrdersQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate);

        $totals = DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->selectRaw('
                COUNT(DISTINCT order_id) as redemptions,
                COUNT(DISTINCT customer_id) as customers,
                COALESCE(SUM(discount_amount), 0) as discounted_amount,
                COUNT(DISTINCT offer_id) as redeemed_offers
            ')
            ->first();

        $revenue = DB::query()->fromSub($this->buildPerOrderQuery($redemptionOrders), 'orders_redeemed')
            ->selectRaw('
                COALESCE(SUM(order_gross_amount), 0) as revenue_gross_amount,
                COALESCE(SUM(order_net_amount), 0) as revenue_net_amount
            ')
            ->first();

        $redemptions      = (int) $totals->redemptions;
        $customers        = (int) $totals->customers;
        $discountedAmount = (float) $totals->discounted_amount;
        $revenueNet       = (float) $revenue->revenue_net_amount;
        $totalOffers      = (int) $offerCounts->total;
        $redeemedOffers   = (int) $totals->redeemed_offers;

        return [
            'currency_code' => $shop->currency->code,
            'offer_counts'  => [
                'total'      => $totalOffers,
                'active'     => (int) $offerCounts->active,
                'in_process' => (int) $offerCounts->in_process,
                'finished'   => (int) $offerCounts->finished,
                'suspended'  => (int) $offerCounts->suspended,
                'redeemed'   => $redeemedOffers,
            ],
            'totals'        => [
                'redemptions'              => $redemptions,
                'customers'                => $customers,
                'revenue_gross_amount'     => round((float) $revenue->revenue_gross_amount, 2),
                'revenue_net_amount'       => round($revenueNet, 2),
                'discounted_amount'        => round($discountedAmount, 2),
                'avg_discount'             => $redemptions > 0 ? round($discountedAmount / $redemptions, 2) : 0,
                'avg_savings_per_customer' => $customers > 0 ? round($discountedAmount / $customers, 2) : 0,
                'discount_rate'            => ($revenueNet + $discountedAmount) > 0 ? round($discountedAmount / ($revenueNet + $discountedAmount) * 100, 2) : 0,
                'conversion_rate'          => $totalOffers > 0 ? round($redeemedOffers / $totalOffers * 100, 2) : 0,
            ],
            'trend'         => $this->getTrend($shop, $offerCampaign, $offerType, $fromDate, $toDate),
            'top_offers'    => $this->getTopOffers($shop, $offerCampaign, $offerType, $fromDate, $toDate),
            'least_offers'  => $this->getLeastEffectiveOffers($shop, $offerCampaign, $offerType, $fromDate, $toDate),
        ];
    }

    protected function buildRedemptionOrdersQuery(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): Builder
    {
        return DB::table('transaction_has_offer_allowances as toa')
            ->join('orders', 'orders.id', '=', 'toa.order_id')
            ->join('offers', 'offers.id', '=', 'toa.offer_id')
            ->where('orders.shop_id', $shop->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('orders.deleted_at')
            ->when($offerCampaign, fn (Builder $query) => $query->where('toa.offer_campaign_id', $offerCampaign->id))
            ->when($offerType, fn (Builder $query) => $query->where('offers.type', $offerType))
            ->when($fromDate, fn (Builder $query) => $query->where('orders.date', '>=', $fromDate))
            ->when($toDate, fn (Builder $query) => $query->where('orders.date', '<=', $toDate))
            ->groupBy('toa.offer_id', 'toa.order_id', 'orders.customer_id')
            ->selectRaw('
                toa.offer_id,
                toa.order_id,
                orders.customer_id,
                MAX(orders.gross_amount) as order_gross_amount,
                MAX(orders.net_amount) as order_net_amount,
                SUM(COALESCE(toa.discounted_amount, 0)) + SUM(COALESCE(toa.free_items_value, 0)) as discount_amount,
                MAX(orders.date) as used_at
            ')
            ->havingRaw('SUM(COALESCE(toa.discounted_amount, 0)) > 0 OR SUM(COALESCE(toa.free_items_value, 0)) > 0 OR SUM(COALESCE(toa.number_of_free_items, 0)) > 0');
    }

    protected function buildPerOrderQuery(Builder $redemptionOrders): Builder
    {
        return DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->groupBy('order_id')
            ->selectRaw('
                order_id,
                MAX(order_gross_amount) as order_gross_amount,
                MAX(order_net_amount) as order_net_amount,
                SUM(discount_amount) as discount_amount,
                MAX(used_at) as used_at
            ');
    }

    protected function getOfferCounts(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType): object
    {
        return DB::table('offers')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->when($offerCampaign, fn (Builder $query) => $query->where('offer_campaign_id', $offerCampaign->id))
            ->when($offerType, fn (Builder $query) => $query->where('type', $offerType))
            ->selectRaw("
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE state = '".OfferStateEnum::ACTIVE->value."') as active,
                COUNT(*) FILTER (WHERE state = '".OfferStateEnum::IN_PROCESS->value."') as in_process,
                COUNT(*) FILTER (WHERE state = '".OfferStateEnum::FINISHED->value."') as finished,
                COUNT(*) FILTER (WHERE state = '".OfferStateEnum::SUSPENDED->value."') as suspended
            ")
            ->first();
    }

    protected function getTrend(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): array
    {
        $redemptionOrders = $this->buildRedemptionOrdersQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate);
        $perOrder         = $this->buildPerOrderQuery($redemptionOrders);

        $granularity = $this->resolveTrendGranularity($perOrder, $fromDate, $toDate);
        $format      = $granularity === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';

        return DB::query()->fromSub($perOrder, 'orders_redeemed')
            ->selectRaw("
                TO_CHAR(DATE_TRUNC('$granularity', used_at), '$format') as period,
                COUNT(*) as redemptions,
                COALESCE(SUM(discount_amount), 0) as discounted_amount,
                COALESCE(SUM(order_net_amount), 0) as revenue_net_amount
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($record) => [
                'period'             => $record->period,
                'redemptions'        => (int) $record->redemptions,
                'discounted_amount'  => round((float) $record->discounted_amount, 2),
                'revenue_net_amount' => round((float) $record->revenue_net_amount, 2),
            ])
            ->all();
    }

    protected function resolveTrendGranularity(Builder $perOrder, ?string $fromDate, ?string $toDate): string
    {
        if ($fromDate) {
            $from = Carbon::createFromFormat('Ymd', $fromDate);
        } else {
            $minDate = DB::query()->fromSub($perOrder, 'orders_redeemed')->min('used_at');

            if (!$minDate) {
                return 'day';
            }

            $from = Carbon::parse($minDate);
        }

        $to   = $toDate ? Carbon::createFromFormat('Ymd', $toDate) : now();
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= 366  => 'day',
            $days <= 1096 => 'week',
            default       => 'month',
        };
    }

    protected function buildOfferRedemptionsQuery(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): Builder
    {
        $redemptionOrders = $this->buildRedemptionOrdersQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate);

        return DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->groupBy('offer_id')
            ->selectRaw('
                offer_id,
                COUNT(*) as redemptions,
                COUNT(DISTINCT customer_id) as customers,
                SUM(order_net_amount) as revenue_net_amount,
                SUM(discount_amount) as discounted_amount
            ');
    }

    protected function getTopOffers(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): array
    {
        $offerRedemptions = $this->buildOfferRedemptionsQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate);

        return DB::query()->fromSub($offerRedemptions, 'offer_redemptions')
            ->join('offers', 'offers.id', '=', 'offer_redemptions.offer_id')
            ->select('offers.slug', 'offers.code', 'offers.name', 'offer_redemptions.redemptions', 'offer_redemptions.customers', 'offer_redemptions.revenue_net_amount', 'offer_redemptions.discounted_amount')
            ->orderByDesc('offer_redemptions.revenue_net_amount')
            ->limit(5)
            ->get()
            ->map(fn ($record) => $this->formatOfferPerformance($record))
            ->all();
    }

    protected function getLeastEffectiveOffers(Shop $shop, ?OfferCampaign $offerCampaign, ?string $offerType, ?string $fromDate, ?string $toDate): array
    {
        $offerRedemptions = $this->buildOfferRedemptionsQuery($shop, $offerCampaign, $offerType, $fromDate, $toDate);

        return DB::table('offers')
            ->where('offers.shop_id', $shop->id)
            ->whereNull('offers.deleted_at')
            ->where('offers.state', OfferStateEnum::ACTIVE->value)
            ->when($offerCampaign, fn (Builder $query) => $query->where('offers.offer_campaign_id', $offerCampaign->id))
            ->when($offerType, fn (Builder $query) => $query->where('offers.type', $offerType))
            ->leftJoinSub($offerRedemptions, 'offer_redemptions', 'offer_redemptions.offer_id', '=', 'offers.id')
            ->selectRaw('
                offers.slug,
                offers.code,
                offers.name,
                COALESCE(offer_redemptions.redemptions, 0) as redemptions,
                COALESCE(offer_redemptions.customers, 0) as customers,
                COALESCE(offer_redemptions.revenue_net_amount, 0) as revenue_net_amount,
                COALESCE(offer_redemptions.discounted_amount, 0) as discounted_amount
            ')
            ->orderBy('redemptions')
            ->orderBy('revenue_net_amount')
            ->limit(5)
            ->get()
            ->map(fn ($record) => $this->formatOfferPerformance($record))
            ->all();
    }

    protected function formatOfferPerformance(object $record): array
    {
        return [
            'slug'               => $record->slug,
            'code'               => $record->code,
            'name'               => $record->name,
            'redemptions'        => (int) $record->redemptions,
            'customers'          => (int) $record->customers,
            'revenue_net_amount' => round((float) $record->revenue_net_amount, 2),
            'discounted_amount'  => round((float) $record->discounted_amount, 2),
        ];
    }
}
