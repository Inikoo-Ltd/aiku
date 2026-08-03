<?php

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\IrisProductAlternativeResource;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Rule: item_detail_alternatives.
 *
 * Content-based k-nearest-neighbours recommender for the product detail page, restricted to the
 * department of the input product. Each candidate is scored against the input product with a
 * weighted similarity kernel over its metadata (sub department, family, department, unit), a
 * trigram similarity of the name, a best seller bonus, and a price-affinity term. The price term
 * is a Gaussian kernel centred on an upsell target above the input price, so equally priced or
 * cheaper products score lower than ones that are slightly more expensive.
 *
 * The nearest neighbours of a product are nearly always its own family, which makes a plain top
 * MAX_PRODUCTS by score read as a family listing instead of a set of alternatives. The ranking is
 * therefore diversified: only the MAX_PER_FAMILY best scoring products of each family survive, so
 * the remaining slots go to the next best families of the department. When that yields fewer than
 * MIN_PRODUCTS the search is widened, dropping the price band and allowing one more product per
 * family.
 */
class GetIrisProductAlternatives extends IrisAction
{
    public const int MAX_PRODUCTS = 12;
    public const int MIN_PRODUCTS = 6;

    public const int MAX_PER_FAMILY         = 2;
    public const int MAX_PER_FAMILY_WIDENED = 3;

    public const float UPSELL_TARGET_RATIO = 0.15;
    public const float PRICE_SIGMA_RATIO   = 0.5;
    public const float MIN_PRICE_RATIO     = 0.7;
    public const float MAX_PRICE_RATIO     = 2.0;

    public const float WEIGHT_PRICE          = 4.0;
    public const float WEIGHT_NAME           = 3.0;
    public const float WEIGHT_SUB_DEPARTMENT = 3.0;
    public const float WEIGHT_FAMILY         = 2.0;
    public const float WEIGHT_TOP_SELLER     = 1.5;
    public const float WEIGHT_DEPARTMENT     = 1.0;
    public const float WEIGHT_UNIT           = 1.0;

    private Product $product;

    public function handle(Product $product): Collection
    {
        $alternatives = $this->getDiversifiedAlternatives($product, self::MAX_PER_FAMILY, true);

        if ($alternatives->count() >= self::MIN_PRODUCTS) {
            return $alternatives;
        }

        $widenedAlternatives = $this->getDiversifiedAlternatives(
            $product,
            self::MAX_PER_FAMILY_WIDENED,
            false,
            $alternatives->pluck('id')->all()
        );

        return $alternatives->concat($widenedAlternatives)->take(self::MAX_PRODUCTS);
    }

    private function getDiversifiedAlternatives(
        Product $product,
        int $maxPerFamily,
        bool $applyPriceBand,
        array $excludedProductIds = []
    ): Collection {
        $candidates = $this->getScoredCandidates($product, $applyPriceBand, $excludedProductIds);

        if (!$candidates) {
            return collect();
        }

        $connection = $product->getConnection();

        $rankedCandidates = $connection->query()
            ->fromSub($candidates, 'candidates')
            ->select('candidates.*')
            ->selectRaw(
                'row_number() over ('
                .'partition by candidates.family_id'
                .' order by candidates.alternative_score desc, candidates.available_quantity desc, candidates.id'
                .') as family_rank'
            );

        $alternatives = $connection->query()
            ->fromSub($rankedCandidates, 'alternatives')
            ->where('family_rank', '<=', $maxPerFamily)
            ->orderByDesc('alternative_score')
            ->orderByDesc('available_quantity')
            ->orderBy('id')
            ->limit(self::MAX_PRODUCTS)
            ->get();

        return Product::hydrate($alternatives->map(fn ($alternative) => (array) $alternative)->all());
    }

    private function getScoredCandidates(Product $product, bool $applyPriceBand, array $excludedProductIds): ?Builder
    {
        $price       = (float) $product->price;
        $hasPrice    = $price > 0;
        $targetPrice = $hasPrice ? $price * (1 + self::UPSELL_TARGET_RATIO) : 0.0;
        $sigma       = $hasPrice ? max($price * self::PRICE_SIGMA_RATIO, 0.01) : 1.0;
        $priceWeight = $hasPrice ? self::WEIGHT_PRICE : 0.0;

        $scoreExpression = '(CASE WHEN products.sub_department_id = ? THEN ?::float8 ELSE 0 END)'
            .' + (CASE WHEN products.family_id = ? THEN ?::float8 ELSE 0 END)'
            .' + (CASE WHEN products.department_id = ? THEN ?::float8 ELSE 0 END)'
            .' + (CASE WHEN products.unit = ? THEN ?::float8 ELSE 0 END)'
            .' + (CASE WHEN products.top_seller BETWEEN 1 AND 3 THEN ?::float8 * (4 - products.top_seller) / 3 ELSE 0 END)'
            ." + (?::float8 * similarity(coalesce(products.name, ''), ?))"
            .' + (?::float8 * exp(greatest(-700, -1 * power((products.price::float8 - ?) / ?, 2))))';

        $scoreBindings = [
            $product->sub_department_id, self::WEIGHT_SUB_DEPARTMENT,
            $product->family_id, self::WEIGHT_FAMILY,
            $product->department_id, self::WEIGHT_DEPARTMENT,
            $product->unit, self::WEIGHT_UNIT,
            self::WEIGHT_TOP_SELLER,
            self::WEIGHT_NAME, (string) $product->name,
            $priceWeight, $targetPrice, $sigma,
        ];

        $queryBuilder = Product::query()
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
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

        if ($product->department_id) {
            $queryBuilder->where('products.department_id', $product->department_id);
        } elseif ($product->sub_department_id) {
            $queryBuilder->where('products.sub_department_id', $product->sub_department_id);
        } elseif ($product->family_id) {
            $queryBuilder->where('products.family_id', $product->family_id);
        } else {
            return null;
        }

        if ($product->variant_id) {
            $queryBuilder->where(function ($query) use ($product) {
                $query->whereNull('products.variant_id')
                    ->orWhere('products.variant_id', '!=', $product->variant_id);
            });
        }

        if ($excludedProductIds) {
            $queryBuilder->whereNotIn('products.id', $excludedProductIds);
        }

        if ($applyPriceBand && $hasPrice) {
            $queryBuilder->where('products.price', '>=', $price * self::MIN_PRICE_RATIO)
                ->where('products.price', '<=', $price * self::MAX_PRICE_RATIO);
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
            'webpages.canonical_url',
            'products.offers_data as product_offers_data',
        ])->selectRaw("($scoreExpression) as alternative_score", $scoreBindings);
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
