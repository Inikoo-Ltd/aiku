<?php

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisBlogProductCarousels
{
    use AsObject;
    use WithIrisProductsInWebpage;

    public const int MAX_PRODUCTS_PER_CAROUSEL = 20;

    public const int DEFAULT_FAMILY_PRODUCTS_LIMIT = 12;

    /**
     * @return array{products: array<string, array<string, mixed>>, families: array<string, array<int, string>>}
     */
    public function handle(Webpage $webpage, ?string $content): array
    {
        return $this->forCarousels($webpage, $this->carouselsInContent((string) $content));
    }

    /**
     * @param  Collection<int, array{source: string, codes: array<int, string>, family: ?string, limit: int}>  $carousels
     *
     * @return array{products: array<string, array<string, mixed>>, families: array<string, array<int, string>>}
     */
    public function forCarousels(Webpage $webpage, Collection $carousels): array
    {
        $families = $this->familyProductCodes($webpage, $this->familyLimits($carousels));

        $codes = $carousels
            ->flatMap(fn (array $carousel) => $carousel['codes'])
            ->merge(collect($families)->flatten())
            ->unique()
            ->values()
            ->all();

        return [
            'products' => $this->productCards($webpage, $codes),
            'families' => $families,
        ];
    }

    /**
     * @param  array{source: string, codes: array<int, string>, family: ?string, limit: int}  $carousel
     *
     * @return array<int, array<string, mixed>>
     */
    public function productsInCarousel(Webpage $webpage, array $carousel): array
    {
        $resolved = $this->forCarousels($webpage, collect([$carousel]));

        $codes = $carousel['source'] === 'family'
            ? array_slice($resolved['families'][$carousel['family']] ?? [], 0, $carousel['limit'])
            : $carousel['codes'];

        return collect($codes)
            ->map(fn (string $code) => $resolved['products'][$code] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{source: string, codes: array<int, string>, family: ?string, limit: int}
     */
    public function carousel(?string $source, ?string $codes, ?string $family, ?string $limit): array
    {
        $source = $source === 'family' ? 'family' : 'products';

        return [
            'source' => $source,
            'codes'  => $source === 'products'
                ? collect(explode(',', (string) $codes))
                    ->map(fn (string $code) => mb_strtolower(trim($code)))
                    ->filter()
                    ->unique()
                    ->take(self::MAX_PRODUCTS_PER_CAROUSEL)
                    ->values()
                    ->all()
                : [],
            'family' => $source === 'family' ? (mb_strtolower(trim((string) $family)) ?: null) : null,
            'limit'  => $this->familyLimit($limit),
        ];
    }

    /**
     * @return Collection<int, array{source: string, codes: array<int, string>, family: ?string, limit: int}>
     */
    public function carouselsInContent(string $content): Collection
    {
        preg_match_all('/<div\b[^>]*\bdata-product-carousel\b[^>]*>/i', $content, $carouselTags);

        return collect($carouselTags[0])->map(fn (string $carouselTag) => $this->carousel(
            $this->attribute($carouselTag, 'data-source'),
            $this->attribute($carouselTag, 'data-codes'),
            $this->attribute($carouselTag, 'data-family'),
            $this->attribute($carouselTag, 'data-limit'),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function codesInContent(string $content): array
    {
        return $this->carouselsInContent($content)
            ->flatMap(fn (array $carousel) => $carousel['codes'])
            ->unique()
            ->values()
            ->all();
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (!preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $match)) {
            return null;
        }

        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
    }

    private function familyLimit(?string $limit): int
    {
        $limit = (int) $limit;

        return $limit >= 1 ? min($limit, self::MAX_PRODUCTS_PER_CAROUSEL) : self::DEFAULT_FAMILY_PRODUCTS_LIMIT;
    }

    /**
     * @param  Collection<int, array{source: string, codes: array<int, string>, family: ?string, limit: int}>  $carousels
     *
     * @return array<string, int>
     */
    private function familyLimits(Collection $carousels): array
    {
        return $carousels
            ->filter(fn (array $carousel) => $carousel['family'] !== null)
            ->groupBy('family')
            ->map(fn (Collection $familyCarousels) => $familyCarousels->max('limit'))
            ->all();
    }

    /**
     * @param  array<string, int>  $familyLimits
     *
     * @return array<string, array<int, string>>
     */
    private function familyProductCodes(Webpage $webpage, array $familyLimits): array
    {
        if ($familyLimits === []) {
            return [];
        }

        $families = ProductCategory::where('shop_id', $webpage->shop_id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn(DB::raw('lower(code)'), array_keys($familyLimits))
            ->get(['id', 'code']);

        return $families->mapWithKeys(function (ProductCategory $family) use ($familyLimits) {
            $queryBuilder = $this->getBaseQuery('all');
            $queryBuilder->where('products.family_id', $family->id);

            $codes = $queryBuilder
                ->orderByRaw('products.available_quantity > 0 desc')
                ->orderByRaw('products.index_under_family asc nulls last')
                ->orderBy('products.code')
                ->limit($familyLimits[mb_strtolower($family->code)])
                ->pluck('products.code')
                ->map(fn (string $code) => mb_strtolower($code))
                ->all();

            return [mb_strtolower($family->code) => $codes];
        })->all();
    }

    /**
     * @param  array<int, string>  $codes
     *
     * @return array<string, array<string, mixed>>
     */
    private function productCards(Webpage $webpage, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $queryBuilder = $this->getBaseQuery('all');
        $queryBuilder->where('products.shop_id', $webpage->shop_id);
        $queryBuilder->whereIn(DB::raw('lower(products.code)'), $codes);
        $queryBuilder->select($this->getSelect());

        return collect(IrisAuthenticatedProductsInWebpageResource::collection($queryBuilder->get())->resolve())
            ->keyBy(fn (array $product) => mb_strtolower($product['code']))
            ->all();
    }
}
