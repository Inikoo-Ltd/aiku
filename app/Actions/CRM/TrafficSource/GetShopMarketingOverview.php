<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
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
     * @return array{period: string, period_label: string, from: string|null, currency_code: string, referrers: array<int, array{host: string, visitors: float, registrations: float, revenue: float}>, totals: array{spend: float, revenue: float, registrations: float, invoices: float, roas: float|null, cac: float|null}, channels: array<int, array{name: string, type: string, spend: float, revenue: float, registrations: float, roas: float|null}>, campaigns: array<int, array{name: string, channel: string, spend: float, revenue: float, registrations: float, roas: float|null}>, spend_by_day: array<int, array{date: string, amount: float}>}
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
        $pending       = $this->pendingRevenueBySource($shop, $from, $window);
        $registrations = $this->registrationsBy('traffic_source_id', $shop, $from, $window);
        $spend         = $this->spendBySource($shop, $from);
        /* Sending is not free and nobody invoices us for it, so the newsletter channel would show a
           spend of zero and an infinite return. Estimated from the emails actually dispatched. */
        $emailCost     = GetEstimatedEmailCost::run([$shop->id], $from, $shop->currency);
        $visits        = $this->visitsBySource($shop, $from);

        $channels = $sources
            ->map(fn ($source) => [
                'name'          => $source->name,
                'type'          => $source->type,
                'spend'         => round((float) ($spend[$source->id] ?? 0)
                    + ($source->type === TrafficSourcesTypeEnum::NEWSLETTER->value ? $emailCost : 0), 2),
                'spend_is_estimated' => $source->type === TrafficSourcesTypeEnum::NEWSLETTER->value && $emailCost > 0,
                'visits'        => (int) ($visits[$source->id] ?? 0),
                'revenue'       => round((float) ($revenue[$source->id]->amount ?? 0), 2),
                'pending'       => round((float) ($pending[$source->id] ?? 0), 2),
                'registrations' => round((float) ($registrations[$source->id] ?? 0), 2),
            ])
            ->map(fn (array $channel) => $channel + [
                /* A channel that has spent money and taken orders that are not invoiced yet has not
                   returned 0.00x, it has not finished being measured. Zero is only honest once there
                   is nothing pending. */
                'roas' => ($channel['spend'] > 0 && ($channel['revenue'] > 0 || $channel['pending'] <= 0))
                    ? round($channel['revenue'] / $channel['spend'], 2)
                    : null,
            ])
            ->filter(fn (array $channel) => $channel['spend'] > 0 || $channel['revenue'] > 0
                || $channel['registrations'] > 0 || $channel['pending'] > 0 || $channel['visits'] > 0)
            ->sortByDesc(fn (array $channel) => max($channel['spend'], $channel['revenue']))
            ->values()
            ->all();

        $totalSpend         = round(array_sum(array_column($channels, 'spend')), 2);
        $totalRevenue       = round(array_sum(array_column($channels, 'revenue')), 2);
        $totalRegistrations = round(array_sum(array_column($channels, 'registrations')), 2);
        $totalPending       = round(array_sum(array_column($channels, 'pending')), 2);

        return [
            'period'        => $period->value,
            'period_label'  => MarketingPeriodEnum::labels()[$period->value],
            'from'          => $from?->toDateString(),
            'currency_code' => $shop->currency->code,
            'totals'        => [
                'spend'         => $totalSpend,
                'revenue'       => $totalRevenue,
                'registrations' => $totalRegistrations,
                'pending'       => $totalPending,
                'invoices'      => round(collect($revenue)->sum('invoices'), 2),
                'roas'          => ($totalSpend > 0 && ($totalRevenue > 0 || $totalPending <= 0))
                    ? round($totalRevenue / $totalSpend, 2)
                    : null,
                'cac'           => ($totalSpend > 0 && $totalRegistrations > 0)
                    ? round($totalSpend / $totalRegistrations, 2)
                    : null,
            ],
            /* The denominator: 0 attributed registrations out of 4 is noise, 0 out of 300 means every
               ad and mailshot in the period earned us nobody. The remainder is the trade that arrives
               whether we advertise or not. */
            'attribution_started_at' => GetAttributionStartedAt::run()?->toDateTimeString(),
            'baseline'      => $this->baseline($shop, $from),
            'channels'      => $channels,
            'campaigns'     => $this->campaigns($shop, $from),
            'referrers'     => $this->referrers($shop, $from, $window),
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

    /**
     * Registrations a channel or campaign may claim: the same causality the revenue figure obeys. A
     * touch cannot have acquired a customer who was already registered when it happened, which is why
     * newsletter once appeared to acquire a hundred customers it had only mailed.
     */
    /**
     * The leading indicator: value of orders already placed but not yet invoiced. Invoicing runs a
     * day or two behind, and a mailshot sent this morning has to show something today - this is that
     * something, and it drains into the invoiced figure as invoicing catches up. It can shrink when
     * an order is cancelled, which is why it is labelled pending and never added into revenue.
     */
    private function pendingRevenueBySource(Shop $shop, ?Carbon $from, int $window)
    {
        return DB::table('orders')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'orders.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToTouchWindow($join, 'orders.date', $window);
            })
            ->where('orders.shop_id', $shop->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->tap(fn ($query) => $this->whereNotYetInvoiced($query))
            ->when($from, fn ($query) => $query->where('orders.date', '>=', $from))
            ->groupBy('p.traffic_source_id')
            ->select('p.traffic_source_id', DB::raw('SUM(orders.net_amount * p.share) as amount'))
            ->pluck('amount', 'traffic_source_id');
    }

    /**
     * "Not yet invoiced" is decided by looking for the invoice, never by `orders.is_invoiced`: that
     * column is false on every order in production - 7,687 of 8,140 in a month had an invoice while
     * still flagged false - so trusting it counted every invoiced order as pending too, and pending
     * simply repeated revenue.
     *
     * Mirrors the revenue condition (`in_process = false`) so every order lands in exactly one of the
     * two figures.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function whereNotYetInvoiced($query): void
    {
        $query->whereNotExists(fn ($invoice) => $invoice
            ->select(DB::raw(1))
            ->from('invoices')
            ->whereColumn('invoices.order_id', 'orders.id')
            ->where('invoices.in_process', false));
    }

    private function registrationsBy(string $pivotColumn, Shop $shop, ?Carbon $from, int $window)
    {
        return DB::table('customers')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'customers.id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToTouchWindow($join, 'customers.created_at', $window);
            })
            ->where('customers.shop_id', $shop->id)
            ->whereNotNull('p.'.$pivotColumn)
            ->when($from, fn ($query) => $query->where('customers.created_at', '>=', $from))
            ->groupBy('p.'.$pivotColumn)
            ->select('p.'.$pivotColumn, DB::raw('SUM(p.share) as registrations'))
            ->pluck('registrations', $pivotColumn);
    }

    /**
     * Everything that happened in the shop this period, marketing or not.
     *
     * @return array{registrations: float, orders: float, revenue: float}
     */
    /**
     * The baseline exists so the attributed figure can be read as a share of it, which only works if
     * both cover the same stretch of time. Attribution has only been recording since its first touch,
     * so a 30-day baseline against half a day of tracking reports "marketing achieved 0%" when the
     * true statement is "we were not recording for most of that window".
     */
    private function clipToAttributionStart(?Carbon $from): ?Carbon
    {
        $startedAt = GetAttributionStartedAt::run();

        if (!$startedAt) {
            return $from;
        }

        return $from && $from->isAfter($startedAt) ? $from : $startedAt;
    }

    private function baseline(Shop $shop, ?Carbon $from): array
    {
        $from = $this->clipToAttributionStart($from);

        return [
            'registrations' => (float) DB::table('customers')
                ->where('shop_id', $shop->id)
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->count(),

            'orders'        => (float) DB::table('orders')
                ->where('shop_id', $shop->id)
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->whereNull('deleted_at')
                ->when($from, fn ($query) => $query->where('date', '>=', $from))
                ->count(),

            'revenue'       => round((float) DB::table('invoices')
                ->where('shop_id', $shop->id)
                ->where('in_process', false)
                ->when($from, fn ($query) => $query->where('date', '>=', $from))
                ->sum('net_amount'), 2),
        ];
    }

    /**
     * People the channel actually sent us, converted or not. Attribution only ever sees the ones who
     * log in or register, so without this a channel we pay for that sends visitors who all leave is
     * simply absent from the report - which is precisely the case worth seeing.
     */
    private function visitsBySource(Shop $shop, ?Carbon $from)
    {
        return DB::table('traffic_source_visits')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->groupBy('traffic_source_id')
            ->select('traffic_source_id', DB::raw('SUM(visits) as visits'))
            ->pluck('visits', 'traffic_source_id');
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
     * The campaigns that actually moved money or brought someone in during the period, richest first. Campaign refs come from
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
            )
            ->get()
            ->keyBy('campaign_id');

        $registrations = $this->registrationsBy('traffic_source_campaign_id', $shop, $from, $window);

        $spend = DB::table('traffic_source_costs')
            ->whereNotNull('traffic_source_campaign_id')
            ->where('shop_id', $shop->id)
            ->when($from, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->groupBy('traffic_source_campaign_id')
            ->select('traffic_source_campaign_id', DB::raw('SUM(amount) as spend'))
            ->pluck('spend', 'traffic_source_campaign_id');

        $campaignIds = $revenue->keys()->merge($spend->keys())->merge($registrations->keys())->unique();

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
                'registrations' => round((float) ($registrations[$campaign->id] ?? 0), 2),
            ])
            ->map(fn (array $campaign) => $campaign + [
                'roas' => $campaign['spend'] > 0 ? round($campaign['revenue'] / $campaign['spend'], 2) : null,
            ])
            ->sortByDesc(fn (array $campaign) => max($campaign['spend'], $campaign['revenue']))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The sites actually sending people here, richest first. Each referring host is a campaign of the
     * referral channel, so this is the campaign breakdown narrowed to that one channel: trade
     * directories, blogs, AI assistants. Before referral existed they were all indistinguishable from
     * someone typing the address in.
     *
     * @return array<int, array{host: string, visitors: float, registrations: float, revenue: float}>
     */
    private function referrers(Shop $shop, ?Carbon $from, int $window, int $limit = 10): array
    {
        $referral = DB::table('traffic_sources')
            ->where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::REFERRAL->value)
            ->value('id');

        if (!$referral) {
            return [];
        }

        $registrations = $this->registrationsBy('traffic_source_campaign_id', $shop, $from, $window);

        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->where('p.traffic_source_id', $referral)
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->groupBy('p.traffic_source_campaign_id')
            ->select('p.traffic_source_campaign_id as campaign_id', DB::raw('SUM(invoices.net_amount * p.share) as revenue'))
            ->pluck('revenue', 'campaign_id');

        /* Counted as well as valued: a site that has only just started sending people is the whole
           point of this block, and it would be invisible if it had to have earned money first. */
        $touches = DB::table('model_has_traffic_sources')
            ->where('model_type', 'Customer')
            ->where('traffic_source_id', $referral)
            ->whereNotNull('traffic_source_campaign_id')
            ->groupBy('traffic_source_campaign_id')
            ->select('traffic_source_campaign_id', DB::raw('SUM(share) as touches'))
            ->pluck('touches', 'traffic_source_campaign_id');

        return DB::table('traffic_source_campaigns')
            ->where('traffic_source_id', $referral)
            ->select('id', 'name')
            ->get()
            ->map(fn ($campaign) => [
                'host'          => $campaign->name,
                'visitors'      => round((float) ($touches[$campaign->id] ?? 0), 2),
                'registrations' => round((float) ($registrations[$campaign->id] ?? 0), 2),
                'revenue'       => round((float) ($revenue[$campaign->id] ?? 0), 2),
            ])
            ->filter(fn (array $referrer) => $referrer['registrations'] > 0 || $referrer['revenue'] > 0
                || $referrer['visitors'] > 0)
            ->sortByDesc(fn (array $referrer) => [$referrer['revenue'], $referrer['visitors']])
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
