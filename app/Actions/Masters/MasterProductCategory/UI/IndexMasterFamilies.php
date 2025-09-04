<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterFamilies extends OrgAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterSubDepartmentSubNavigation;

    private Group|MasterShop|MasterProductCategory $parent;


    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $masterShop);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $parent       = $this->parent;
        $this->initialisationFromGroup($parent, $request);

        return $this->handle(parent: $parent);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $parent       = $this->parent;
        $this->initialisationFromGroup($masterDepartment->group, $request);

        return $this->handle(parent: $parent);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $parent       = $this->parent;
        $this->initialisationFromGroup($masterDepartment->group, $request);

        return $this->handle(parent: $parent);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $parent       = $this->parent;
        $this->initialisationFromGroup($masterSubDepartment->group, $request);

        return $this->handle(parent: $parent, parentType: 'sub_department');
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $parent       = $this->parent;
        $this->initialisationFromGroup($masterSubDepartment->group, $request);

        return $this->handle(parent: $parent, parentType: 'sub_department');
    }


    public function handle(Group|MasterShop|MasterProductCategory $parent, string $parentType = 'department', $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('master_product_categories.type', ProductCategoryTypeEnum::FAMILY);
        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id');

        $queryBuilder->select([
            'master_product_categories.id',
            'master_product_categories.slug',
            'master_product_categories.code',
            'master_product_categories.name',
            'master_product_categories.status',
            'master_product_categories.description',
            'master_product_categories.created_at',
            'master_product_categories.updated_at',
            'master_product_category_stats.number_current_families as used_in',
            'master_product_category_stats.number_current_master_assets_type_product as products',

        ]);


        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
        } elseif ($parent instanceof MasterProductCategory) {
            if ($parentType == 'department') {
                $queryBuilder->where('master_product_categories.master_department_id', $parent->id);
            } elseif ($parentType == 'sub_department') {
                $queryBuilder->where('master_product_categories.master_sub_department_id', $parent->id);
            } else {
                $queryBuilder->where('master_product_categories.master_sub_department_id', $parent->id);
            }
        } else {
            $queryBuilder->where('master_product_categories.group_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_product_categories.master_shop_id');
            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_product_categories.master_department_id');
            $queryBuilder->addSelect([
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name',
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
            ]);
        }

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts(['code', 'name','used_in','products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
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
                        'title' => __("No master families found"),
                    ],
                );

            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('Shop'), sortable: true);
                $table->column('master_department_code', __('Department'), sortable: true);
            }


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current families with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'products', label: __('products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterFamilies): AnonymousResourceCollection
    {
        return MasterFamiliesResource::collection($masterFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $masterShop = null;
        $subNavigation = null;
        $title         = $this->parent->name;
        $model         = '';
        $icon          = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('master shop')
        ];
        $afterTitle    = [
            'label' => __('Master Families')
        ];
        $iconRight     = [
            'icon' => 'fal fa-folder-tree',
        ];
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $masterShop = $this->parent;
        } elseif ($this->parent instanceof Group) {
            $title      = __('Master families');
            $icon       = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => $title
            ];
            $afterTitle = [
                'label' => __('In group')
            ];
            $iconRight  = [
                'icon' => 'fal fa-city',
            ];
        } elseif ($this->parent instanceof MasterProductCategory) {
            if ($this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $icon          = [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('Master department')
                ];
                $subNavigation = $this->getMasterDepartmentSubNavigation($this->parent);
            } elseif ($this->parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $icon          = [
                    'icon'  => ['fal', 'fa-folder'],
                    'title' => __('Master sub-department')
                ];
                $subNavigation = $this->getMasterSubDepartmentSubNavigation($this->parent);
            }
            $masterShop = $this->parent->masterShop;
        }

        return Inertia::render(
            'Masters/MasterFamilies',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Departments'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $this->getActions($request),
                    'subNavigation' => $subNavigation,
                ],
                'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterShop, 'shops')),
                'data'        => MasterFamiliesResource::collection($masterFamilies),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getActions(ActionRequest $request): array
    {
        $actions = [];


        $createRoute = "grp.masters.master_shops.show.master_families.create";

        if ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $createRoute = "grp.masters.master_sub_departments.show.master_families.create";
        } elseif ($this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            match ($request->route()->getName()) {
                'grp.masters.master_departments.show.master_families.index' =>
                $createRoute = 'grp.masters.master_departments.show.master_families.create',
                'grp.masters.master_shops.show.master_departments.show.master_families.index' =>
                $createRoute = 'grp.masters.master_shops.show.master_departments.show.master_families.create',
                default => $createRoute
            };
        } elseif ($this->parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            match ($request->route()->getName()) {
                'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index' =>
                $createRoute = 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.create',
                'grp.masters.master_departments.show.master_families.index' =>
                $createRoute = 'grp.masters.master_departments.show.master_families.create',
                default => $createRoute
            };
        }

        $actions[] = [
            'type'    => 'button',
            'style'   => 'create',
            'tooltip' => __('master new family'),
            'label'   => __('master family'),
            'route'   => [
                'name'       => $createRoute,
                'parameters' => $request->route()->originalParameters()
            ]
        ];


        return $actions;
    }

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_families.index' =>
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_families.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_departments.show.master_families.index' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs(
                    $parent->group,
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
            'grp.masters.master_shops.show.master_sub_departments.master_families.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.index' =>
            array_merge(
                ShowMasterSubDepartment::make()->getBreadcrumbs($parent, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.show.master_families.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.create' =>
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
