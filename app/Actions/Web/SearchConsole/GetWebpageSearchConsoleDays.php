<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\SearchConsole;

use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebpageSearchConsoleDays
{
    use AsObject;

    /**
     * @return array<int, array{keys: array<int, string>, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function handle(Webpage $webpage, string $fromDate, string $toDate): array
    {
        return DB::connection('aiku_no_sticky')->table('search_console_page_days')
            ->where('webpage_id', $webpage->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->groupBy('date')
            ->orderBy('date')
            ->select('date')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(position * impressions) / NULLIF(SUM(impressions), 0) as position')
            ->get()
            ->map(fn ($day) => [
                'keys'        => [$day->date],
                'clicks'      => (int) $day->clicks,
                'impressions' => (int) $day->impressions,
                'ctr'         => $day->impressions > 0 ? $day->clicks / $day->impressions : 0,
                'position'    => round((float) $day->position, 1),
            ])
            ->all();
    }
}
