<?php

/*
 * author Louis Perez
 * created on 10-03-2026-13h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
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

class IndexDepartmentsInMasterDepartment extends OrgAction
{
    use WithCollectionSubNavigation;
    use WithMastersAuthorisation;
    use WithMasterDepartmentSubNavigation;

    private MasterProductCategory $parent;

    public function inGroup(MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterDepartment;
        $this->initialisationFromGroup($group, $request)->withTab(ProductCategoryTabsEnum::valuesExcept([ProductCategoryTabsEnum::SALES]));

        return $this->handle($masterDepartment, ProductCategoryTabsEnum::INDEX->value);
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(ProductCategoryTabsEnum::valuesExcept([ProductCategoryTabsEnum::SALES]));

        return $this->handle($masterDepartment, ProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id');
        $queryBuilder->where('product_categories.master_product_category_id', $parent->id);

        $selects = [
            'product_categories.id',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.state',
            'product_categories.description',
            'product_categories.created_at',
            'product_categories.updated_at',
            'product_categories.web_images',
            'product_categories.master_product_category_id',
            'product_category_stats.number_current_families',
            'product_category_stats.number_current_products',
            'product_category_stats.number_current_sub_departments',
            'product_category_stats.number_current_collections',
            'shops.slug as shop_slug',
            'shops.code as shop_code',
            'shops.name as shop_name',
            'currencies.code as currency_code',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
        ];

        $queryBuilder->select($selects);

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::DEPARTMENT)
            ->allowedSorts([
                'code',
                'name',
                'shop_code',
                'number_current_families',
                'number_current_products',
                'number_current_collections',
                'number_current_sub_departments',
                'sales_grp_currency_external',
                'invoices',
                'dropshippers',
                'listings',
                'sold',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $parent, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withLabelRecord([__('department'),__('departments')])
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No departments found under this master department"),
                        'count' => $parent->stats->number_departments,
                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');

            $table
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return DepartmentsResource::collection($departments);
    }

    public function htmlResponse(LengthAwarePaginator $departments, ActionRequest $request): Response
    {
        $modelNavigation = [];
        $subNavigation = $this->getMasterDepartmentSubNavigation($this->parent);
        $navigation = ProductCategoryTabsEnum::navigationExcept([ProductCategoryTabsEnum::SALES]);

        $title           = $this->parent->name;
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder-tree'],
            'title' => $this->parent->name
        ];
        $afterTitle      = [
            'label' => __('Departments in Shop')
        ];
        $iconRight       = [
            'icon' => 'fal fa-store',
        ];

        return Inertia::render(
            'Org/Catalogue/Departments',
            [
                'breadcrumbs'                         => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                               => __('Departments'),
                'pageHead'                            => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'                                => DepartmentsResource::collection($departments),
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductCategoryTabsEnum::INDEX->value => $this->tab == ProductCategoryTabsEnum::INDEX->value ?
                    fn () => DepartmentsResource::collection($departments)
                    : Inertia::lazy(fn () => DepartmentsResource::collection($departments)),
                ProductCategoryTabsEnum::NEED_REVIEW->value => $this->tab == ProductCategoryTabsEnum::NEED_REVIEW->value ?
                    fn () => DepartmentsResource::collection(IndexDepartmentsNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))
                    : Inertia::lazy(fn () => DepartmentsResource::collection(IndexDepartmentsNeedReviews::run($this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value))),
            ]
        )
        ->table($this->tableStructure(parent: $this->parent, prefix: ProductCategoryTabsEnum::INDEX->value))
        ->table(IndexDepartmentsNeedReviews::make()->tableStructure(parent: $this->parent, prefix: ProductCategoryTabsEnum::NEED_REVIEW->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Departments in Shop'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_departments.show.departments',
            'grp.masters.master_shops.show.master_departments.show.departments' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs($this->parent->masterShop, $this->parent, preg_replace("/.departments$/", "", $routeName), $routeParameters),
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
