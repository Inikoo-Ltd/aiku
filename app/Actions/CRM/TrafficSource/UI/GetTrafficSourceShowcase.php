<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\UI;

use App\Actions\CRM\TrafficSource\GetEstimatedEmailCost;
use App\Actions\CRM\TrafficSource\WithAggregatedChannelQueries;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\TrafficSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTrafficSourceShowcase
{
    use AsObject;
    use WithAggregatedChannelQueries;

    /**
     * What this channel has done since it started being measured. Deliberately period-less: the
     * marketing dashboard answers "how did it do last month", this page answers "what is this
     * channel", so the two are not two versions of the same screen.
     *
     * @return array{channel: array<string, mixed>, stats: array<string, float|int|null>, activity: array{first_visit: string|null, last_visit: string|null}, campaigns: array<int, array<string, mixed>>, currency_code: string}
     */
    public function handle(TrafficSource $trafficSource): array
    {
        $type  = TrafficSourcesTypeEnum::tryFrom($trafficSource->type);
        $stats = $trafficSource->stats;
        /* Everything here starts where attribution started recording. Before that date there is spend
           but no attributed return, and dividing one by the other reports a channel that lost money
           when the truth is that we were not measuring. */
        $from  = $this->clipToAttributionStart(null);

        $revenue = round((float) ($stats->total_customer_revenue ?? 0), 2);
        /* Sending is not free and nobody invoices us for it, so an email channel would otherwise show
           no cost and an infinite return. Estimated from the messages actually sent, exactly as the
           dashboards estimate it. */
        $emailCost = $type ? $this->estimatedEmailCost($trafficSource, $type, $from) : 0;
        $cost      = round((float) ($stats->total_cost ?? 0) + $emailCost, 2);
        /* Share-weighted: a customer touched by three channels is a third of a registration here, so
           the channels add up to the customer rather than to three of them. */
        $customers  = round((float) ($stats->number_customers ?? 0), 2);
        $purchases  = round((float) ($stats->number_customer_purchases ?? 0), 2);
        $visits     = $this->visits($trafficSource);
        /* Orders this channel earned that nobody has invoiced yet. Read against revenue below: a
           channel with spend and nothing but pending has not returned zero, it has not finished
           being measured. */
        $pending    = round((float) $this->pendingRevenueByType(
            collect([$trafficSource->shop]),
            $from,
            null,
            'net_amount',
            'ts.type',
            $trafficSource->type
        )->sum(), 2);

        return [
            'currency_code' => $trafficSource->shop->currency->code,
            'channel'       => [
                'name'        => $trafficSource->name,
                'type'        => $trafficSource->type,
                'type_label'  => $type ? (TrafficSourcesTypeEnum::labels()[$type->value] ?? $trafficSource->type) : $trafficSource->type,
                'group_label' => $type?->group()['label'] ?? __('Other'),
                'is_paid'     => (bool) $type?->isPaid(),
                'status'      => $trafficSource->status,
            ],
            'stats'         => [
                'visits'        => $visits['total'],
                'customers'     => $customers,
                'purchases'     => $purchases,
                'revenue'       => $revenue,
                'pending'       => $pending,
                'cost'          => $cost,
                'cost_is_estimated' => $emailCost > 0,
                /* Zero return on money spent is only honest once there is money spent to divide by
                   and nothing still waiting to be invoiced. */
                'roas'          => ($cost > 0 && ($revenue > 0 || $pending <= 0)) ? round($revenue / $cost, 2) : null,
                'cac'           => ($cost > 0 && $customers > 0) ? round($cost / $customers, 2) : null,
                'per_customer'  => $customers > 0 ? round($revenue / $customers, 2) : null,
            ],
            'activity'      => [
                'first_visit' => $visits['first'],
                'last_visit'  => $visits['last'],
            ],
            'campaigns'     => $this->campaigns($trafficSource),
        ];
    }

    private function estimatedEmailCost(TrafficSource $trafficSource, TrafficSourcesTypeEnum $type, ?Carbon $from): float
    {
        $shopIds  = [$trafficSource->shop_id];
        $currency = $trafficSource->shop->currency;

        if ($type === TrafficSourcesTypeEnum::EMAIL_AUTOMATED) {
            return GetEstimatedEmailCost::automated($shopIds, $from, null, $currency);
        }

        if (!in_array($type, [TrafficSourcesTypeEnum::NEWSLETTER, TrafficSourcesTypeEnum::MARKETING_MAILSHOT], true)) {
            return 0;
        }

        return GetEstimatedEmailCost::run($shopIds, $from, null, $currency, GetEstimatedEmailCost::typesFor($type));
    }

    /**
     * @return array{total: int, first: string|null, last: string|null}
     */
    private function visits(TrafficSource $trafficSource): array
    {
        $visits = DB::table('traffic_source_visits')
            ->where('traffic_source_id', $trafficSource->id)
            ->selectRaw('COALESCE(SUM(visits), 0) as total, MIN(date) as first_date, MAX(date) as last_date')
            ->first();

        return [
            'total' => (int) ($visits->total ?? 0),
            'first' => $visits->first_date ?? null,
            'last'  => $visits->last_date ?? null,
        ];
    }

    /**
     * The campaigns underneath the channel, worst-earning last. A channel with no campaigns - every
     * organic and email one - simply shows none.
     *
     * @return array<int, array<string, mixed>>
     */
    private function campaigns(TrafficSource $trafficSource): array
    {
        return DB::table('traffic_source_campaigns as c')
            ->leftJoin('traffic_source_campaign_stats as s', 's.traffic_source_campaign_id', '=', 'c.id')
            ->where('c.traffic_source_id', $trafficSource->id)
            ->orderByDesc(DB::raw('COALESCE(s.total_customer_revenue, 0)'))
            ->select('c.name', 'c.reference', 's.number_customers', 's.number_customer_purchases', 's.total_customer_revenue', 's.total_cost')
            ->get()
            ->map(fn ($campaign) => [
                'name'      => $campaign->name,
                'reference' => $campaign->reference,
                'customers' => round((float) ($campaign->number_customers ?? 0), 2),
                'purchases' => round((float) ($campaign->number_customer_purchases ?? 0), 2),
                'revenue'   => round((float) ($campaign->total_customer_revenue ?? 0), 2),
                'cost'      => round((float) ($campaign->total_cost ?? 0), 2),
                'roas'      => ($campaign->total_cost ?? 0) > 0
                    ? round((float) $campaign->total_customer_revenue / (float) $campaign->total_cost, 2)
                    : null,
            ])
            ->all();
    }
}
