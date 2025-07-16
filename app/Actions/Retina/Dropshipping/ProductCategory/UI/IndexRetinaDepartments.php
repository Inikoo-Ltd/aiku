<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\RetinaAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDepartments extends RetinaAction
{
    private Shop|Collection $parent;
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $this->shop;

        return $this->handle(parent: $this->shop);
    }

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('product_categories.state', ProductCategoryStateEnum::ACTIVE);


        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('product_category_sales_intervals', 'product_category_sales_intervals.product_category_id', 'product_categories.id');
        $queryBuilder->leftJoin('product_category_ordering_intervals', 'product_category_ordering_intervals.product_category_id', 'product_categories.id');

        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Collection') {
            $queryBuilder->join('model_has_collections', function ($join) use ($parent) {
                $join->on('product_categories.id', '=', 'model_has_collections.model_id')
                    ->where('model_has_collections.model_type', '=', 'ProductCategory')
                    ->where('model_has_collections.collection_id', '=', $parent->id);
            });
        }

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
                'product_categories.updated_at',
                'product_category_stats.number_current_families',
                'product_category_stats.number_current_products',
                'product_category_stats.number_current_sub_departments',
                'product_category_stats.number_current_collections',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'product_category_sales_intervals.sales_grp_currency_all as sales_all',
                'product_category_ordering_intervals.invoices_all as invoices_all',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::DEPARTMENT)
            ->allowedSorts(['code', 'name', 'shop_code', 'number_current_families', 'number_current_products', 'number_current_collections', 'number_current_sub_departments'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory|Collection $parent, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Shop' => [
                            'title'       => __("No departments found"),
                            'description' => $canEdit ? __('Get started by creating a new department. ✨')
                                : null,
                            'count'       => $parent->stats->number_departments,
                            'action'      => $canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new department'),
                                'label'   => __('department'),
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.departments.create',
                                    'parameters' => [$parent->organisation->slug, $parent->slug]
                                ]
                            ] : null
                        ],
                        default => null
                    }
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


                $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);


                if (class_basename($parent) != 'Collection') {
                    $table->column(key: 'number_current_sub_departments', label: __('sub-departments'), tooltip: __('current sub departments'), canBeHidden: false, sortable: true, searchable: true);
                    $table->column(key: 'number_current_collections', label: __('collections'), tooltip: __('current collections'), canBeHidden: false, sortable: true, searchable: true);

                    $table->column(key: 'number_current_families', label: __('families'), tooltip: __('current families'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'number_current_products', label: __('products'), tooltip: __('current products'), canBeHidden: false, sortable: true, searchable: true);
                }

                if (class_basename($parent) == 'Collection') {
                    $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
                }
        };
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return DepartmentsResource::collection($departments);
    }

    public function htmlResponse(LengthAwarePaginator $departments, ActionRequest $request): Response
    {
        $title      = __('Departments');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder-tree'],
            'title' => __('departments')
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof Collection) {
            $title      = $this->parent->name;
            $model      = __('collection');
            $icon       = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => __('collection')
            ];
            $iconRight  = [
                'icon' => 'fal fa-folder-tree',
            ];
            $afterTitle = [
                'label' => __('Departments')
            ];
        }

        return Inertia::render(
            'Catalogue/RetinaDepartments',
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
                ],
                'data'                                => DepartmentsResource::collection($departments),
            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'retina.catalogue.department.index' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs(),
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
