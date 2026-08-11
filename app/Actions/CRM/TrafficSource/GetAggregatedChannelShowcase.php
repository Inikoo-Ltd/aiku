<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAggregatedChannelShowcase
{
    use AsAction;
    use WithAggregatedChannelQueries;

    /**
     * One channel seen from above: what it has done across every shop of an organisation, or every
     * organisation of the group, in the parent's own currency. The same shape the shop-level channel
     * page returns, so one screen renders all three levels, with a breakdown row of the level below.
     *
     * Period-less on purpose. The marketing dashboard answers "how did this channel do last month";
     * this page answers "what is this channel", and two screens disagreeing about one figure because
     * they cover different stretches of time is worse than either of them alone.
     *
     * @return array{currency_code: string, channel: array<string, mixed>, stats: array<string, float|int|null>, activity: array{first_visit: string|null, last_visit: string|null}, children: array<int, array<string, mixed>>}
     */
    public function handle(Organisation|Group $parent, TrafficSourcesTypeEnum $type): array
    {
        $shops          = $parent->shops()->get();
        $isOrganisation = $parent instanceof Organisation;
        $revenueColumn  = $isOrganisation ? 'org_net_amount' : 'grp_net_amount';
        $costColumn     = $isOrganisation ? 'org_amount' : 'grp_amount';
        /* Everything on the page starts where attribution started recording. Before that date there
           is spend but no attributed return, and dividing one by the other reports a channel that
           lost money when the truth is that we were not measuring. */
        $from           = $this->clipToAttributionStart(null);

        /* Bucketed by shop rather than by channel: the headline is the sum of the breakdown, so the
           rows a reader can see always add up to the figure above them. */
        $revenue       = $this->revenueByType($shops, $from, null, $revenueColumn, 'invoices.shop_id', $type->value);
        $registrations = $this->registrationsByType($shops, $from, null, 'customers.shop_id', $type->value);
        $orders        = $this->ordersByType($shops, $from, null, 'orders.shop_id', $type->value);
        $pending       = $this->pendingRevenueByType($shops, $from, null, $revenueColumn, 'orders.shop_id', $type->value);
        $spend         = $this->spendByType($shops, $from, null, $costColumn, 'c.shop_id', $type->value);
        $visits        = $this->visitsByType($shops, $from, null, 'v.shop_id', $type->value);

        /* Sending is not free and nobody invoices us for it, so an email channel would otherwise show
           no cost and an infinite return. Estimated from the messages actually sent, and only ever at
           this level: the per-shop split of an estimate would read as a bill. */
        $emailCost = $this->estimatedEmailCost($parent, $shops, $type, $from);

        $totalRevenue       = round((float) $revenue->sum(), 2);
        $totalPending       = round((float) $pending->sum(), 2);
        $totalRegistrations = round((float) $registrations->sum(), 2);
        $totalSpend         = round((float) $spend->sum() + $emailCost, 2);

        return [
            'currency_code' => $parent->currency->code,
            'channel'       => [
                'name'        => TrafficSourcesTypeEnum::labels()[$type->value] ?? $type->value,
                'type'        => $type->value,
                'type_label'  => TrafficSourcesTypeEnum::labels()[$type->value] ?? $type->value,
                'group_label' => $type->group()['label'],
                'is_paid'     => $type->isPaid(),
                'status'      => (bool) (TrafficSourcesTypeEnum::status()[$type->value] ?? true),
            ],
            'stats'         => [
                'visits'       => (int) $visits->sum(),
                'customers'    => $totalRegistrations,
                'purchases'    => round((float) $orders->sum(), 2),
                'revenue'      => $totalRevenue,
                'pending'      => $totalPending,
                'cost'         => $totalSpend,
                'cost_is_estimated' => $emailCost > 0,
                /* A channel that has spent money and taken orders nobody has invoiced yet has not
                   returned 0.00x, it has not finished being measured. */
                'roas'         => ($totalSpend > 0 && ($totalRevenue > 0 || $totalPending <= 0))
                    ? round($totalRevenue / $totalSpend, 2)
                    : null,
                'cac'          => ($totalSpend > 0 && $totalRegistrations > 0)
                    ? round($totalSpend / $totalRegistrations, 2)
                    : null,
                'per_customer' => $totalRegistrations > 0 ? round($totalRevenue / $totalRegistrations, 2) : null,
            ],
            'activity'       => $this->activity($shops, $type),
            'children_label' => $isOrganisation ? __('Shops') : __('Organisations'),
            'children'       => $this->children($parent, $shops, $type, $revenue, $registrations, $orders, $pending, $visits),
        ];
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    private function estimatedEmailCost(Organisation|Group $parent, Collection $shops, TrafficSourcesTypeEnum $type, ?Carbon $from): float
    {
        $shopIds = $shops->pluck('id');

        if ($type === TrafficSourcesTypeEnum::EMAIL_AUTOMATED) {
            return GetEstimatedEmailCost::automated($shopIds, $from, null, $parent->currency);
        }

        if (!in_array($type, [TrafficSourcesTypeEnum::NEWSLETTER, TrafficSourcesTypeEnum::MARKETING_MAILSHOT], true)) {
            return 0;
        }

        return GetEstimatedEmailCost::run($shopIds, $from, null, $parent->currency, GetEstimatedEmailCost::typesFor($type));
    }

    /**
     * @param Collection<int, Shop> $shops
     *
     * @return array{first_visit: string|null, last_visit: string|null}
     */
    private function activity(Collection $shops, TrafficSourcesTypeEnum $type): array
    {
        $dates = DB::table('traffic_source_visits as v')
            ->join('traffic_sources as ts', 'ts.id', '=', 'v.traffic_source_id')
            ->whereIn('v.shop_id', $shops->pluck('id'))
            ->where('ts.type', $type->value)
            ->selectRaw('MIN(v.date) as first_date, MAX(v.date) as last_date')
            ->first();

        return [
            'first_visit' => $dates->first_date ?? null,
            'last_visit'  => $dates->last_date ?? null,
        ];
    }

    /**
     * The level below, each row linking one step further down: an organisation lists its shops and
     * lands on that shop's own channel page, the group lists its organisations and lands on theirs.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return array<int, array<string, mixed>>
     */
    private function children(
        Organisation|Group $parent,
        Collection $shops,
        TrafficSourcesTypeEnum $type,
        Collection $revenue,
        Collection $registrations,
        Collection $orders,
        Collection $pending,
        Collection $visits
    ): array {
        $figures = fn (Collection $shopIds) => [
            'revenue'       => round((float) $shopIds->sum(fn ($id) => $revenue[$id] ?? 0), 2),
            'pending'       => round((float) $shopIds->sum(fn ($id) => $pending[$id] ?? 0), 2),
            'registrations' => round((float) $shopIds->sum(fn ($id) => $registrations[$id] ?? 0), 2),
            'orders'        => round((float) $shopIds->sum(fn ($id) => $orders[$id] ?? 0), 2),
            'visits'        => (int) $shopIds->sum(fn ($id) => $visits[$id] ?? 0),
        ];

        if ($parent instanceof Organisation) {
            /* Straight to the shop's own page for this channel, which is the traffic source row that
               shop keeps for the type. */
            $slugs = DB::table('traffic_sources')
                ->whereIn('shop_id', $shops->pluck('id'))
                ->where('type', $type->value)
                ->pluck('slug', 'shop_id');

            $children = $shops->map(fn (Shop $shop) => array_merge(
                ['name' => $shop->name, 'slug' => $shop->slug],
                $figures(collect([$shop->id])),
                ['route' => isset($slugs[$shop->id]) ? [
                    'name'       => 'grp.org.shops.show.marketing.traffic_sources.show',
                    'parameters' => [$parent->slug, $shop->slug, $slugs[$shop->id]],
                ] : null],
            ));
        } else {
            $shopsByOrganisation = $shops->groupBy('organisation_id');

            $children = $parent->organisations()->get()->map(fn (Organisation $organisation) => array_merge(
                ['name' => $organisation->name, 'slug' => $organisation->slug],
                $figures(($shopsByOrganisation[$organisation->id] ?? collect())->pluck('id')),
                ['route' => [
                    'name'       => 'grp.org.marketing.channels.show',
                    'parameters' => [$organisation->slug, $type->value],
                ]],
            ));
        }

        return $children
            ->filter(fn (array $child) => $child['revenue'] > 0 || $child['registrations'] > 0
                || $child['orders'] > 0 || $child['visits'] > 0 || $child['pending'] > 0)
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
