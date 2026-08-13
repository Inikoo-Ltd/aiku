<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\UI\TrafficSourceTabsEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAggregatedMarketingOverview
{
    use AsAction;
    use WithAggregatedChannelQueries;
    use WithAttributionWindow;

    /**
     * The management view: one marketing picture for a whole organisation or the whole group, with the
     * per-channel figures added up across every shop underneath.
     *
     * Money is reported in the parent's own currency, never the shops': an organisation totals
     * `org_net_amount` and `org_amount`, the group totals `grp_net_amount`. Summing a shop's own
     * amounts across currencies would produce a number that means nothing.
     *
     * Group level deliberately stops at channels. A campaign belongs to one shop, so a group-wide
     * campaign table would be a list of other people's campaigns; the children table links down to
     * each organisation instead, and the drill-down continues on that dashboard.
     *
     * @return array{from: string|null, to: string|null, currency_code: string, totals: array{spend: float, revenue: float, registrations: float, unsubscribed: int, orders: float, roas: float|null, cac: float|null}, channels: array<int, array{name: string, type: string, registrations_route: array{name: string, parameters: array<string, string>}, spend: float, revenue: float, registrations: float, orders: float, roas: float|null}>, baseline: array{registrations: float, orders: float, revenue: float}, children: array<int, array{name: string, slug: string, revenue: float, registrations: float, registrations_total: int, orders: float, orders_total: int, pending: float, revenue_total: float, top_channel: string|null, route: array{name: string, parameters: array<int, string>}}>}
     */
    public function handle(Organisation|Group $parent, ?Carbon $from = null, ?Carbon $to = null): array
    {
        /* One window for the whole screen. Everything - revenue, orders, sign-ups, spend, unsubscribes,
           visits, the baseline they are shares of - starts when attribution started recording, so no
           two figures on the page cover different stretches of time. Before that date the report reads
           zero, which is the truthful answer: we were not measuring. */
        $from  = $this->clipToAttributionStart($from);
        $shops = $parent->shops()->get();

        $isOrganisation = $parent instanceof Organisation;
        $revenueColumn  = $isOrganisation ? 'org_net_amount' : 'grp_net_amount';
        $costColumn     = $isOrganisation ? 'org_amount' : 'grp_amount';

        $window        = (int) $this->shopsByWindow($shops)->first()['window'] ?? 0;

        /* Cost is measured over the same window as the return it will be divided by. Thirty days of
           mailshots against half a day of attributable revenue is not a return on ad spend, it is two
           different questions divided by each other. Sending is not free either, and nobody invoices
           us for it, so the newsletter's cost is estimated from the emails actually sent. */
        $costFrom      = $from;
        $costTo        = $to;

        /* Every attributed figure is bucketed by shop and channel at once, because the screen needs
           both: the headline reads it by channel, the children table by shop. Asking twice meant
           scanning the same invoices, orders and customers twice for arithmetic that differs only in
           which way the same rows are added up. */
        $attributed    = [
            'revenue'       => $this->revenueByShopAndType($shops, $from, $to, $revenueColumn),
            'registrations' => $this->registrationsByShopAndType($shops, $from, $to),
            'orders'        => $this->ordersByShopAndType($shops, $from, $to),
            'pending'       => $this->pendingRevenueByShopAndType($shops, $from, $to, $revenueColumn),
        ];

        $revenue       = $this->totalsByType($attributed['revenue']);
        $registrations = $this->totalsByType($attributed['registrations']);
        $orders        = $this->totalsByType($attributed['orders']);
        $pending       = $this->totalsByType($attributed['pending']);
        $spend         = $this->spendByType($shops, $costFrom, $costTo, $costColumn);

        $shopIds       = $shops->pluck('id');
        $costPerEmail  = GetEstimatedEmailCost::make()->costPerEmail($parent->currency);

        /* Per email channel: a newsletter and a promotional mailshot cost separately and lose
           subscribers separately, so averaging them would hide which one is doing the damage. */
        $mailshotTotals = GetEstimatedEmailCost::totalsByType($shopIds, $costFrom, $costTo);
        $emailCostBy    = [];
        $unsubsBy       = [];

        foreach ([TrafficSourcesTypeEnum::NEWSLETTER, TrafficSourcesTypeEnum::MARKETING_MAILSHOT] as $emailChannel) {
            $channelTotals = GetEstimatedEmailCost::forChannel($mailshotTotals, $emailChannel, $costPerEmail);

            $emailCostBy[$emailChannel->value] = $channelTotals['cost'];
            $unsubsBy[$emailChannel->value]    = $channelTotals['unsubscribed'];
        }

        /* Automated marketing is billed per message like everything else we send. */
        $emailCostBy[TrafficSourcesTypeEnum::EMAIL_AUTOMATED->value] = GetEstimatedEmailCost::automated($shopIds, $costFrom, $costTo, $parent->currency, $costPerEmail);

        $emailCost = array_sum($emailCostBy);
        $visits        = $this->visitsByType($shops, $from, $to);

        $channels = collect(array_unique(array_merge(
            $revenue->keys()->all(),
            $registrations->keys()->all(),
            $orders->keys()->all(),
            $spend->keys()->all(),
            $visits->keys()->all(),
            /* Every email channel that cost something, not just the newsletter: a channel with spend
               and no touches yet is exactly the one worth seeing. */
            array_keys(array_filter($emailCostBy)),
        )))
            ->map(fn (string $type) => [
                'name'          => TrafficSourcesTypeEnum::labels()[$type] ?? $type,
                'type'          => $type,
                'route'         => $this->channelRoute($parent, $type),
                'registrations_route' => $this->registrationsRoute($parent, $type, $from, $to),
                'orders_route'  => $this->ordersRoute($parent, $type, $from, $to),
                'group'         => TrafficSourcesTypeEnum::tryFrom($type)?->group()['key'] ?? 'other',
                'group_label'   => TrafficSourcesTypeEnum::tryFrom($type)?->group()['label'] ?? __('Other'),
                'group_position' => TrafficSourcesTypeEnum::tryFrom($type)?->group()['position'] ?? 9,
                'spend'         => round((float) ($spend[$type] ?? 0)
                    + ($emailCostBy[$type] ?? 0), 2),
                'spend_is_estimated' => ($emailCostBy[$type] ?? 0) > 0,
                /* Not netted off registrations: an unsubscribe is not a lost customer, it is lost
                   permission to email one. */
                'unsubscribed'  => (int) ($unsubsBy[$type] ?? 0),
                'revenue'       => round((float) ($revenue[$type] ?? 0), 2),
                'registrations' => round((float) ($registrations[$type] ?? 0), 2),
                'orders'        => round((float) ($orders[$type] ?? 0), 2),
                'pending'       => round((float) ($pending[$type] ?? 0), 2),
                'visits'        => (int) ($visits[$type] ?? 0),
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
                || $channel['registrations'] > 0 || $channel['orders'] > 0 || $channel['pending'] > 0
                || $channel['visits'] > 0)
            /* Money first, then the traffic behind it, and the name last so that channels which have
               yet to earn or cost anything still land in the same order every load. Ties used to fall
               back on whatever order the database happened to return the rows in. */
            ->sortBy(fn (array $channel) => [
                -max($channel['spend'], $channel['revenue']),
                -$channel['revenue'],
                -$channel['registrations'],
                -$channel['orders'],
                -$channel['visits'],
                $channel['type'],
            ])
            ->values()
            ->all();

        $totalSpend         = round(array_sum(array_column($channels, 'spend')), 2);
        $totalRevenue       = round(array_sum(array_column($channels, 'revenue')), 2);
        $totalRegistrations = round(array_sum(array_column($channels, 'registrations')), 2);
        $totalPending       = round(array_sum(array_column($channels, 'pending')), 2);

        $baselineByShop     = $this->baselineByShop($shops, $from, $to, $revenueColumn);

        return [
            'from'          => $from?->toDateString(),
            'to'            => $to?->toDateString(),
            'currency_code' => $parent->currency->code,
            'totals'        => [
                'spend'         => $totalSpend,
                /* Split because they are different kinds of money: one is invoiced by an ad platform,
                   the other is our own estimate of what sending the emails cost. Totalled for ROAS,
                   shown apart so nobody reads an estimate as a bill. */
                'spend_ads'     => round($totalSpend - $emailCost, 2),
                'spend_email'   => round($emailCost, 2),
                'revenue'       => $totalRevenue,
                'registrations' => $totalRegistrations,
                /* Beside the sign-ups, never taken off them: an unsubscribe costs permission to email
                   somebody, not the customer, and netting the two would report a number that means
                   neither. */
                'unsubscribed'  => (int) array_sum(array_column($channels, 'unsubscribed')),
                'orders'        => round(array_sum(array_column($channels, 'orders')), 2),
                'pending'       => $totalPending,
                'roas'          => ($totalSpend > 0 && ($totalRevenue > 0 || $totalPending <= 0))
                    ? round($totalRevenue / $totalSpend, 2)
                    : null,
                'cac'           => ($totalSpend > 0 && $totalRegistrations > 0)
                    ? round($totalSpend / $totalRegistrations, 2)
                    : null,
            ],
            /* The denominator. Attributed figures alone cannot tell "marketing produced nothing" from
               "nothing happened": 0 registrations out of 4 is noise, 0 out of 300 means every ad and
               every mailshot in the period earned us nobody. The remainder is the trade that arrives
               whether we advertise or not. */
            'attribution_started_at' => GetAttributionStartedAt::run()?->toIso8601String(),
            'baseline'      => $this->baseline($baselineByShop),
            'channels'      => $channels,
            'referrers'     => $this->referrers($shops, $from, $to, $revenueColumn, $window ?? 0),
            'children'      => $this->children($parent, $shops, $attributed, $baselineByShop),
        ];
    }

    /**
     * The channel's own page at this level. No period travels with it: that page is the channel's
     * standing overview, not a slice of this dashboard's window.
     *
     * @return array{name: string, parameters: array<int, string>}
     */
    private function channelRoute(Organisation|Group $parent, string $type): array
    {
        return $parent instanceof Organisation
            ? ['name' => 'grp.org.marketing.channels.show', 'parameters' => [$parent->slug, $type]]
            : ['name' => 'grp.marketing.channels.show', 'parameters' => [$type]];
    }

    /**
     * The customers the channel signed up, on the customers tab of that same page, over the
     * dashboard's own dates: the figure being clicked counts a period, so the list it opens has to
     * cover the same one.
     *
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function registrationsRoute(Organisation|Group $parent, string $type, ?Carbon $from, ?Carbon $to): array
    {
        return $this->tabRoute($parent, $type, TrafficSourceTabsEnum::CUSTOMERS, $this->periodFilter($from, $to, 'created_at'));
    }

    /**
     * The orders the channel was touched before, on the orders tab of that same page, over the
     * dashboard's own dates.
     *
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function ordersRoute(Organisation|Group $parent, string $type, ?Carbon $from, ?Carbon $to): array
    {
        return $this->tabRoute($parent, $type, TrafficSourceTabsEnum::ORDERS, $this->periodFilter($from, $to, 'date'));
    }

    /**
     * @param array<string, array<string, string>> $period
     *
     * @return array{name: string, parameters: array<string, mixed>}
     */
    private function tabRoute(Organisation|Group $parent, string $type, TrafficSourceTabsEnum $tab, array $period): array
    {
        $parameters = [
            'channelType' => $type,
            'tab'         => $tab->value,
        ] + $period;

        return $parent instanceof Organisation
            ? ['name' => 'grp.org.marketing.channels.show', 'parameters' => ['organisation' => $parent->slug] + $parameters]
            : ['name' => 'grp.marketing.channels.show', 'parameters' => $parameters];
    }

    /**
     * The dashboard's window as a table takes it: a between filter on the same column the figure was
     * counted over. An open-ended period travels as no filter at all.
     *
     * @return array<string, array<string, string>>
     */
    private function periodFilter(?Carbon $from, ?Carbon $to, string $column): array
    {
        if (!$from) {
            return [];
        }

        return [
            'between' => [
                $column => $from->format('Ymd').'-'.($to ?? Carbon::now())->format('Ymd'),
            ],
        ];
    }

    /**
     * Everything that happened in the period, marketing or not, so the attributed figures can be read
     * as a proportion of it. Bucketed by shop because each child row carries its own denominator too:
     * "0" against nothing reads as a quiet month, "0 of 123" says marketing reached none of the
     * people who signed up.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return array{registrations: Collection<int, int>, orders: Collection<int, int>, revenue: Collection<int, float>}
     */
    private function baselineByShop(Collection $shops, ?Carbon $from, ?Carbon $to, string $revenueColumn): array
    {
        $shopIds = $shops->pluck('id');

        return [
            'registrations' => DB::table('customers')
                ->whereIn('shop_id', $shopIds)
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
                ->groupBy('shop_id')
                ->select('shop_id', DB::raw('COUNT(*) as total'))
                ->pluck('total', 'shop_id'),

            'orders'        => DB::table('orders')
                ->whereIn('shop_id', $shopIds)
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->whereNull('deleted_at')
                ->when($from, fn ($query) => $query->where('date', '>=', $from))
                ->when($to, fn ($query) => $query->where('date', '<=', $to))
                ->groupBy('shop_id')
                ->select('shop_id', DB::raw('COUNT(*) as total'))
                ->pluck('total', 'shop_id'),

            'revenue'       => DB::table('invoices')
                ->whereIn('shop_id', $shopIds)
                ->where('in_process', false)
                ->when($from, fn ($query) => $query->where('date', '>=', $from))
                ->when($to, fn ($query) => $query->where('date', '<=', $to))
                ->groupBy('shop_id')
                ->select('shop_id', DB::raw("SUM({$revenueColumn}) as total"))
                ->pluck('total', 'shop_id'),
        ];
    }

    /**
     * @param array{registrations: Collection<int, int>, orders: Collection<int, int>, revenue: Collection<int, float>} $baselineByShop
     *
     * @return array{registrations: float, orders: float, revenue: float}
     */
    private function baseline(array $baselineByShop): array
    {
        return [
            'registrations' => (float) $baselineByShop['registrations']->sum(),
            'orders'        => (float) $baselineByShop['orders']->sum(),
            'revenue'       => round((float) $baselineByShop['revenue']->sum(), 2),
        ];
    }

    /**
     * The single channel earning the most for each shop, so the children table can say which one it
     * is. A shop whose every channel is flat or negative has no top channel rather than a losing one.
     *
     * @param Collection<int, Collection<string, float>> $revenueByShopAndType
     *
     * @return Collection<int, array{name: string, amount: float}>
     */
    private function topChannelByShop(Collection $revenueByShopAndType): Collection
    {
        return $revenueByShopAndType
            ->map(fn (Collection $byType) => $byType->filter(fn (float $amount) => $amount > 0)->sortDesc())
            ->filter(fn (Collection $byType) => $byType->isNotEmpty())
            ->map(fn (Collection $byType) => [
                'name'   => TrafficSourcesTypeEnum::labels()[$byType->keys()->first()] ?? $byType->keys()->first(),
                'amount' => (float) $byType->first(),
            ]);
    }

    /**
     * The sites sending people to any shop underneath, pooled by host. A trade directory that feeds
     * three shops is one entry here, which is the whole reason to look at this level.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array{host: string, kind: string, visitors: float, revenue: float}>
     */
    private function referrers(Collection $shops, ?Carbon $from, ?Carbon $to, string $revenueColumn, int $window, int $limit = 10): array
    {
        /* Search engines belong here as much as directories do: knowing DuckDuckGo sends people is
           what tells you whether it is worth advertising on. They keep their own channel for the
           totals, but this list is about which individual sites send us anybody. */
        $kindBySource = DB::table('traffic_sources')
            ->whereIn('shop_id', $shops->pluck('id'))
            ->whereIn('type', [
                TrafficSourcesTypeEnum::REFERRAL->value,
                TrafficSourcesTypeEnum::ORGANIC_SEARCH->value,
            ])
            ->pluck('type', 'id');

        if ($kindBySource->isEmpty()) {
            return [];
        }

        $campaigns = DB::table('traffic_source_campaigns')
            ->whereIn('traffic_source_id', $kindBySource->keys())
            ->get(['id', 'name', 'traffic_source_id'])
            ->keyBy('id');

        if ($campaigns->isEmpty()) {
            return [];
        }

        $visitors = DB::table('model_has_traffic_sources')
            ->where('model_type', 'Customer')
            ->whereIn('traffic_source_campaign_id', $campaigns->keys())
            ->groupBy('traffic_source_campaign_id')
            ->select('traffic_source_campaign_id', DB::raw('SUM(share) as visitors'))
            ->pluck('visitors', 'traffic_source_campaign_id');

        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->whereIn('p.traffic_source_campaign_id', $campaigns->keys())
            ->where('invoices.in_process', false)
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->when($to, fn ($query) => $query->where('invoices.date', '<=', $to))
            ->groupBy('p.traffic_source_campaign_id')
            ->select('p.traffic_source_campaign_id as campaign_id', DB::raw("SUM(invoices.{$revenueColumn} * p.share) as revenue"))
            ->pluck('revenue', 'campaign_id');

        return $campaigns
            ->map(fn ($campaign) => [
                'host'     => $campaign->name,
                'kind'     => ($kindBySource[$campaign->traffic_source_id] ?? '') === TrafficSourcesTypeEnum::ORGANIC_SEARCH->value
                    ? 'search'
                    : 'site',
                'visitors' => round((float) ($visitors[$campaign->id] ?? 0), 2),
                'revenue'  => round((float) ($revenue[$campaign->id] ?? 0), 2),
            ])
            ->filter(fn (array $referrer) => $referrer['visitors'] > 0 || $referrer['revenue'] > 0)
            ->sortByDesc(fn (array $referrer) => [$referrer['revenue'], $referrer['visitors']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The row under the headline: each shop of an organisation, each organisation of the group, with
     * a link onward. Every figure is rolled up from what the headline already counted, so the table
     * costs no queries of its own - the drill-down is the existing dashboard for that level rather
     * than a repeat of it.
     *
     * @param Collection<int, Shop>                                                                                    $shops
     * @param array<string, Collection<int, Collection<string, float>>>                                                $attributed
     * @param array{registrations: Collection<int, int>, orders: Collection<int, int>, revenue: Collection<int, float>} $baselineByShop
     *
     * @return array<int, array{name: string, slug: string, revenue: float, registrations: float, orders: float, route: array{name: string, parameters: array<int, string>}}>
     */
    private function children(Organisation|Group $parent, Collection $shops, array $attributed, array $baselineByShop): array
    {
        $revenue       = $this->totalsByShop($attributed['revenue']);
        $registrations = $this->totalsByShop($attributed['registrations']);
        $orders        = $this->totalsByShop($attributed['orders']);
        $childPending  = $this->totalsByShop($attributed['pending']);
        $topChannel    = $this->topChannelByShop($attributed['revenue']);

        $allRegistrations = $baselineByShop['registrations'];
        $allRevenue       = $baselineByShop['revenue'];
        $allOrders        = $baselineByShop['orders'];

        $figures = fn (Collection $shopIds) => [
            'revenue'       => round($shopIds->sum(fn ($id) => $revenue[$id] ?? 0), 2),
            'registrations' => round($shopIds->sum(fn ($id) => $registrations[$id] ?? 0), 2),
            'orders'        => round($shopIds->sum(fn ($id) => $orders[$id] ?? 0), 2),
            'pending'             => round($shopIds->sum(fn ($id) => $childPending[$id] ?? 0), 2),
            'revenue_total'       => round((float) $shopIds->sum(fn ($id) => $allRevenue[$id] ?? 0), 2),
            'registrations_total' => (int) $shopIds->sum(fn ($id) => $allRegistrations[$id] ?? 0),
            'orders_total'        => (int) $shopIds->sum(fn ($id) => $allOrders[$id] ?? 0),
            /* Names the channel doing the work, so the row says something about marketing rather than
               just repeating a shop's name next to a number. */
            'top_channel'   => $shopIds
                ->map(fn ($id) => $topChannel[$id] ?? null)
                ->filter()
                ->sortByDesc('amount')
                ->first()['name'] ?? null,
        ];

        if ($parent instanceof Organisation) {
            $children = $shops->map(fn (Shop $shop) => array_merge(
                ['name' => $shop->name, 'slug' => $shop->slug],
                $figures(collect([$shop->id])),
                ['route' => [
                    'name'       => 'grp.org.shops.show.marketing.dashboard',
                    'parameters' => [$parent->slug, $shop->slug],
                ]],
            ));
        } else {
            $shopsByOrganisation = $shops->groupBy('organisation_id');

            $children = $parent->organisations()->get()->map(fn (Organisation $organisation) => array_merge(
                ['name' => $organisation->name, 'slug' => $organisation->slug],
                $figures(($shopsByOrganisation[$organisation->id] ?? collect())->pluck('id')),
                ['route' => [
                    'name'       => 'grp.org.marketing.dashboard',
                    'parameters' => [$organisation->slug],
                ]],
            ));
        }

        return $children
            ->filter(fn (array $child) => $child['revenue'] > 0 || $child['registrations'] > 0 || $child['orders'] > 0)
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
