<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class SearchIrisCataloguePage extends IrisAction
{
    use WithRawSearchResults;

    /**
     * @param array{q: string, categories?: array<int, int>, page?: int, per_page?: int, sort?: string|null} $modelData
     *
     * @return array{results: array{products: array<int, array<string, mixed>>, total: int, page: int, last_page: int, per_page: int, facets: array<string, array<int, array<string, mixed>>>, collections: array<int, array<string, mixed>>}}
     */
    public function handle(array $modelData): array
    {
        $query       = Arr::get($modelData, 'q');
        $categoryIds = array_map('intval', Arr::get($modelData, 'categories', []));
        $brandIds    = array_map('intval', Arr::get($modelData, 'brands', []));
        $tagIds      = array_map('intval', Arr::get($modelData, 'tags', []));
        $priceMin    = Arr::get($modelData, 'price_min');
        $priceMax    = Arr::get($modelData, 'price_max');
        $perPage     = (int) Arr::get($modelData, 'per_page', 15);
        $pageNumber  = (int) Arr::get($modelData, 'page', 1);
        $sort        = Arr::get($modelData, 'sort');

        $matchedIds = $this->matchedProductIds($query);

        if (empty($matchedIds)) {
            return [
                'results' => [
                    'products'    => [],
                    'total'       => 0,
                    'page'        => 1,
                    'last_page'   => 1,
                    'per_page'    => $perPage,
                    'facets'      => [
                        'departments'     => [],
                        'sub_departments' => [],
                        'families'        => [],
                        'brands'          => [],
                        'tags'            => [],
                        'price'           => ['min' => null, 'max' => null],
                    ],
                    'collections' => [],
                ],
            ];
        }

        $productsQuery = Product::query()->whereIn('id', $matchedIds);
        $this->applyCategoryFilters($productsQuery, $categoryIds);

        if (!empty($brandIds)) {
            $productsQuery->whereHas('brands', fn ($brandQuery) => $brandQuery->whereIn('brands.id', $brandIds));
        }
        if (!empty($tagIds)) {
            $productsQuery->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('tags.id', $tagIds));
        }
        if ($priceMin !== null && $priceMin !== '') {
            $productsQuery->where('price', '>=', (float) $priceMin);
        }
        if ($priceMax !== null && $priceMax !== '') {
            $productsQuery->where('price', '<=', (float) $priceMax);
        }

        $total    = (clone $productsQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($sort === 'price_amount:asc' || $sort === 'price_amount:desc') {
            $productsQuery->orderBy('price', $sort === 'price_amount:asc' ? 'asc' : 'desc');
        } else {
            $productsQuery->orderByRaw('array_position(ARRAY['.implode(',', $matchedIds).']::bigint[], products.id)');
        }

        $showPrice = auth()->check();

        $products = $productsQuery
            ->with(['webpage' => fn ($webpageQuery) => $webpageQuery->where('website_id', $this->website->id)->with('shop')])
            ->forPage($pageNumber, $perPage)
            ->get()
            ->map(fn (Product $product) => [
                'id'    => $product->id,
                'code'  => $product->code,
                'name'  => $product->name,
                'image' => $product->imageSources(200, 200),
                'url'   => $product->webpage?->getCanonicalUrl() ?: null,
                'stock' => $product->available_quantity,
                'units' => $product->units,
                'unit'  => $product->unit,
                'price' => $showPrice ? $product->price : null,
            ])
            ->values()
            ->all();

        return [
            'results' => [
                'products'    => $products,
                'total'       => $total,
                'page'        => $pageNumber,
                'last_page'   => $lastPage,
                'per_page'    => $perPage,
                'facets'      => array_merge(
                    $this->categoryFacets($matchedIds),
                    $this->brandTagFacets($matchedIds),
                    ['price' => $this->priceRange($matchedIds)],
                ),
                'collections' => $this->matchedCollections($query),
            ],
        ];
    }

    /**
     * Relevance-ordered product ids matching the query. Without an explicit take() the
     * Typesense engine runs a single request at its 250 per_page maximum; take(250) or more
     * would switch it to the paginated path capped by scout.typesense.max_total_results (100).
     *
     * @return array<int, int>
     */
    private function matchedProductIds(string $query): array
    {
        $searchQuery = Product::search($query)->where('shop_id', $this->shop->id);

        return array_values(array_unique(array_map('intval', array_filter(array_column($this->rawDocuments($searchQuery), 'id')))));
    }

    /**
     * Selections within the same category type are OR-ed, across types AND-ed.
     *
     * @param array<int, int> $categoryIds
     */
    private function applyCategoryFilters(Builder $productsQuery, array $categoryIds): void
    {
        if (empty($categoryIds)) {
            return;
        }

        $columnByType = [
            ProductCategoryTypeEnum::DEPARTMENT->value     => 'department_id',
            ProductCategoryTypeEnum::SUB_DEPARTMENT->value => 'sub_department_id',
            ProductCategoryTypeEnum::FAMILY->value         => 'family_id',
        ];

        $selectedByType = ProductCategory::query()
            ->whereIn('id', $categoryIds)
            ->get(['id', 'type'])
            ->groupBy(fn (ProductCategory $category) => $category->type->value);

        foreach ($columnByType as $type => $column) {
            $ids = $selectedByType->get($type)?->pluck('id')->all();
            if ($ids) {
                $productsQuery->whereIn($column, $ids);
            }
        }
    }

    /**
     * Category facets with the number of matched products per category, unaffected by the
     * active category filters so unchecking within a group stays possible.
     *
     * @param array<int, int> $matchedIds
     *
     * @return array{departments: array<int, array<string, mixed>>, sub_departments: array<int, array<string, mixed>>, families: array<int, array<string, mixed>>}
     */
    private function categoryFacets(array $matchedIds): array
    {
        $rows = Product::query()->whereIn('id', $matchedIds)->get(['department_id', 'sub_department_id', 'family_id']);

        $countsByColumn = [];
        foreach (['department_id', 'sub_department_id', 'family_id'] as $column) {
            $countsByColumn[$column] = $rows->pluck($column)->filter()->countBy()->all();
        }

        $allCategoryIds = collect($countsByColumn)->flatMap(fn (array $counts) => array_keys($counts))->unique()->values()->all();

        $categories = ProductCategory::query()
            ->whereIn('id', $allCategoryIds)
            ->with(['webpage' => fn ($webpageQuery) => $webpageQuery->where('website_id', $this->website->id)->with('shop')])
            ->get()
            ->keyBy('id');

        $buildGroup = fn (string $column) => collect($countsByColumn[$column])
            ->map(function (int $count, int $categoryId) use ($categories) {
                $category = $categories->get($categoryId);
                if (!$category) {
                    return null;
                }

                return [
                    'id'    => $category->id,
                    'name'  => $category->name,
                    'count' => $count,
                    'url'   => $category->webpage?->getCanonicalUrl() ?: null,
                    'image' => $category->imageSources(200, 200) ?: Arr::get($category->web_images ?? [], 'main.thumbnail'),
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'departments'     => $buildGroup('department_id'),
            'sub_departments' => $buildGroup('sub_department_id'),
            'families'        => $buildGroup('family_id'),
        ];
    }

    /**
     * Brand and tag facets counted from the matched products, capped to the 30 most frequent
     * per group to keep the side panel manageable.
     *
     * @param array<int, int> $matchedIds
     *
     * @return array{brands: array<int, array<string, mixed>>, tags: array<int, array<string, mixed>>}
     */
    private function brandTagFacets(array $matchedIds): array
    {
        $morphClass = (new Product())->getMorphClass();

        $buildGroup = function (string $table, string $column, string $modelClass) use ($matchedIds, $morphClass) {
            $counts = DB::table($table)
                ->where('model_type', $morphClass)
                ->whereIn('model_id', $matchedIds)
                ->select($column, DB::raw('count(distinct model_id) as count'))
                ->groupBy($column)
                ->pluck('count', $column);

            if ($counts->isEmpty()) {
                return [];
            }

            $models = $modelClass::query()->whereIn('id', $counts->keys()->all())->get()->keyBy('id');

            return $counts
                ->map(function ($count, $id) use ($models) {
                    $model = $models->get($id);
                    if (!$model) {
                        return null;
                    }

                    return [
                        'id'    => $model->id,
                        'name'  => $model->name,
                        'count' => (int) $count,
                    ];
                })
                ->filter()
                ->sortByDesc('count')
                ->take(30)
                ->values()
                ->all();
        };

        return [
            'brands' => $buildGroup('model_has_brands', 'brand_id', Brand::class),
            'tags'   => $buildGroup('model_has_tags', 'tag_id', Tag::class),
        ];
    }

    /**
     * @param array<int, int> $matchedIds
     *
     * @return array{min: float|null, max: float|null}
     */
    private function priceRange(array $matchedIds): array
    {
        $range = Product::query()
            ->whereIn('id', $matchedIds)
            ->selectRaw('min(price) as min_price, max(price) as max_price')
            ->first();

        return [
            'min' => $range?->min_price !== null ? (float) $range->min_price : null,
            'max' => $range?->max_price !== null ? (float) $range->max_price : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchedCollections(string $query): array
    {
        $collectionsQuery = Collection::search($query)->where('shop_id', $this->shop->id);
        $ids              = array_map('intval', array_filter(array_column($this->rawDocuments($collectionsQuery), 'id')));

        if (empty($ids)) {
            return [];
        }

        return Collection::query()
            ->whereIn('id', $ids)
            ->with(['webpage' => fn ($webpageQuery) => $webpageQuery->where('website_id', $this->website->id)->with('shop')])
            ->get()
            ->map(fn (Collection $collection) => [
                'id'    => $collection->id,
                'code'  => $collection->code,
                'name'  => $collection->name,
                'image' => $collection->imageSources(200, 200) ?: Arr::get($collection->web_images ?? [], 'main.thumbnail'),
                'url'   => $collection->webpage?->getCanonicalUrl() ?: null,
            ])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'q'            => ['required', 'string'],
            'categories'   => ['sometimes', 'array'],
            'categories.*' => ['integer'],
            'brands'       => ['sometimes', 'array'],
            'brands.*'     => ['integer'],
            'tags'         => ['sometimes', 'array'],
            'tags.*'       => ['integer'],
            'price_min'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_max'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'page'         => ['sometimes', 'integer', 'min:1'],
            'per_page'     => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort'         => ['sometimes', 'nullable', 'in:price_amount:asc,price_amount:desc'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
