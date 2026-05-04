<?php

/*
 * author Louis Perez
 * created on 10-03-2026-10h-30m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;
use Illuminate\Database\Eloquent\Builder;

class IndexFamiliesInMasterFamilies extends OrgAction
{
    use WithMastersAuthorisation;
    use WithDepartmentSubNavigation;
    use WithCollectionSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;

    private MasterProductCategory $parent;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inGroup(MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');

        $queryBuilder->where('product_categories.master_product_category_id', $parent->id)
            ->where('shops.state', '!=', ShopStateEnum::CLOSED->value);

        $selects = [
            'product_categories.id',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.state',
            'product_categories.description',
            'product_categories.created_at',
            'product_categories.image_id',
            'product_categories.updated_at',
            'product_categories.web_images',
            'departments.slug as department_slug',
            'departments.code as department_code',
            'departments.name as department_name',
            'sub_departments.slug as sub_department_slug',
            'sub_departments.code as sub_department_code',
            'sub_departments.name as sub_department_name',
            'product_category_stats.number_current_products',
            'shops.slug as shop_slug',
            'shops.code as shop_code',
            'shops.name as shop_name',
            'currencies.code as currency_code',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
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
        ];
        $queryBuilder->select($selects);

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->leftjoin('product_categories as sub_departments', 'sub_departments.id', 'product_categories.sub_department_id')
            ->allowedSorts([
                'code',
                'name',
                'shop_code',
                'department_code',
                'number_current_products',
                'sub_department_name',
                'department_name',
                'sales_grp_currency_external',
                'invoices',
                'dropshippers',
                'listings',
                'sold',
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

    public function tableStructure(MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("No families found under this master family"),
                        'count' => $parent->stats->number_families,
                    ]
                )
                ->withLabelRecord([__('family'), __('families')])
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);

            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'image_thumbnail', label: '', type: 'avatar');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'department_name', label: __('Department'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sub_department_name', label: __('Sub department'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        $modelNavigation = [];
        $navigation = ProductCategoryTabsEnum::navigationExcept([ProductCategoryTabsEnum::SALES]);
        $subNavigation = $this->getMasterFamilySubNavigation($this->parent);


        $title           = $this->parent->name;
        $model           = '';
        $icon            = [
            'icon'  => ['fal', 'fa-folder'],
            'title' => $this->parent->name
        ];
        $afterTitle      = [
            'label' => __('Families in Shop')
        ];
        $iconRight       = [
            'icon' => 'fal fa-store',
        ];


        return Inertia::render(
            'Org/Catalogue/Families',
            [
                'breadcrumbs'                         => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                          => $modelNavigation,
                'title'                               => __('Families'),
                'pageHead'                            => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'                                => FamiliesResource::collection($families),
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductCategoryTabsEnum::INDEX->value => $this->tab == ProductCategoryTabsEnum::INDEX->value ?
                    fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection($families)),

                ProductCategoryTabsEnum::NEED_REVIEW->value => $this->tab == ProductCategoryTabsEnum::NEED_REVIEW->value ?
                    fn () => FamiliesResource::collection(IndexFamiliesNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))
                    : Inertia::lazy(fn () => FamiliesResource::collection(IndexFamiliesNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))),
            ]
        )
        ->table($this->tableStructure(parent: $this->parent, prefix: ProductCategoryTabsEnum::INDEX->value))
        ->table(IndexFamiliesNeedReviews::make()->tableStructure(parent: $this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value));
    }

    public function getBreadcrumbs(MasterProductCategory $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Families in Shop'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_departments.show.master_families.families',
            'grp.masters.master_shops.show.master_family.mismatch_detected.families',
            'grp.masters.master_shops.show.master_sub_departments.master_families.families',
            'grp.masters.master_shops.show.master_families.families',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families',
            'grp.masters.master_shops.show.master_departments.show.master_families.families',
            'grp.masters.master_departments.show.master_sub_departments.show.master_families.families',
            'grp.masters.master_departments.show.master_families.families' =>
            array_merge(
                ShowMasterFamily::make()->getBreadcrumbs($parent, preg_replace("/families$/", "show", $routeName), $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
