<?php

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\IrisProductBasketRecommendationResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Rule: basket.
 *
 * Cross-sell recommender for the whole basket. Every product already in the basket is used as a seed:
 * the historical orders that contain any of them are collected and the other products of those orders
 * become the candidates, so a candidate that keeps company with the basket in many orders (bought
 * together) scores higher. Support is log-damped so a handful of extremely popular staples cannot
 * drown out the genuinely associated products. By default the recommender prefers complements, adding
 * a bonus to candidates priced below the basket's most expensive item; the more a candidate undercuts
 * that reference the larger the bonus, so cheap add-ons surface ahead of equally associated but pricey
 * products. The candidates are then diversified by family: only the best few of each family survive so
 * the block offers variety instead of a wall of near-identical products. When strict diversity leaves
 * too few products the cap per family is relaxed until the minimum is met.
 *
 * The co-purchase counts are the expensive part, so they are computed a single time: a pool of the
 * top scored candidates is fetched in one query and the family diversification (including the widening
 * fallback) runs in memory instead of re-scanning the transactions table for a second pass.
 */
class GetRetinaProductBasketRecommendations extends RetinaAction
{
    public const int MAX_PRODUCTS = 12;
    public const int MIN_PRODUCTS = 6;

    public const int MAX_BASKET_PRODUCTS = 50;
    public const int CANDIDATE_POOL      = 60;

    public const int MAX_PER_FAMILY         = 1;
    public const int MAX_PER_FAMILY_WIDENED = 3;

    public const float WEIGHT_SUPPORT = 2.0;
    public const float WEIGHT_CHEAPER = 1.0;

    public function handle(Shop $shop, array $basketProductIds, array $modelData = []): Collection
    {
        $basketProductIds = array_values(array_unique(array_filter(array_map('intval', $basketProductIds))));
        $basketProductIds = array_slice($basketProductIds, 0, self::MAX_BASKET_PRODUCTS);

        if (!$basketProductIds) {
            return collect();
        }

        $preferCheaper  = ($modelData['prefer_cheaper'] ?? true) !== false;
        $referencePrice = $preferCheaper ? $this->getBasketReferencePrice($shop, $basketProductIds) : 0.0;

        $candidates = $this->getScoredCandidates($shop, $basketProductIds, $referencePrice, $preferCheaper);

        return $this->diversifyByFamily($candidates);
    }

    private function getBasketReferencePrice(Shop $shop, array $basketProductIds): float
    {
        return (float) Product::query()
            ->where('shop_id', $shop->id)
            ->whereIn('id', $basketProductIds)
            ->max('price');
    }

    /**
     * The pool already carries a per-family rank from the database. Strict diversity keeps the best of
     * each family; if that leaves fewer than the minimum the runners-up (rank 2..N) top the list up.
     * Everything happens over the in-memory pool, so there is no second database round-trip.
     */
    private function diversifyByFamily(Collection $candidates): Collection
    {
        $recommendations = $candidates
            ->where('family_rank', '<=', self::MAX_PER_FAMILY)
            ->take(self::MAX_PRODUCTS)
            ->values();

        if ($recommendations->count() >= self::MIN_PRODUCTS) {
            return $recommendations;
        }

        $widened = $candidates
            ->whereNotIn('id', $recommendations->pluck('id')->all())
            ->take(self::MAX_PRODUCTS - $recommendations->count());

        return $recommendations->concat($widened)->take(self::MAX_PRODUCTS)->values();
    }

    private function getScoredCandidates(
        Shop $shop,
        array $basketProductIds,
        float $referencePrice,
        bool $preferCheaper
    ): Collection {
        $coPurchase = $this->getCoPurchaseCounts($shop, $basketProductIds);

        $scoreExpression = '(?::float8 * ln(1 + co_purchase.co_orders))';
        $scoreBindings   = [self::WEIGHT_SUPPORT];

        if ($preferCheaper && $referencePrice > 0) {
            $scoreExpression .= ' + (?::float8 * greatest(0, least(1, (?::float8 - products.price) / ?::float8)))';
            $scoreBindings = array_merge($scoreBindings, [self::WEIGHT_CHEAPER, $referencePrice, $referencePrice]);
        }

        $scoredCandidates = Product::query()
            ->joinSub($coPurchase, 'co_purchase', 'co_purchase.product_id', '=', 'products.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->where('products.shop_id', $shop->id)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->where('products.available_quantity', '>', 0)
            ->whereNotNull('products.price')
            ->whereNotIn('products.id', $basketProductIds)
            ->where(function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('products.is_minion_variant', false)
                        ->where('products.is_for_sale', true);
                })->orWhere('products.is_variant_leader', true);
            })
            ->select([
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
                'webpages.canonical_url',
                'products.offers_data as product_offers_data',
                'co_purchase.co_orders',
            ])
            ->selectRaw("($scoreExpression) as basket_score", $scoreBindings);

        $connection = $shop->getConnection();

        $rankedCandidates = $connection->query()
            ->fromSub($scoredCandidates, 'candidates')
            ->select('candidates.*')
            ->selectRaw(
                'row_number() over ('
                .'partition by candidates.family_id'
                .' order by candidates.basket_score desc, candidates.co_orders desc, candidates.price asc, candidates.available_quantity desc, candidates.id'
                .') as family_rank'
            );

        $pool = $connection->query()
            ->fromSub($rankedCandidates, 'recommendations')
            ->where('family_rank', '<=', self::MAX_PER_FAMILY_WIDENED)
            ->orderByDesc('basket_score')
            ->orderByDesc('co_orders')
            ->orderBy('price')
            ->orderByDesc('available_quantity')
            ->orderBy('id')
            ->limit(self::CANDIDATE_POOL)
            ->get();

        return Product::hydrate($pool->map(fn ($candidate) => (array) $candidate)->all());
    }

    /**
     * The seed order ids are collapsed to a distinct set before the co-purchase join. Deduplicating
     * them lets the planner drive a single index scan per order instead of re-walking the transaction
     * rows once per matching basket item, which is where the previous self-join spent its time.
     */
    private function getCoPurchaseCounts(Shop $shop, array $basketProductIds): QueryBuilder
    {
        $connection = $shop->getConnection();

        $seedOrders = $connection->table('transactions')
            ->where('model_type', 'Product')
            ->whereIn('model_id', $basketProductIds)
            ->whereNull('deleted_at')
            ->select('order_id')
            ->distinct();

        return $connection->table('transactions as co')
            ->joinSub($seedOrders, 'seed_orders', 'seed_orders.order_id', '=', 'co.order_id')
            ->where('co.model_type', 'Product')
            ->where('co.shop_id', $shop->id)
            ->whereNotIn('co.model_id', $basketProductIds)
            ->whereNull('co.deleted_at')
            ->groupBy('co.model_id')
            ->select('co.model_id as product_id')
            ->selectRaw('count(distinct co.order_id) as co_orders');
    }

    public function rules(): array
    {
        return [
            'product_ids'    => ['sometimes', 'nullable'],
            'prefer_cheaper' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): Collection
    {
        $this->initialisation($request);

        return $this->handle($this->shop, $this->getBasketProductIds($request), $this->validatedData);
    }

    /**
     * @return array<int, int>
     */
    private function getBasketProductIds(ActionRequest $request): array
    {
        $productIds = $request->input('product_ids');

        if (is_string($productIds)) {
            $productIds = explode(',', $productIds);
        }

        if (is_array($productIds) && $productIds) {
            return array_map('intval', $productIds);
        }

        $basket = $this->customer?->orderInBasket;

        if (!$basket) {
            return [];
        }

        return $basket->transactions()
            ->where('model_type', 'Product')
            ->pluck('model_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductBasketRecommendationResource::collection($products);
    }
}
