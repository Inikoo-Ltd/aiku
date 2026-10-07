<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\Catalogue\ProductCategory\WithFamiliesFromParentCollections;
use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\FamiliesInDepartmentWebpageResource;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class GetFamiliesUnderDepartmentPage extends IrisAction
{
    use WithFamiliesFromParentCollections;

    public function handle(ProductCategory $parent): LengthAwarePaginator
    {
        if (!in_array($parent->type, [ProductCategoryTypeEnum::DEPARTMENT, ProductCategoryTypeEnum::SUB_DEPARTMENT])) {
            abort(404);
        }

        $parentColumn = $parent->type === ProductCategoryTypeEnum::SUB_DEPARTMENT
            ? 'product_categories.sub_department_id'
            : 'product_categories.department_id';

        $categorySearch = AllowedFilter::callback('category', function ($query, $value) {
            $query->where("sub_department.code", $value);
        });

        $collectionSearch = AllowedFilter::callback('collection', function ($query, $value) {
            $query->whereExists(function ($sub) use ($value) {
                $sub->selectRaw(1)
                    ->from('collection_has_models as chm')
                    ->join('collections as c', 'c.id', '=', 'chm.collection_id')
                    ->whereColumn('chm.model_id', 'product_categories.id')
                    ->where('chm.model_type', class_basename(ProductCategory::class))
                    ->where('c.code', $value);
            });
        });

        $query = QueryBuilder::for(ProductCategory::class)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->leftJoin('product_categories as sub_department', 'product_categories.sub_department_id', '=', 'sub_department.id')
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'sub_department.code as sub_department_code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.image_id',
                    'product_categories.created_at',
                    'product_categories.website_position',
                    'webpages.canonical_url'
                ]
            )
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->where('product_categories.show_in_website', true)
            ->where('product_categories.shop_id', $parent->shop_id)
            ->where(function ($q) use ($parent, $parentColumn) {
                $q->where($parentColumn, $parent->id)
                    ->orWhereIn('product_categories.id', $this->familyIdsFromParentCollections($parent->id));
            })
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at');

        $curatedSort = AllowedSort::custom(
            'website_position',
            new class ($parentColumn, $parent->id) implements Sort {
                public function __construct(private readonly string $parentColumn, private readonly int $parentId)
                {
                }

                public function __invoke(Builder $query, bool $descending, string $property)
                {
                    $direction = $descending ? 'DESC' : 'ASC';
                    $query->orderByRaw("CASE WHEN $this->parentColumn = ? THEN 0 ELSE 1 END", [$this->parentId])
                        ->orderByRaw("product_categories.website_position $direction NULLS LAST")
                        ->orderByRaw('product_categories.created_at DESC');
                }
            }
        );

        return $query
            ->defaultSort($curatedSort)
            ->allowedSorts(['code', 'name', 'created_at', $curatedSort])
            ->allowedFilters([$categorySearch, $collectionSearch])
            ->withIrisPaginator(500)
            ->withQueryString();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($productCategory);
    }

    public function jsonResponse(LengthAwarePaginator $familyList): AnonymousResourceCollection
    {
        return FamiliesInDepartmentWebpageResource::collection($familyList);
    }
}
