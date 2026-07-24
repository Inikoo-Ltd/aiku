<?php

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\IrisProductAlternativeResource;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Rule: item_detail_alternatives.
 *
 * Content-based k-nearest-neighbours recommender for the product detail page. Each candidate
 * product is scored against the input product with a weighted similarity kernel over its
 * metadata (family, sub department, department, unit) plus a price-affinity term. The price
 * term is a Gaussian kernel centred on an upsell target above the input price, so equally
 * priced or cheaper products score lower than ones that are slightly more expensive. The top
 * MAX_PRODUCTS by score are the nearest neighbours.
 */
class GetIrisProductAlternatives extends IrisAction
{
    public const int MAX_PRODUCTS = 12;

    public const float UPSELL_TARGET_RATIO = 0.15;
    public const float PRICE_SIGMA_RATIO   = 0.5;
    public const float MIN_PRICE_RATIO     = 0.7;
    public const float MAX_PRICE_RATIO     = 2.0;

    public const float WEIGHT_FAMILY         = 5.0;
    public const float WEIGHT_SUB_DEPARTMENT = 3.0;
    public const float WEIGHT_DEPARTMENT     = 2.0;
    public const float WEIGHT_UNIT           = 1.0;
    public const float WEIGHT_PRICE          = 4.0;

    private Product $product;

    public function handle(Product $product): Collection
    {
        $price       = (float) $product->price;
        $hasPrice    = $price > 0;
        $targetPrice = $hasPrice ? $price * (1 + self::UPSELL_TARGET_RATIO) : 0.0;
        $sigma       = $hasPrice ? max($price * self::PRICE_SIGMA_RATIO, 0.01) : 1.0;
        $priceWeight = $hasPrice ? self::WEIGHT_PRICE : 0.0;

        $scoreExpression = '(CASE WHEN products.family_id = ? THEN ? ELSE 0 END)'
            .' + (CASE WHEN products.sub_department_id = ? THEN ? ELSE 0 END)'
            .' + (CASE WHEN products.department_id = ? THEN ? ELSE 0 END)'
            .' + (CASE WHEN products.unit = ? THEN ? ELSE 0 END)'
            .' + (? * exp(-1 * power((products.price - ?) / ?, 2)))';

        $scoreBindings = [
            $product->family_id, self::WEIGHT_FAMILY,
            $product->sub_department_id, self::WEIGHT_SUB_DEPARTMENT,
            $product->department_id, self::WEIGHT_DEPARTMENT,
            $product->unit, self::WEIGHT_UNIT,
            $priceWeight, $targetPrice, $sigma,
        ];

        $queryBuilder = QueryBuilder::for(Product::class)
            ->where('products.shop_id', $product->shop_id)
            ->where('products.id', '!=', $product->id)
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

        if ($hasPrice) {
            $queryBuilder->where('products.price', '>=', $price * self::MIN_PRICE_RATIO)
                ->where('products.price', '<=', $price * self::MAX_PRICE_RATIO);
        }

        $queryBuilder->select([
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
            'products.offers_data as product_offers_data',
        ])->selectRaw("($scoreExpression) as alternative_score", $scoreBindings);

        return $queryBuilder
            ->orderByDesc('alternative_score')
            ->orderByDesc('products.available_quantity')
            ->orderBy('products.id')
            ->limit(self::MAX_PRODUCTS)
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->product->shop_id === $this->shop->id;
    }

    public function asController(Product $product, ActionRequest $request): Collection
    {
        $this->product = $product;
        $this->initialisation($request);

        return $this->handle($product);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductAlternativeResource::collection($products);
    }
}
