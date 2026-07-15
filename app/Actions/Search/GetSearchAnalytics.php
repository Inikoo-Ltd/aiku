<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\SearchLog;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSearchAnalytics
{
    use AsObject;

    public function handle(Group $group, ?int $organisationId = null, int $days = 30): array
    {
        $base = SearchLog::where('group_id', $group->id)
            ->where('created_at', '>=', now()->subDays($days));
        if ($organisationId) {
            $base->where('organisation_id', $organisationId);
        }

        $totalSearches = (clone $base)->count();
        $clicked       = (clone $base)->whereNotNull('clicked_at')->count();
        $zeroResults   = (clone $base)->where('results_count', 0)->count();

        $topQueries = $this->groupedQueries(clone $base)
            ->orderByDesc('searches')
            ->orderByDesc('clicks')
            ->orderBy('query')
            ->limit(10)
            ->get();

        $topZeroQueries = $this->groupedQueries((clone $base)->where('results_count', 0))
            ->orderByDesc('searches')
            ->orderBy('query')
            ->limit(10)
            ->get();

        return [
            'days'              => $days,
            'total_searches'    => $totalSearches,
            'click_through'     => $totalSearches ? round($clicked / $totalSearches * 100, 1) : 0,
            'zero_results_rate' => $totalSearches ? round($zeroResults / $totalSearches * 100, 1) : 0,
            'top_queries'       => $topQueries,
            'top_zero_queries'  => $topZeroQueries,
        ];
    }

    protected function groupedQueries(Builder $query): Builder
    {
        return $query
            ->whereRaw('char_length(query) >= 3')
            ->selectRaw('lower(query) as query, count(*) as searches, count(clicked_at) as clicks')
            ->groupByRaw('lower(query)');
    }
}
