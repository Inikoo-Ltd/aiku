<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Actions\CRM\RangeAdvisor\GetCustomerRangeGaps;
use App\Actions\CRM\RangeAdvisor\GetSegmentBestSellers;
use App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales;
use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Keep exploring row under the storefront search results: related or popular searches, a
 * did you mean, families that sell well with the families found, and best sellers, new arrivals
 * and the customer's forgotten products. The searches come from what this website's customers
 * searched and are cached per website; the products depend on who is looking and are not cached.
 * The entry of one query lives an hour only, as anyone can make up queries and each one is a key.
 */
class GetIrisSearchKeepExploring extends IrisAction
{
    use WithIrisProductsInWebpage;
    use WithRecommendableProducts;

    private const int POPULAR_CACHE_SECONDS = 86400;

    private const int QUERY_CACHE_SECONDS = 3600;

    private const int MAX_SEARCHES = 6;

    private const int MAX_ITEMS = 8;

    private const array SIMILAR_SHOP_LEVELS = [
        RebuildRangeAdvisorSales::LEVEL_TYPE_AREA,
        RebuildRangeAdvisorSales::LEVEL_TYPE_COUNTRY,
        RebuildRangeAdvisorSales::LEVEL_TYPE_GROUP,
        RebuildRangeAdvisorSales::LEVEL_TYPE,
    ];

    /**
     * @param array{q: string, families?: array<int, int>} $modelData
     *
     * @return array{did_you_mean: ?string, searches: array{kind: string, items: array<int, string>}, families: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public function handle(array $modelData): array
    {
        $query      = mb_strtolower(trim($modelData['q']));
        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        $searches   = Cache::remember(
            'iris-search-keep-exploring:'.$this->website->id.':'.md5($query),
            self::QUERY_CACHE_SECONDS,
            fn () => $this->searches($query)
        );

        return [
            'did_you_mean' => $searches['did_you_mean'],
            'searches'     => Arr::only($searches, ['kind', 'items']),
            'families'     => $this->families(array_map('intval', Arr::get($modelData, 'families', [])), $customer),
            'products'     => $this->products($customer),
        ];
    }

    /**
     * @return array{did_you_mean: ?string, kind: string, items: array<int, string>}
     */
    private function searches(string $query): array
    {
        $popular = $this->popularSearches();
        $related = $this->relatedSearches($query, $popular);

        return [
            'did_you_mean' => $this->didYouMean($query, $popular),
            'kind'         => $related ? 'related' : 'popular',
            'items'        => $related ?: array_slice(array_values(array_filter(array_keys($popular), fn ($popularQuery) => $popularQuery !== $query)), 0, self::MAX_SEARCHES),
        ];
    }

    /**
     * Submitted searches with results that at least 5 different customers made in the last 180 days,
     * most searched first. Only signed-in customers are counted: anyone can script searches
     * without an account, one new session per request, and get any phrase shown to everybody.
     *
     * @return array<string, int>
     */
    private function popularSearches(): array
    {
        return Cache::remember('iris-search-popular:'.$this->website->id, self::POPULAR_CACHE_SECONDS, function () {
            return DB::table('website_search_logs')
                ->where('website_id', $this->website->id)
                ->where('scope', 'catalogue_page')
                ->where('results_count', '>', 0)
                ->where('created_at', '>=', now()->subDays(180))
                ->whereRaw("query !~ '[0-9@]'")
                ->groupByRaw('lower(trim(query))')
                ->havingRaw('count(distinct customer_id) >= 5')
                ->orderByRaw('people desc, search_query')
                ->limit(3000)
                ->selectRaw('lower(trim(query)) as search_query, count(distinct customer_id) as people')
                ->pluck('people', 'search_query')
                ->map(fn ($people) => (int) $people)
                ->all();
        });
    }

    /**
     * Popular searches made in the same sessions as this query by at least 3 different customers,
     * scored by how often they go together against how popular they are on their own. The
     * sessions may be a visitor's, but only rows of signed-in customers are counted, as anyone
     * can script searches without an account.
     *
     * @param array<string, int> $popular
     *
     * @return array<int, string>
     */
    private function relatedSearches(string $query, array $popular): array
    {
        $since = now()->subDays(180);

        $seedSessions = DB::table('website_search_logs')
            ->where('website_id', $this->website->id)
            ->where('created_at', '>=', $since)
            ->whereNotNull('session_id')
            ->whereRaw('lower(trim(query)) = ?', [$query])
            ->select('session_id');

        return DB::table('website_search_logs')
            ->where('website_id', $this->website->id)
            ->where('scope', 'catalogue_page')
            ->where('results_count', '>', 0)
            ->where('created_at', '>=', $since)
            ->whereIn('session_id', $seedSessions)
            ->whereRaw('lower(trim(query)) <> ?', [$query])
            ->groupByRaw('lower(trim(query))')
            ->havingRaw('count(distinct customer_id) >= 3')
            ->selectRaw('lower(trim(query)) as search_query, count(distinct customer_id) as people')
            ->get()
            ->filter(fn ($row) => isset($popular[$row->search_query]))
            ->sortByDesc(fn ($row) => $row->people / sqrt($popular[$row->search_query]))
            ->take(self::MAX_SEARCHES)
            ->pluck('search_query')
            ->values()
            ->all();
    }

    /**
     * ponytail: edit distance against past successful searches, a loop over at most 3000 popular
     * queries; upgrade path a trigram index or the search engine's own suggestions.
     *
     * @param array<string, int> $popular
     */
    private function didYouMean(string $query, array $popular): ?string
    {
        if (isset($popular[$query])) {
            return null;
        }

        $key = $this->comparisonKey($query);
        if (strlen($key) < 5) {
            return null;
        }

        $maxDistance = strlen($key) < 8 ? 1 : 2;
        $best        = null;
        $bestRank    = null;

        foreach ($popular as $popularQuery => $people) {
            $distance = levenshtein($key, $this->comparisonKey((string) $popularQuery));
            if ($distance > $maxDistance) {
                continue;
            }

            $rank = [$distance, -$people];
            if ($bestRank === null || $rank < $bestRank) {
                $best     = (string) $popularQuery;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    private function comparisonKey(string $query): string
    {
        return str_replace([' ', '-'], '', Str::ascii(mb_strtolower($query)));
    }

    /**
     * Families related to the given ones, of this shop, showable on the storefront and with at
     * least one product the customer can buy now, in the order they were related.
     *
     * @param array<int, int> $familyIds
     *
     * @return array<int, array{id: int, name: string, url: ?string, image: mixed}>
     */
    private function families(array $familyIds, ?Customer $customer): array
    {
        if (!$familyIds) {
            return [];
        }

        $relatedIds = DB::table('product_category_has_related_product_categories')
            ->join('product_categories', 'product_categories.id', '=', 'product_category_has_related_product_categories.product_category_id')
            ->where('product_categories.shop_id', $this->shop->id)
            ->whereIn('product_category_has_related_product_categories.product_category_id', $familyIds)
            ->whereNotIn('product_category_has_related_product_categories.related_product_category_id', $familyIds)
            ->orderBy('product_category_has_related_product_categories.position')
            ->orderBy('product_category_has_related_product_categories.id')
            ->pluck('product_category_has_related_product_categories.related_product_category_id')
            ->unique()
            ->values()
            ->all();

        if (!$relatedIds) {
            return [];
        }

        $categories = ProductCategory::query()
            ->whereIn('product_categories.id', $relatedIds)
            ->where('product_categories.shop_id', $this->shop->id)
            ->where('product_categories.show_in_website', true)
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->whereHas('webpage', fn ($webpageQuery) => $webpageQuery
                ->where('website_id', $this->website->id)
                ->where('layout_style', 'main_page')
                ->where('state', WebpageStateEnum::LIVE->value))
            ->whereIn('product_categories.id', $this->recommendableProducts($this->shop, $customer)->select('products.family_id'))
            ->with(['image', 'webpage' => fn ($webpageQuery) => $webpageQuery->where('website_id', $this->website->id)])
            ->get()
            ->keyBy('id');

        return collect($relatedIds)
            ->map(fn (int $categoryId) => $categories->get($categoryId))
            ->filter()
            ->take(self::MAX_ITEMS)
            ->map(fn (ProductCategory $category) => [
                'id'    => $category->id,
                'name'  => $category->name,
                'url'   => $this->canonicalUrl($category->webpage) ?: null,
                'image' => $category->imageSources(200, 200) ?: Arr::get($category->web_images ?? [], 'main.thumbnail'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(?Customer $customer): array
    {
        $bestSellers = GetSegmentBestSellers::run($this->shop, $customer?->shop_type?->value, data_get($customer?->location, 0), [
            'customer' => $customer,
            'area'     => $customer ? RebuildRangeAdvisorSales::areaOf($customer) : null,
            'limit'    => self::MAX_ITEMS,
        ])['items'];

        $groups = [
            'best_sellers' => $bestSellers->pluck('id')->all(),
            'new_arrivals' => $this->recommendableProducts($this->shop, $customer)
                ->where('products.first_available_at', '>', ProductBadgeEnum::newInSince())
                ->orderByDesc('products.first_available_at')
                ->orderByDesc('products.id')
                ->limit(self::MAX_ITEMS)
                ->pluck('products.id')
                ->all(),
            'forgotten'    => $customer ? GetCustomerRangeGaps::run($customer, self::MAX_ITEMS)['forgotten']->pluck('id')->all() : [],
        ];

        $productIds = array_values(array_unique(array_merge(...array_values($groups))));
        if (!$productIds) {
            return [];
        }

        $cards = collect(IrisAuthenticatedProductsInWebpageResource::collection(
            $this->getBaseQuery('in_stock')
                ->whereIn('products.id', $productIds)
                ->select($this->getSelect())
                ->get()
        )->resolve())->keyBy('id');

        $products = [];
        foreach ($groups as $key => $ids) {
            $items = collect($ids)->map(fn (int $id) => $cards->get($id))->filter()->take(self::MAX_ITEMS)->values()->all();
            if (!$items) {
                continue;
            }

            $group = ['key' => $key];
            if ($key === 'best_sellers') {
                $group['similar_shops'] = $bestSellers->every(fn ($product) => in_array($product->segment_level, self::SIMILAR_SHOP_LEVELS, true));
            }
            $group['items'] = $items;

            $products[] = $group;
        }

        return $products;
    }

    /**
     * Replaces the paginated product list response of WithIrisProductsInWebpage.
     *
     * @param array<string, mixed> $results
     *
     * @return array<string, mixed>
     */
    public function jsonResponse(array $results): array
    {
        return $results;
    }

    public function rules(): array
    {
        return [
            'q'          => ['required', 'string', 'max:100'],
            'families'   => ['sometimes', 'array', 'max:5'],
            'families.*' => ['integer'],
            'search_log' => ['sometimes', 'nullable', 'string', 'size:26'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        abort_unless(Arr::get($request->input('website')?->settings, 'search_keep_exploring'), 404);

        $this->initialisation($request);

        $result = $this->handle($this->validatedData);

        if ($searchLogUlid = Arr::get($this->validatedData, 'search_log')) {
            $websiteId   = $this->website->id;
            $suggestions = $this->suggestionIds($result);
            logAfterResponse(fn () => DB::table('website_search_logs')
                ->where('ulid', $searchLogUlid)
                ->where('website_id', $websiteId)
                ->whereNull('suggestions')
                ->update(['suggestions' => json_encode($suggestions)]));
        }

        return $result;
    }

    /**
     * What was offered, as ids, for the search log row: stored after the response on the row of the
     * same website that has none yet. The row itself is written after the response of the search
     * request; when it is not there yet the update finds nothing and the suggestions are not logged.
     *
     * @param array<string, mixed> $result
     *
     * @return array{did_you_mean: ?string, searches: array{kind: string, items: array<int, string>}, families: array<int, int>, products: array{best_sellers: array<int, int>, new_arrivals: array<int, int>, forgotten: array<int, int>}}
     */
    private function suggestionIds(array $result): array
    {
        $products = collect($result['products'])->keyBy('key');

        return [
            'did_you_mean' => $result['did_you_mean'],
            'searches'     => $result['searches'],
            'families'     => array_column($result['families'], 'id'),
            'products'     => collect(['best_sellers', 'new_arrivals', 'forgotten'])
                ->mapWithKeys(fn (string $key) => [$key => array_column($products->get($key)['items'] ?? [], 'id')])
                ->all(),
        ];
    }
}
