<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterSubDepartmentsResource;
use App\InertiaTable\InertiaTable;
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

class IndexMasterSubDepartments extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;

    private MasterShop|MasterProductCategory $parent;

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle(parent: $masterShop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle(parent: $masterDepartment);
    }

    public function handle(MasterShop|MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.slug', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);
        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id');
        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
        } else {
            $queryBuilder->where('master_product_categories.master_parent_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.status',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.updated_at',
                'master_product_category_stats.number_current_master_product_categories_type_family as number_families',
                'master_product_category_stats.number_current_master_assets_type_product as number_products',
            ])
            ->where('master_product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->allowedSorts(['code', 'name', 'number_families', 'number_products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No sub departments found"),
                    ],
                );

            $table->column(key: 'code', label: __('code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), sortable: true, searchable: true)
                ->column(key: 'number_families', label: __('M. Families'), sortable: true)
                ->column(key: 'number_products', label: __('M. Products'), sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterSubDepartments): AnonymousResourceCollection
    {
        return MasterSubDepartmentsResource::collection($masterSubDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $masterSubDepartments, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
        } elseif ($this->parent instanceof MasterProductCategory) {
            $subNavigation = $this->getMasterDepartmentSubNavigation($this->parent);
        }
        $title      = $this->parent->name;
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('master shop')
        ];
        $afterTitle = [
            'label' => __('Master Sub Departments')
        ];
        $iconRight  = [
            'icon' => 'fal fa-folder-tree',
        ];

        return Inertia::render(
            'Masters/MasterSubDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Master Sub Departments'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new master Sub-department'),
                            'label'   => __('Sub-department'),
                            'route'   => match ($this->parent::class) {
                                MasterProductCategory::class => [
                                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.create',
                                    'parameters' => $request->route()->originalParameters()
                                ],
                                default => [
                                    'name'       => 'grp.masters.master_shops.show.master_sub_departments.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            }
                        ],
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterSubDepartmentsResource::collection($masterSubDepartments),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master sub departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_sub_departments.edit' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
                $headCrumb(
                    [
                        'name'       => 'grp.masters.master_shops.show.master_sub_departments.index',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            'grp.masters.master_departments.show.master_sub_departments.index',
            'grp.masters.master_departments.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs(
                    $parent->masterShop,
                    $parent,
                    $routeName,
                    $routeParameters
                ),
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
