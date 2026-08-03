<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Catalogue\Product;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteZeroResultOpportunities
{
    use AsObject;
    use WithRawSearchResults;

    public const UNPUBLISHED = 'unpublished';
    public const NOT_STOCKED = 'not_stocked';

    /**
     * Why did a search return nothing? Re-running it without the is_in_website filter
     * separates the two very different answers:
     *  - unpublished: the catalogue has it, it just is not live on the website (publish it)
     *  - not_stocked: nothing in the catalogue matches at all (demand we do not sell yet)
     *
     * @return array<int, array{query: string, searches: int, customers: int, status: string, catalogue_matches: int, samples: array<int, string>, last_searched_at: string|null}>
     */
    public function handle(Website $website, int $days = 30, int $limit = 25): array
    {
        return Cache::remember(
            'website_search_opportunities:'.$website->id.':'.$days.':'.$limit,
            now()->addMinutes(10),
            fn () => $this->compute($website, $days, $limit)
        );
    }

    protected function compute(Website $website, int $days, int $limit): array
    {
        $terms = WebsiteSearchLog::where('website_id', $website->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('results_count', 0)
            ->whereRaw('char_length(query) between 3 and 40')
            ->selectRaw('lower(query) as query, count(*) as searches, count(distinct customer_id) as customers, max(created_at) as last_searched_at')
            ->groupByRaw('lower(query)')
            ->orderByDesc('searches')
            ->orderByDesc('customers')
            ->limit($limit)
            ->get();

        $opportunities = [];
        foreach ($terms as $term) {
            $matches = $this->catalogueMatches($website, $term->query);

            $opportunities[] = [
                'query'            => $term->query,
                'searches'         => (int)$term->searches,
                'customers'        => (int)$term->customers,
                'status'           => $matches ? self::UNPUBLISHED : self::NOT_STOCKED,
                'catalogue_matches' => count($matches),
                'samples'          => array_slice($matches, 0, 3),
                'last_searched_at' => $term->last_searched_at,
            ];
        }

        return $opportunities;
    }

    /**
     * The same search the storefront ran, minus the is_in_website filter.
     *
     * @return array<int, string>
     */
    protected function catalogueMatches(Website $website, string $query): array
    {
        $searchQuery = Product::search($query)->where('shop_id', $website->shop_id)->take(5);

        return array_values(array_filter(array_map(
            fn (array $document) => $document['name'] ?? null,
            $this->rawDocuments($searchQuery)
        )));
    }
}
