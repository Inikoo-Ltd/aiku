<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSearchDemandOpportunities
{
    use AsObject;
    use WithRawSearchResults;

    /**
     * What customers searched for on the websites and nobody in the group sells.
     * Buying signal for procurement and supply chain, so the catalogue check spans every
     * shop in scope rather than the single website the search happened on.
     *
     * @return array{days: int, opportunities: array<int, array{query: string, searches: int, customers: int, websites: int, last_searched_at: string|null}>}
     */
    public function handle(Group $group, ?Organisation $organisation = null, int $days = 30, int $limit = 15): array
    {
        $cacheKey = 'search_demand_opportunities:'.$group->id.':'.($organisation?->id ?? 'all').':'.$days.':'.$limit;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($group, $organisation, $days, $limit) {
            return [
                'days'          => $days,
                'opportunities' => $this->compute($group, $organisation, $days, $limit),
            ];
        });
    }

    protected function compute(Group $group, ?Organisation $organisation, int $days, int $limit): array
    {
        $shopIds = Shop::where('group_id', $group->id)
            ->when($organisation, fn ($query) => $query->where('organisation_id', $organisation->id))
            ->pluck('id')
            ->all();

        if (empty($shopIds)) {
            return [];
        }

        // Demand repeats; single searches are typos, so only terms wanted more than once
        $terms = WebsiteSearchLog::where('group_id', $group->id)
            ->when($organisation, fn ($query) => $query->where('organisation_id', $organisation->id))
            ->where('created_at', '>=', now()->subDays($days))
            ->where('keyword_results_count', 0)
            ->whereRaw('char_length(query) between 3 and 40')
            ->selectRaw('lower(query) as query, count(*) as searches, count(distinct customer_id) as customers, count(distinct website_id) as websites, max(created_at) as last_searched_at')
            ->groupByRaw('lower(query)')
            ->havingRaw('count(*) > 1 or count(distinct customer_id) > 1')
            ->orderByDesc('customers')
            ->orderByDesc('searches')
            ->limit($limit * 3)
            ->get();

        $opportunities = [];
        foreach ($terms as $term) {
            if (count($opportunities) >= $limit) {
                break;
            }

            if ($this->soldByAnyShop($term->query, $shopIds)) {
                continue;
            }

            $opportunities[] = [
                'query'            => $term->query,
                'searches'         => (int)$term->searches,
                'customers'        => (int)$term->customers,
                'websites'         => (int)$term->websites,
                'last_searched_at' => $term->last_searched_at,
            ];
        }

        return $opportunities;
    }

    protected function soldByAnyShop(string $query, array $shopIds): bool
    {
        $searchQuery = Product::search($query)->whereIn('shop_id', $shopIds)->take(1);

        return count($this->rawDocuments($searchQuery)) > 0;
    }
}
