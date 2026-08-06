<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopMarketingOverview
{
    use AsAction;

    /**
     * Assembles the numbers the marketing dashboard leads with: total attributed revenue, total ad
     * spend, the ROAS and CAC they imply, and the per-channel spend/revenue comparison. Everything is
     * read from the traffic_source_stats rollups the hydrators maintain, so this is a handful of rows,
     * never an aggregate over customers or orders.
     *
     * All figures are in the shop's currency: total_customer_revenue is summed from
     * customer_stats.sales_all (shop currency) and total_cost is converted to shop currency on import.
     *
     * @return array{currency_code: string, totals: array{spend: float, revenue: float, registrations: int, purchases: int, roas: float|null, cac: float|null}, channels: array<int, array{name: string, type: string, spend: float, revenue: float, registrations: int, roas: float|null}>, spend_by_day: array<int, array{date: string, amount: float}>}
     */
    public function handle(Shop $shop): array
    {
        $rows = DB::table('traffic_sources')
            ->leftJoin('traffic_source_stats', 'traffic_source_stats.traffic_source_id', '=', 'traffic_sources.id')
            ->where('traffic_sources.shop_id', $shop->id)
            ->select(
                'traffic_sources.name',
                'traffic_sources.type',
                DB::raw('COALESCE(traffic_source_stats.number_customers, 0) as registrations'),
                DB::raw('COALESCE(traffic_source_stats.number_customer_purchases, 0) as purchases'),
                DB::raw('COALESCE(traffic_source_stats.total_customer_revenue, 0) as revenue'),
                DB::raw('COALESCE(traffic_source_stats.total_cost, 0) as spend'),
            )
            ->get();

        $spend         = round($rows->sum('spend'), 2);
        $revenue       = round($rows->sum('revenue'), 2);
        $registrations = (int) $rows->sum('registrations');

        $channels = $rows
            ->filter(fn ($row) => $row->spend > 0 || $row->revenue > 0 || $row->registrations > 0)
            ->sortByDesc(fn ($row) => max((float) $row->spend, (float) $row->revenue))
            ->values()
            ->map(fn ($row) => [
                'name'          => $row->name,
                'type'          => $row->type,
                'spend'         => round((float) $row->spend, 2),
                'revenue'       => round((float) $row->revenue, 2),
                'registrations' => (int) $row->registrations,
                'roas'          => $row->spend > 0 ? round($row->revenue / $row->spend, 2) : null,
            ])
            ->all();

        $spendByDay = TrafficSourceCost::where('shop_id', $shop->id)
            ->where('date', '>=', now()->subDays(30)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->select('date', DB::raw('SUM(amount) as amount'))
            ->get()
            ->map(fn ($row) => [
                'date'   => $row->date->toDateString(),
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();

        return [
            'currency_code' => $shop->currency->code,
            'totals'        => [
                'spend'         => $spend,
                'revenue'       => $revenue,
                'registrations' => $registrations,
                'purchases'     => (int) $rows->sum('purchases'),
                'roas'          => $spend > 0 ? round($revenue / $spend, 2) : null,
                'cac'           => ($spend > 0 && $registrations > 0) ? round($spend / $registrations, 2) : null,
            ],
            'channels'      => $channels,
            'spend_by_day'  => $spendByDay,
        ];
    }
}
