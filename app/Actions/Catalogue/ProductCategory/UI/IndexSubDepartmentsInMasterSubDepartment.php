<?php

/*
 * author Louis Perez
 * created on 10-03-2026-12h-57m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSubDepartmentsInMasterSubDepartment extends OrgAction
{
    use WithMastersAuthorisation;
    use WithDepartmentSubNavigation;
    use WithMasterSubDepartmentSubNavigation;

    private MasterProductCategory $parent;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(ProductCategoryTabsEnum::valuesExcept([ProductCategoryTabsEnum::SALES]));

        return $this->handle($masterSubDepartment, ProductCategoryTabsEnum::INDEX->value);
    }

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(ProductCategoryTabsEnum::valuesExcept([ProductCategoryTabsEnum::SALES]));

        return $this->handle($masterSubDepartment, ProductCategoryTabsEnum::INDEX->value);
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

        $queryBuilder->where('product_categories.master_product_category_id', $parent->id);
        $queryBuilder->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT);
        $queryBuilder->where('shops.state', '!=', ShopStateEnum::CLOSED->value);
        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', 'organisations.id');
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');

        $selects = [
            'product_categories.id',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.state',
            'product_categories.description',
            'product_categories.master_product_category_id',
            'product_categories.created_at',
            'product_categories.updated_at',
            'product_categories.web_images',
            'departments.slug as department_slug',
            'departments.code as department_code',
            'departments.name as department_name',
            'shops.slug as shop_slug',
            'shops.code as shop_code',
            'shops.name as shop_name',
            'currencies.code as currency_code',
            'organisations.slug as organisation_slug',
            'organisations.code as organisation_code',
            'organisations.name as organisation_name',
            'product_category_stats.number_current_families as number_families',
            'product_category_stats.number_current_products as number_products',
        ];

        $queryBuilder->select($selects);

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->allowedSorts(['code', 'name', 'shop_code', 'department_code', 'number_families', 'number_products', 'sales_grp_currency_external', 'invoices', 'dropshippers', 'listings', 'sold'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false, $sales = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("No sub departments found under this master sub department"),
                        'count' => $parent->stats->number_sub_departments,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'shop_code', label: __('Shop'), sortable: true)
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), sortable: true)
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'number_families', label: __('Families'), sortable: true)
                ->column(key: 'number_products', label: __('Products'), sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $subDepartment): AnonymousResourceCollection
    {
        return SubDepartmentsResource::collection($subDepartment);
    }

    public function htmlResponse(LengthAwarePaginator $subDepartment, ActionRequest $request): Response
    {
        $modelNavigation = [];
        $navigation = ProductCategoryTabsEnum::navigationExcept([ProductCategoryTabsEnum::SALES]);
        $subNavigation = $this->getMasterSubDepartmentSubNavigation($this->parent);

        $title           = $this->parent->name;
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder-download'],
            'title' => $this->parent->name
        ];
        $afterTitle      = [
            'label' => __('Sub-Departments in Shop')
        ];
        $iconRight       = [
            'icon' => 'fal fa-store',
        ];

        return Inertia::render(
            'Org/Catalogue/SubDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => $modelNavigation,
                'title'       => __('sub-departments'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                'data'        => SubDepartmentsResource::collection($subDepartment),

                ProductCategoryTabsEnum::INDEX->value => $this->tab == ProductCategoryTabsEnum::INDEX->value ?
                    fn () => SubDepartmentsResource::collection($subDepartment)
                    : Inertia::lazy(fn () => SubDepartmentsResource::collection($subDepartment)),

                ProductCategoryTabsEnum::NEED_REVIEW->value => $this->tab == ProductCategoryTabsEnum::NEED_REVIEW->value ?
                    fn () => SubDepartmentsResource::collection(IndexSubDepartmentsNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))
                    : Inertia::lazy(fn () => SubDepartmentsResource::collection(IndexSubDepartmentsNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))),
            ]
        )
        ->table($this->tableStructure($this->parent, prefix: ProductCategoryTabsEnum::INDEX->value))
        ->table(IndexSubDepartmentsNeedReviews::make()->tableStructure(parent: $this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Sub-Departments in Shop'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.sub_departments',
            'grp.masters.master_departments.show.master_sub_departments.sub_departments',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.sub_departments' => array_merge(
                ShowMasterSubDepartment::make()->getBreadcrumbs($this->parent, preg_replace("/sub_departments$/", "show", $routeName), $routeParameters),
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
