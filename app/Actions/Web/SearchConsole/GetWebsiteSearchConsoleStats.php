<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\SearchConsole;

use App\Models\Web\Website;
use App\Services\SearchConsole\SearchConsoleClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchConsoleStats
{
    use AsObject;

    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null): array
    {
        $isConnected = (bool) Arr::get($website->data, 'gcp.siteUrl');

        $websiteDays = DB::connection('aiku_no_sticky')->table('search_console_website_days')
            ->where('website_id', $website->id);

        $latestStoredDay = (clone $websiteDays)->max('date');

        $daily = $websiteDays
            ->when($fromDate, fn ($query) => $query->where('date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('date')
            ->orderBy('date')
            ->select('date')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(position * impressions) as weighted_position')
            ->get();

        $clicks      = (int) $daily->sum('clicks');
        $impressions = (int) $daily->sum('impressions');

        return [
            'is_connected'          => $isConnected,
            'site_url'              => Arr::get($website->data, 'gcp.siteUrl'),
            'service_account_email' => $isConnected ? null : SearchConsoleClient::serviceAccountEmail($website),
            'latest_stored_day'     => $latestStoredDay,
            'days_with_data'        => $daily->count(),
            'first_day'             => $daily->first()?->date,
            'last_day'              => $daily->last()?->date,
            'clicks'                => $clicks,
            'impressions'           => $impressions,
            'ctr'                   => $impressions > 0 ? round($clicks * 100 / $impressions, 2) : 0,
            'position'              => $impressions > 0 ? round($daily->sum('weighted_position') / $impressions, 1) : null,
            'daily'                 => $daily->map(fn ($day) => [
                'day'         => $day->date,
                'clicks'      => (int) $day->clicks,
                'impressions' => (int) $day->impressions,
            ])->values()->all(),
        ];
    }
}
