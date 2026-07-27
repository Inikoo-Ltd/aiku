<?php

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\IrisProductTrendResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Rule: trends.
 *
 * Most popular products of a shop. The catalogue reach can be narrowed to a single department, sub
 * department, family, brand or collection so the same recommender serves the homepage (whole shop),
 * a product listing (this category/brand only) and the product detail page (e.g. popular products of
 * the same collection). Popularity is measured against the product's sales interval, so a shorter
 * window surfaces what is trending right now while a wider one surfaces evergreen best sellers.
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

    public const string DEFAULT_PERIOD = '1d';

    /**
     * Narrowest first: a period also acts as the entry point of the fallback chain below it.
     */
    private const array SALES_PERIOD_COLUMNS = [
        '1d'  => 'sales_1d',
        '1w'  => 'sales_1w',
        '1m'  => 'sales_1m',
        '1q'  => 'sales_1q',
        '1y'  => 'sales_1y',
        'all' => 'sales_all',
    ];

    private const string DISCOUNT_EXPRESSION = "coalesce((products.offers_data -> 'best_percentage_off' ->> 'percentage_off')::float8, 0)";

    public function handle(Shop $shop, array $modelData = []): Collection
    {
        $orderBy      = $modelData['order_by'] ?? self::ORDER_POPULARITY;
        $direction    = ($modelData['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $salesColumns = $this->getSalesColumns($modelData['period'] ?? self::DEFAULT_PERIOD);
        $limit        = max(1, min((int) ($modelData['limit'] ?? self::MAX_PRODUCTS), self::MAX_PRODUCTS));

        $queryBuilder = $this->getScopedProducts($shop, $modelData, $salesColumns[0]);

        $this->applyOrder($queryBuilder, $orderBy, $direction, $salesColumns);

        return $queryBuilder->limit($limit)->get();
    }

    /**
     * The requested period followed by every wider one. Short periods are sparse: on a quiet day
     * hardly any product has sales_1d, and ordering by it alone collapses the ranking onto the
     * tiebreakers, which reads as a fixed list rather than as trending products. Products with no
     * sales in the requested period are ranked by the next wider period instead.
     *
     * @return array<int, string>
     */
    private function getSalesColumns(string $period): array
    {
        $offset = array_search($period, array_keys(self::SALES_PERIOD_COLUMNS), true);

        if ($offset === false) {
            $offset = array_search(self::DEFAULT_PERIOD, array_keys(self::SALES_PERIOD_COLUMNS), true);
        }

        return array_values(array_slice(self::SALES_PERIOD_COLUMNS, $offset));
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
            $queryBuilder->orderByRaw('coalesce(asset_sales_intervals.'.$salesColumn.', 0) '.$direction);
        }
    }

    private function getScopedProducts(Shop $shop, array $modelData, string $salesColumn): Builder
    {
        $queryBuilder = Product::query()
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->leftJoin('asset_sales_intervals', 'asset_sales_intervals.asset_id', '=', 'products.asset_id')
            ->where('products.shop_id', $shop->id)
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

        if (!empty($modelData['exclude_product_id'])) {
            $queryBuilder->where('products.id', '!=', $modelData['exclude_product_id']);
        }

        return $queryBuilder->select([
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
        ])->selectRaw('coalesce(asset_sales_intervals.'.$salesColumn.', 0) as trend_sales');
    }

    public function rules(): array
    {
        return [
            'department_id'      => ['sometimes', 'nullable', 'integer'],
            'sub_department_id'  => ['sometimes', 'nullable', 'integer'],
            'family_id'          => ['sometimes', 'nullable', 'integer'],
            'brand_id'           => ['sometimes', 'nullable', 'integer'],
            'collection_id'      => ['sometimes', 'nullable', 'integer'],
            'exclude_product_id' => ['sometimes', 'nullable', 'integer'],
            'order_by'           => ['sometimes', 'nullable', 'string', 'in:'.self::ORDER_POPULARITY.','.self::ORDER_PRICE.','.self::ORDER_DISCOUNT],
            'direction'          => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'period'             => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(self::SALES_PERIOD_COLUMNS))],
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
