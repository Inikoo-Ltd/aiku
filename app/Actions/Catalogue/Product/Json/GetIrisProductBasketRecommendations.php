<?php

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\IrisProductBasketRecommendationResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
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
 */
class GetIrisProductBasketRecommendations extends IrisAction
{
    public const int MAX_PRODUCTS = 12;
    public const int MIN_PRODUCTS = 6;

    public const int MAX_BASKET_PRODUCTS = 50;

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

        $recommendations = $this->getDiversifiedRecommendations(
            $shop,
            $basketProductIds,
            $referencePrice,
            $preferCheaper,
            self::MAX_PER_FAMILY
        );

        if ($recommendations->count() >= self::MIN_PRODUCTS) {
            return $recommendations;
        }

        $widenedRecommendations = $this->getDiversifiedRecommendations(
            $shop,
            $basketProductIds,
            $referencePrice,
            $preferCheaper,
            self::MAX_PER_FAMILY_WIDENED,
            $recommendations->pluck('id')->all()
        );

        return $recommendations->concat($widenedRecommendations)->take(self::MAX_PRODUCTS);
    }

    private function getBasketReferencePrice(Shop $shop, array $basketProductIds): float
    {
        return (float) Product::query()
            ->where('shop_id', $shop->id)
            ->whereIn('id', $basketProductIds)
            ->max('price');
    }

    private function getDiversifiedRecommendations(
        Shop $shop,
        array $basketProductIds,
        float $referencePrice,
        bool $preferCheaper,
        int $maxPerFamily,
        array $excludedProductIds = []
    ): Collection {
        $candidates = $this->getScoredCandidates($shop, $basketProductIds, $referencePrice, $preferCheaper, $excludedProductIds);

        $connection = $shop->getConnection();

        $rankedCandidates = $connection->query()
            ->fromSub($candidates, 'candidates')
            ->select('candidates.*')
            ->selectRaw(
                'row_number() over ('
                .'partition by candidates.family_id'
                .' order by candidates.basket_score desc, candidates.co_orders desc, candidates.price asc, candidates.available_quantity desc, candidates.id'
                .') as family_rank'
            );

        $recommendations = $connection->query()
            ->fromSub($rankedCandidates, 'recommendations')
            ->where('family_rank', '<=', $maxPerFamily)
            ->orderByDesc('basket_score')
            ->orderByDesc('co_orders')
            ->orderBy('price')
            ->orderByDesc('available_quantity')
            ->orderBy('id')
            ->limit(self::MAX_PRODUCTS)
            ->get();

        return Product::hydrate($recommendations->map(fn ($recommendation) => (array) $recommendation)->all());
    }

    private function getScoredCandidates(
        Shop $shop,
        array $basketProductIds,
        float $referencePrice,
        bool $preferCheaper,
        array $excludedProductIds
    ): Builder {
        $coPurchase = $this->getCoPurchaseCounts($shop, $basketProductIds);

        $scoreExpression = '(?::float8 * ln(1 + co_purchase.co_orders))';
        $scoreBindings   = [self::WEIGHT_SUPPORT];

        if ($preferCheaper && $referencePrice > 0) {
            $scoreExpression .= ' + (?::float8 * greatest(0, least(1, (?::float8 - products.price) / ?::float8)))';
            $scoreBindings = array_merge($scoreBindings, [self::WEIGHT_CHEAPER, $referencePrice, $referencePrice]);
        }

        $excludedIds = array_values(array_unique(array_merge($basketProductIds, $excludedProductIds)));

        return Product::query()
            ->joinSub($coPurchase, 'co_purchase', 'co_purchase.product_id', '=', 'products.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->where('products.shop_id', $shop->id)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->where('products.available_quantity', '>', 0)
            ->whereNotNull('products.price')
            ->whereNotIn('products.id', $excludedIds)
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
    }

    private function getCoPurchaseCounts(Shop $shop, array $basketProductIds): QueryBuilder
    {
        $connection = $shop->getConnection();

        return $connection->table('transactions as co')
            ->join('transactions as seed', function ($join) use ($basketProductIds) {
                $join->on('seed.order_id', '=', 'co.order_id')
                    ->where('seed.model_type', '=', 'Product')
                    ->whereIn('seed.model_id', $basketProductIds)
                    ->whereNull('seed.deleted_at');
            })
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

        $basket = $request->user()?->customer?->orderInBasket;

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
