<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebVital;

use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The daily 75th percentile of the samples our visitors' browsers send, the same figure Google
 * reports. A day needs a few samples before its percentile means anything.
 */
class GetWebVitalsReport
{
    use AsAction;

    public const int DAYS = 90;

    public const int MIN_SAMPLES = 5;

    private const int WEBSITE_CACHE_MINUTES = 60;

    /**
     * @return array{scope: string|null, url: string|null, history: array<string, array<int, array<string, mixed>>>}
     */
    public function handle(Website $website, ?Webpage $webpage = null): array
    {
        if ($webpage) {
            $history = $this->history($website, $webpage);

            if ($history) {
                return ['scope' => 'page', 'url' => null, 'history' => $history];
            }
        }

        $history = cache()->remember(
            "web-vitals-report:$website->id",
            now()->addMinutes(self::WEBSITE_CACHE_MINUTES),
            fn () => $this->history($website, null)
        );

        return ['scope' => $history ? 'website' : null, 'url' => null, 'history' => $history];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function history(Website $website, ?Webpage $webpage): array
    {
        $percentiles = collect(['lcp', 'inp', 'cls', 'fcp', 'ttfb'])
            ->map(fn (string $metric) => "percentile_cont(0.75) within group (order by $metric) as $metric")
            ->implode(', ');

        $rows = DB::table('web_vital_samples')
            ->selectRaw("(created_at at time zone 'UTC')::date as day, coalesce(device, 'all') as form_factor, count(*) as samples, $percentiles")
            ->where('website_id', $website->id)
            ->when($webpage, fn ($query) => $query->where('webpage_id', $webpage->id))
            ->where('created_at', '>=', now()->subDays(self::DAYS)->startOfDay())
            ->groupByRaw('grouping sets ((day, device), (day))')
            ->havingRaw('count(*) >= ?', [self::MIN_SAMPLES])
            ->orderBy('day')
            ->get();

        return $rows->groupBy('form_factor')
            ->map(fn ($days) => $days->map(fn ($row) => [
                'period_start' => $row->day,
                'period_end'   => $row->day,
                'samples'      => (int)$row->samples,
                'lcp'          => $row->lcp === null ? null : (int)round($row->lcp),
                'inp'          => $row->inp === null ? null : (int)round($row->inp),
                'cls'          => $row->cls === null ? null : round((float)$row->cls, 3),
                'fcp'          => $row->fcp === null ? null : (int)round($row->fcp),
                'ttfb'         => $row->ttfb === null ? null : (int)round($row->ttfb),
                'histograms'   => [],
            ])->values()->all())
            ->all();
    }
}
