<?php

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\Catalogue\Shop\BreakShopPricesCache;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Catalogue\IrisProductTrendResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * Rule: trends.
 *
 * Most popular products of a shop. The catalogue reach can be narrowed to a single department, sub
 * department, family, brand or collection so the same recommender serves the homepage (whole shop),
 * a product listing (this category/brand only) and the product detail page (e.g. popular products of
 * the same collection). Popularity is measured against the sales of the product's asset over a window
 * of its daily time series, so a shorter window surfaces what is trending right now while a wider one
 * surfaces evergreen best sellers.
 * Short windows are sparse, so the requested one only decides the primary ranking and every wider
 * window breaks its ties. The ranking attribute is configurable: instead of popularity the products can be
 * ordered by price or by their best discount, which is useful when the block is used to promote the
 * cheapest or the most heavily discounted popular products.
 */
class GetIrisProductTrends extends IrisAction
{
    public const int MAX_PRODUCTS = 25;

    public const string ORDER_POPULARITY = 'popularity';
    public const string ORDER_PRICE      = 'price';
    public const string ORDER_DISCOUNT   = 'discount';

    public const string DEFAULT_PERIOD = '3d';

    /**
     * Narrowest first: a period also acts as the entry point of the fallback chain below it.
     * The value is the postgres interval the window reaches back from today.
     *
     * ponytail: the widest window bounds the daily records the ranking subquery aggregates,
     * so an 'all' period would mean scanning every daily record of the shop. 1y already
     * surfaces evergreen best sellers; widen only if a shop really needs older sales.
     */
    private const array SALES_PERIOD_INTERVALS = [
        'tdy' => '0 days',
        'ld'  => '1 day',
        '3d'  => '3 days',
        '1w'  => '7 days',
        '1m'  => '1 month',
        '1q'  => '3 months',
        '1y'  => '1 year',
    ];

    private const string SALES_SUBQUERY_ALIAS = 'asset_sales';

    private const string DISCOUNT_EXPRESSION = "coalesce((products.offers_data -> 'best_percentage_off' ->> 'percentage_off')::float8, 0)";

    public function handle(Shop $shop, array $modelData = []): Collection
    {
        $orderBy      = $modelData['order_by'] ?? self::ORDER_POPULARITY;
        $direction    = ($modelData['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $salesColumns = $this->getSalesColumns($modelData['period'] ?? self::DEFAULT_PERIOD);
        $limit        = max(1, min((int) ($modelData['limit'] ?? self::MAX_PRODUCTS), self::MAX_PRODUCTS));

        $ranking = $this->getRanking($shop, $modelData, $orderBy, $direction, $salesColumns, $limit);

        return $this->getRankedProducts($ranking, $shop);
    }

    /**
     * The requested period followed by every wider one. Short periods are sparse: on a quiet day
     * hardly any product has sales_3d, and ordering by it alone collapses the ranking onto the
     * tiebreakers, which reads as a fixed list rather than as trending products. Products with no
     * sales in the requested period are ranked by the next wider period instead.
     *
     * @return array<int, string>
     */
    private function getSalesColumns(string $period): array
    {
        $offset = array_search($period, array_keys(self::SALES_PERIOD_INTERVALS), true);

        if ($offset === false) {
            $offset = array_search(self::DEFAULT_PERIOD, array_keys(self::SALES_PERIOD_INTERVALS), true);
        }

        return array_map(
            fn (string $salesPeriod) => 'sales_'.$salesPeriod,
            array_slice(array_keys(self::SALES_PERIOD_INTERVALS), $offset)
        );
    }

    /**
     * One row per asset of the shop holding the sales of every window, so the ranking below can
     * order and break ties on them. The sales of a window are the daily time series records
     * falling in it; the widest window bounds how many records are read.
     */
    private function getAssetSales(Shop $shop): QueryBuilder
    {
        $sales      = 'coalesce(records.sales_external, 0) + coalesce(records.sales_internal, 0)';
        $intervals  = self::SALES_PERIOD_INTERVALS;
        $widestFrom = end($intervals);

        $queryBuilder = DB::table('asset_time_series as series')
            ->join('asset_time_series_records as records', function ($join) {
                $join->on('records.asset_time_series_id', '=', 'series.id')
                    ->where('records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter());
            })
            ->where('series.shop_id', $shop->id)
            ->where('series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereRaw('records."from" >= current_date - interval \''.$widestFrom.'\'')
            ->groupBy('series.asset_id')
            ->select('series.asset_id');

        foreach (self::SALES_PERIOD_INTERVALS as $salesPeriod => $interval) {
            $queryBuilder->selectRaw(
                'sum('.$sales.') filter (where records."from" >= current_date - interval \''.$interval.'\') as sales_'.$salesPeriod
            );
        }

        return $queryBuilder;
    }

    /**
     * @param array<int, string> $salesColumns
     */
    private function applyOrder(Builder $queryBuilder, string $orderBy, string $direction, array $salesColumns): void
    {
        switch ($orderBy) {
            case self::ORDER_PRICE:
                $queryBuilder->orderBy('products.price', $direction);
                break;
            case self::ORDER_DISCOUNT:
                $queryBuilder->orderByRaw(self::DISCOUNT_EXPRESSION.' '.$direction);
                $this->applySalesOrder($queryBuilder, $salesColumns, 'desc');
                break;
            default:
                $this->applySalesOrder($queryBuilder, $salesColumns, $direction);
                $queryBuilder->orderByRaw('products.top_seller asc nulls last');
                break;
        }

        $queryBuilder->orderByDesc('products.available_quantity')
            ->orderBy('products.id');
    }

    /**
     * @param array<int, string> $salesColumns
     */
    private function applySalesOrder(Builder $queryBuilder, array $salesColumns, string $direction): void
    {
        foreach ($salesColumns as $salesColumn) {
            $queryBuilder->orderByRaw('coalesce('.self::SALES_SUBQUERY_ALIAS.'.'.$salesColumn.', 0) '.$direction);
        }
    }

    private function getScopedProducts(Shop $shop, array $modelData): Builder
    {
        $queryBuilder = $this->applySellableFilters(
            Product::query()
                ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
                ->leftJoinSub($this->getAssetSales($shop), self::SALES_SUBQUERY_ALIAS, self::SALES_SUBQUERY_ALIAS.'.asset_id', '=', 'products.asset_id')
                ->where('products.shop_id', $shop->id)
        );

        foreach (['department_id', 'sub_department_id', 'family_id'] as $categoryColumn) {
            if (!empty($modelData[$categoryColumn])) {
                $queryBuilder->where('products.'.$categoryColumn, $modelData[$categoryColumn]);
            }
        }

        if (!empty($modelData['brand_id'])) {
            $queryBuilder->whereExists(function ($query) use ($modelData) {
                $query->selectRaw('1')
                    ->from('model_has_brands')
                    ->whereColumn('model_has_brands.model_id', 'products.id')
                    ->where('model_has_brands.model_type', 'Product')
                    ->where('model_has_brands.brand_id', $modelData['brand_id']);
            });
        }

        if (!empty($modelData['collection_id'])) {
            $queryBuilder->whereExists(function ($query) use ($modelData) {
                $query->selectRaw('1')
                    ->from('collection_has_models')
                    ->whereColumn('collection_has_models.model_id', 'products.id')
                    ->where('collection_has_models.model_type', 'Product')
                    ->where('collection_has_models.collection_id', $modelData['collection_id']);
            });
        }

        return $queryBuilder;
    }

    private function applySellableFilters(Builder $queryBuilder): Builder
    {
        return $queryBuilder
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->where('products.available_quantity', '>', 0)
            ->whereNotNull('products.price')
            ->where(function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('products.is_minion_variant', false)
                        ->where('products.is_for_sale', true);
                })->orWhere('products.is_variant_leader', true);
            });
    }

    /**
     * The ranking is the expensive half: it aggregates the daily time series of every asset of the
     * shop. It only moves when a day of sales lands or a price changes, so it is cached for a day
     * and stranded early by the price generation. Hydrating the ranked ids afterwards is 25 primary
     * key lookups, cheap enough to run on every request and keep stock and prices live.
     *
     * @return array<int, float> trend sales keyed by product id, best first
     */
    private function getRanking(Shop $shop, array $modelData, string $orderBy, string $direction, array $salesColumns, int $limit): array
    {
        $queryBuilder = $this->getScopedProducts($shop, $modelData)
            ->select('products.id')
            ->selectRaw('coalesce('.self::SALES_SUBQUERY_ALIAS.'.'.$salesColumns[0].', 0) as trend_sales');

        $this->applyOrder($queryBuilder, $orderBy, $direction, $salesColumns);

        $compute = fn () => $queryBuilder->limit($limit)->pluck('trend_sales', 'products.id')
            ->map(fn ($trendSales) => (float) $trendSales)
            ->all();

        $cacheKey = 'irisData:shop:'.$shop->id.':productTrends:'
            .BreakShopPricesCache::make()->getGeneration($shop->id).':'
            .md5(json_encode([$modelData, $orderBy, $direction, $salesColumns[0], $limit]));

        try {
            return Cache::remember($cacheKey, config('iris.cache.product_trends.ttl'), $compute);
        } catch (Throwable) {
            return $compute();
        }
    }

    /**
     * ponytail: the sellable filters run again here, so a product that sold out or was pulled since
     * the ranking was cached drops out and the list comes back shorter than the limit rather than
     * stale. Rank more than the limit if a short list ever becomes a problem.
     */
    private function getRankedProducts(array $ranking, Shop $shop): Collection
    {
        if (!$ranking) {
            return collect();
        }

        $products = $this->applySellableFilters(
            Product::query()
                ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
                ->where('products.shop_id', $shop->id)
                ->whereIn('products.id', array_keys($ranking))
        )->select([
            'products.id',
            'products.code',
            'products.name',
            'products.available_quantity',
            'products.price',
            'products.rrp',
            'products.web_images',
            'products.unit',
            'products.units',
            'products.offers_data',
            'products.url',
            'products.webpage_id',
            'products.family_id',
            'products.top_seller',
            'webpages.canonical_url',
            'products.offers_data as product_offers_data',
        ])->get();

        return $products
            ->each(fn (Product $product) => $product->trend_sales = $ranking[$product->id] ?? 0)
            ->sortBy(fn (Product $product) => array_search($product->id, array_keys($ranking), true))
            ->values();
    }

    public function rules(): array
    {
        return [
            'department_id'      => ['sometimes', 'nullable', 'integer'],
            'sub_department_id'  => ['sometimes', 'nullable', 'integer'],
            'family_id'          => ['sometimes', 'nullable', 'integer'],
            'brand_id'           => ['sometimes', 'nullable', 'integer'],
            'collection_id'      => ['sometimes', 'nullable', 'integer'],
            'order_by'           => ['sometimes', 'nullable', 'string', 'in:'.self::ORDER_POPULARITY.','.self::ORDER_PRICE.','.self::ORDER_DISCOUNT],
            'direction'          => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'period'             => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(self::SALES_PERIOD_INTERVALS))],
            'limit'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PRODUCTS],
        ];
    }

    public function asController(ActionRequest $request): Collection
    {
        $this->initialisation($request);

        return $this->handle($this->shop, $this->validatedData);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductTrendResource::collection($products);
    }
}
