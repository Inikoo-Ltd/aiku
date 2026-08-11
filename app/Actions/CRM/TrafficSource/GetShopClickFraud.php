<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Support\Carbon;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopClickFraud
{
    use AsAction;

    /**
     * How many clicks looked suspicious rather than fraud proven: a bot verdict on the user agent, or
     * many clicks arriving from one IP. Both rules are computed here at read time - a stored flag
     * would go stale the moment the next click from that IP arrived.
     *
     * Clicks only exist since the recorder went live (8 Aug 2026) and are pruned after 90 days, so
     * this reads whichever is shorter: the period or the retention window.
     *
     * @return array{from: string|null, to: string|null, totals: array{clicks: int, bots: int, bot_pct: float|null, ips: int, repeats: int}, channels: array<int, array{channel: string, clicks: int, bots: int, bot_pct: float|null}>, suspect_ips: array<int, array{ip: string, country: string|null, clicks: int, bots: int, channels: string, device: string|null, first_seen: string, last_seen: string}>, recent_bots: array<int, array{at: string, channel: string, campaign_ref: string|null, ip: string|null, country: string|null, device: string|null, url: string|null}>}
     */
    public function handle(Shop $shop, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from?->max(now()->subDays(90)) ?? now()->subDays(90);

        $base = fn () => DB::table('traffic_source_clicks')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $from)
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to));

        $totals = $base()
            ->selectRaw('COUNT(*) AS clicks, COUNT(*) FILTER (WHERE is_bot) AS bots,
                COUNT(DISTINCT ip) AS ips, COUNT(*) FILTER (WHERE is_repeat) AS repeats')
            ->first();

        $channels = $base()
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) AS clicks, COUNT(*) FILTER (WHERE is_bot) AS bots')
            ->orderByDesc('clicks')
            ->get()
            ->map(fn ($row) => [
                'channel' => TrafficSourcesTypeEnum::labels()[$row->type] ?? $row->type,
                'clicks'  => (int) $row->clicks,
                'bots'    => (int) $row->bots,
                'bot_pct' => $row->clicks > 0 ? round($row->bots / $row->clicks * 100, 1) : null,
            ])
            ->all();

        /* Five clicks from one IP inside the window is where curiosity starts; a single bot click
           qualifies on its own. The table is evidence to look at, not an accusation. */
        $suspectIps = $base()
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->havingRaw('COUNT(*) >= 5 OR bool_or(is_bot)')
            ->selectRaw("ip, MAX(country_code) AS country, COUNT(*) AS clicks,
                COUNT(*) FILTER (WHERE is_bot) AS bots,
                string_agg(DISTINCT type, ', ') AS channels, MAX(device_type) AS device,
                MIN(created_at) AS first_seen, MAX(created_at) AS last_seen")
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'ip'         => $row->ip,
                'country'    => $row->country,
                'clicks'     => (int) $row->clicks,
                'bots'       => (int) $row->bots,
                'channels'   => $row->channels,
                'device'     => $row->device,
                'first_seen' => $row->first_seen,
                'last_seen'  => $row->last_seen,
            ])
            ->all();

        $recentBots = $base()
            ->where('is_bot', true)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['created_at', 'type', 'campaign_ref', 'ip', 'country_code', 'device_type', 'url'])
            ->map(fn ($row) => [
                'at'           => $row->created_at,
                'channel'      => TrafficSourcesTypeEnum::labels()[$row->type] ?? $row->type,
                'campaign_ref' => $row->campaign_ref,
                'ip'           => $row->ip,
                'country'      => $row->country_code,
                'device'       => $row->device_type,
                'url'          => $row->url,
            ])
            ->all();

        return [
            'from'         => $from->toDateString(),
            'to'           => $to?->toDateString(),
            'totals'       => [
                'clicks'  => (int) $totals->clicks,
                'bots'    => (int) $totals->bots,
                'bot_pct' => $totals->clicks > 0 ? round($totals->bots / $totals->clicks * 100, 1) : null,
                'ips'     => (int) $totals->ips,
                'repeats' => (int) $totals->repeats,
            ],
            'channels'     => $channels,
            'suspect_ips'  => $suspectIps,
            'recent_bots'  => $recentBots,
        ];
    }
}
