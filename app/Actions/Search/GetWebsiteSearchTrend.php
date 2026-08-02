<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteSearchTrend
{
    use AsObject;

    /**
     * Daily search/click/zero-result counts, gap filled so the chart has a point per day.
     *
     * @param array{query?: string, customer_id?: int} $filters
     *
     * @return array<int, array{day: string, searches: int, clicks: int, zero_results: int}>
     */
    public function handle(Website $website, int $days = 30, array $filters = []): array
    {
        $base = WebsiteSearchLog::where('website_id', $website->id)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());

        if ($query = Arr::get($filters, 'query')) {
            $base->whereRaw('lower(query) = ?', [mb_strtolower($query)]);
        }

        if ($customerId = Arr::get($filters, 'customer_id')) {
            $base->where('customer_id', $customerId);
        }

        if ($clickedUrl = Arr::get($filters, 'clicked_url')) {
            $base->where('clicked_url', $clickedUrl);
        }

        $rows = $base
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as searches, count(clicked_at) as clicks, count(*) filter (where results_count = 0) as zero_results")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $row = $rows->get($day);

            $series[] = [
                'day'          => $day,
                'searches'     => (int)($row->searches ?? 0),
                'clicks'       => (int)($row->clicks ?? 0),
                'zero_results' => (int)($row->zero_results ?? 0),
            ];
        }

        return $series;
    }
}
