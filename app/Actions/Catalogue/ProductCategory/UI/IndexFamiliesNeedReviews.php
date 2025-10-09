<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;
use Illuminate\Database\Eloquent\Builder;

class IndexFamiliesNeedReviews extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Group|Shop|ProductCategory|Organisation|Collection|MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('product_category_sales_intervals', 'product_category_sales_intervals.product_category_id', 'product_categories.id');
        $queryBuilder->leftJoin('product_category_ordering_intervals', 'product_category_ordering_intervals.product_category_id', 'product_categories.id');
        if ($parent instanceof Group) {
            $queryBuilder->where('product_categories.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('product_categories.organisation_id', $parent->id);
        } elseif (class_basename($parent) == 'ProductCategory') {
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('product_categories.department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('product_categories.sub_department_id', $parent->id);
            } else {
                abort(419);
            }
        } elseif (class_basename($parent) == 'MasterProductCategory') {
            $queryBuilder->where('product_categories.master_product_category_id', $parent->id);
        }


        $queryBuilder->where(function ($query) {
            $query->where(function ($subQuery) {
                $subQuery->where('product_categories.is_name_reviewed', false)
                    ->orWhereNull('product_categories.is_name_reviewed');
            })
            ->orWhere(function ($subQuery) {
                $subQuery->where('product_categories.is_description_title_reviewed', false)
                    ->orWhereNull('product_categories.is_description_title_reviewed');
            })
            ->orWhere(function ($subQuery) {
                $subQuery->where('product_categories.is_description_reviewed', false)
                    ->orWhereNull('product_categories.is_description_reviewed');
            })
            ->orWhere(function ($subQuery) {
                $subQuery->where('product_categories.is_description_extra_reviewed', false)
                    ->orWhereNull('product_categories.is_description_extra_reviewed');
            });
        });

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.image_id',
                'product_categories.updated_at',
                'product_categories.is_name_reviewed',
                'product_categories.is_description_title_reviewed',
                'product_categories.is_description_reviewed',
                'product_categories.is_description_extra_reviewed',
                'product_category_stats.number_current_products',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'product_category_sales_intervals.sales_grp_currency_all as sales_all',
                'product_category_ordering_intervals.invoices_all as invoices_all',
                'product_categories.master_product_category_id',
                DB::raw(
                    "(
                    SELECT json_agg(json_build_object(
                        'id', c.id,
                        'slug', c.slug,
                        'code', c.code,
                        'name', c.name
                    ))
                    FROM collection_has_models chm
                    JOIN collections c ON chm.collection_id = c.id
                    WHERE chm.model_id = product_categories.id
                        AND chm.model_type = 'ProductCategory'
                        AND c.deleted_at IS NULL
                )::text as collections"
                ),
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->allowedSorts([
                'code',
                'name',
                'shop_code',
                'number_current_products',
                'is_name_reviewed',
                'is_description_title_reviewed',
                'is_description_extra_reviewed',
                'is_description_reviewed',
                'sales_all',
                'invoices_all',
                AllowedSort::custom(
                    'collections',
                    new class () implements Sort {
                        public function __invoke(Builder $query, bool $descending, string $property)
                        {
                            $direction = $descending ? 'desc' : 'asc';
                            $query->orderBy(
                                DB::raw(
                                    "(
                                SELECT json_agg(c.name)
                                FROM collection_has_models chm
                                JOIN collections c ON chm.collection_id = c.id
                                WHERE chm.model_id = product_categories.id
                                AND chm.model_type = 'ProductCategory'
                                AND c.deleted_at IS NULL
                            )::text"
                                ),
                                $direction
                            );
                        }
                    }
                )
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|ProductCategory|Organisation|Collection|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                            'title' => __("No families need review"),
                            'count' => 0,
                    ]
                )


                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_name_reviewed', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_description_title_reviewed', label: __('description title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_description_reviewed', label: __('description'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_description_extra_reviewed', label: __('description extra'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }
    protected function getElementGroups($parent): array
    {
        return
            [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        ProductCategoryStateEnum::labels(),
                        ProductCategoryStateEnum::countFamily($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('product_categories.state', $elements);
                    }
                ]
            ];
    }
}
