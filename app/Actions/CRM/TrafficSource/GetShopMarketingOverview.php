<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopMarketingOverview
{
    use AsAction;
    use WithAttributionWindow;

    /**
     * Assembles the marketing dashboard's numbers for a period: attributed revenue, ad spend, and the
     * ROAS and CAC they imply, per channel and in total.
     *
     * Revenue is measured the same way the lifetime figure is - invoice net_amount, excluding
     * in-process refunds - weighted by each channel's attribution share of the customer, so the
     * all-time period reproduces traffic_source_stats.total_customer_revenue exactly and every other
     * period is an honest slice of it. Spend comes from the daily cost rows over the same dates, so
     * ROAS finally compares like with like: a period's return against that period's spend.
     *
     * All figures are in the shop's currency.
     *
     * @return array{period: string, period_label: string, from: string|null, currency_code: string, totals: array{spend: float, revenue: float, registrations: float, invoices: float, roas: float|null, cac: float|null}, channels: array<int, array{name: string, type: string, spend: float, revenue: float, registrations: float, roas: float|null}>, campaigns: array<int, array{name: string, channel: string, spend: float, revenue: float, registrations: float, roas: float|null}>, spend_by_day: array<int, array{date: string, amount: float}>}
     */
    public function handle(Shop $shop, MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30): array
    {
        $from = $period->startsAt();

        $sources = DB::table('traffic_sources')
            ->where('shop_id', $shop->id)
            ->select('id', 'name', 'type')
            ->get()
            ->keyBy('id');

        $window        = GetAttributionWindow::run($shop);
        $revenue       = $this->revenueBySource($shop, $from, $window);
        $registrations = $this->registrationsBySource($shop, $from);
        $spend         = $this->spendBySource($shop, $from);

        $channels = $sources
            ->map(fn ($source) => [
                'name'          => $source->name,
                'type'          => $source->type,
                'spend'         => round((float) ($spend[$source->id] ?? 0), 2),
                'revenue'       => round((float) ($revenue[$source->id]->amount ?? 0), 2),
                'registrations' => round((float) ($registrations[$source->id] ?? 0), 2),
            ])
            ->map(fn (array $channel) => $channel + [
                'roas' => $channel['spend'] > 0 ? round($channel['revenue'] / $channel['spend'], 2) : null,
            ])
            ->filter(fn (array $channel) => $channel['spend'] > 0 || $channel['revenue'] > 0 || $channel['registrations'] > 0)
            ->sortByDesc(fn (array $channel) => max($channel['spend'], $channel['revenue']))
            ->values()
            ->all();

        $totalSpend         = round(array_sum(array_column($channels, 'spend')), 2);
        $totalRevenue       = round(array_sum(array_column($channels, 'revenue')), 2);
        $totalRegistrations = round(array_sum(array_column($channels, 'registrations')), 2);

        return [
            'period'        => $period->value,
            'period_label'  => MarketingPeriodEnum::labels()[$period->value],
            'from'          => $from?->toDateString(),
            'currency_code' => $shop->currency->code,
            'totals'        => [
                'spend'         => $totalSpend,
                'revenue'       => $totalRevenue,
                'registrations' => $totalRegistrations,
                'invoices'      => round(collect($revenue)->sum('invoices'), 2),
                'roas'          => $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : null,
                'cac'           => ($totalSpend > 0 && $totalRegistrations > 0)
                    ? round($totalSpend / $totalRegistrations, 2)
                    : null,
            ],
            'channels'      => $channels,
            'campaigns'     => $this->campaigns($shop, $from),
            'spend_by_day'  => $this->spendByDay($shop, $from),
        ];
    }

    /**
     * Revenue a channel may claim: invoiced after the touch that earned it, and no later than the
     * attribution window allows. Without the first condition a click today collects a customer's
     * entire history; without the second it collects their next several years.
     */
    private function revenueBySource(Shop $shop, ?Carbon $from, int $window)
    {
        return DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->groupBy('p.traffic_source_id')
            ->select(
                'p.traffic_source_id',
                DB::raw('SUM(invoices.net_amount * p.share) as amount'),
                DB::raw('SUM(p.share) as invoices'),
            )
            ->get()
            ->keyBy('traffic_source_id');
    }

    private function registrationsBySource(Shop $shop, ?Carbon $from)
    {
        return DB::table('customers')
            ->join('model_has_traffic_sources as p', function ($join) {
                $join->on('p.model_id', '=', 'customers.id')
                    ->where('p.model_type', '=', 'Customer');
            })
            ->where('customers.shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('customers.created_at', '>=', $from))
            ->groupBy('p.traffic_source_id')
            ->select('p.traffic_source_id', DB::raw('SUM(p.share) as registrations'))
            ->pluck('registrations', 'traffic_source_id');
    }

    private function spendBySource(Shop $shop, ?Carbon $from)
    {
        return DB::table('traffic_source_costs')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->groupBy('traffic_source_id')
            ->select('traffic_source_id', DB::raw('SUM(amount) as spend'))
            ->pluck('spend', 'traffic_source_id');
    }

    /**
     * @return array<int, array{date: string, amount: float}>
     */
    /**
     * The campaigns that actually moved money in the period, richest first. Campaign refs come from
     * ad platforms, so a shop can accumulate hundreds; the dashboard shows the handful worth looking
     * at and the campaign listing carries the rest.
     *
     * @return array<int, array{name: string, channel: string, spend: float, revenue: float, registrations: float, roas: float|null}>
     */
    private function campaigns(Shop $shop, ?Carbon $from, int $limit = 8): array
    {
        $window = GetAttributionWindow::run($shop);

        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->whereNotNull('p.traffic_source_campaign_id')
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->groupBy('p.traffic_source_campaign_id')
            ->select(
                'p.traffic_source_campaign_id as campaign_id',
                DB::raw('SUM(invoices.net_amount * p.share) as revenue'),
                DB::raw('SUM(p.share) as registrations'),
            )
            ->get()
            ->keyBy('campaign_id');

        $spend = DB::table('traffic_source_costs')
            ->whereNotNull('traffic_source_campaign_id')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->groupBy('traffic_source_campaign_id')
            ->select('traffic_source_campaign_id', DB::raw('SUM(amount) as spend'))
            ->pluck('spend', 'traffic_source_campaign_id');

        $campaignIds = $revenue->keys()->merge($spend->keys())->unique();

        if ($campaignIds->isEmpty()) {
            return [];
        }

        return DB::table('traffic_source_campaigns as c')
            ->join('traffic_sources as ts', 'ts.id', '=', 'c.traffic_source_id')
            ->whereIn('c.id', $campaignIds)
            ->select('c.id', 'c.name', 'ts.name as channel')
            ->get()
            ->map(fn ($campaign) => [
                'name'          => $campaign->name,
                'channel'       => $campaign->channel,
                'spend'         => round((float) ($spend[$campaign->id] ?? 0), 2),
                'revenue'       => round((float) ($revenue[$campaign->id]->revenue ?? 0), 2),
                'registrations' => round((float) ($revenue[$campaign->id]->registrations ?? 0), 2),
            ])
            ->map(fn (array $campaign) => $campaign + [
                'roas' => $campaign['spend'] > 0 ? round($campaign['revenue'] / $campaign['spend'], 2) : null,
            ])
            ->sortByDesc(fn (array $campaign) => max($campaign['spend'], $campaign['revenue']))
            ->take($limit)
            ->values()
            ->all();
    }

    private function spendByDay(Shop $shop, ?Carbon $from): array
    {
        /* The sparkline is a shape, not a ledger: it always shows the recent run of days so the tile
           reads the same whichever period is selected, and never grows unbounded on all time. */
        $sparklineFrom = $from && $from->isAfter(now()->subDays(30))
            ? $from
            : now()->subDays(30)->startOfDay();

        return DB::table('traffic_source_costs')
            ->where('shop_id', $shop->id)
            ->where('date', '>=', $sparklineFrom->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->select('date', DB::raw('SUM(amount) as amount'))
            ->get()
            ->map(fn ($row) => [
                'date'   => Carbon::parse($row->date)->toDateString(),
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }
}
