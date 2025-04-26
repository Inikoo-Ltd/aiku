<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Collection\UI\ShowCollection;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
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
        $group        = $this->parent;
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $group);
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
            ->allowedSorts(['code', 'name'])
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
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current shops with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'products', label: __('products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true);

        };
    }

    public function jsonResponse(LengthAwarePaginator $masterFamilies): AnonymousResourceCollection
    {
        return MasterFamiliesResource::collection($masterFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title         = $this->parent->name;
        $model         = '';
        $icon          = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('master shop')
        ];
        $afterTitle    = [
            'label' => __('Families')
        ];
        $iconRight     = [
            'icon' => 'fal fa-folder-tree',
        ];
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
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
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterFamiliesResource::collection($masterFamilies),
            ]
        )->table($this->tableStructure($this->parent));
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
            'grp.masters.families.index' =>
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
            'grp.masters.shops.show.families.index' =>
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
            'grp.org.shops.show.catalogue.collections.departments.index' =>
            array_merge(
                ShowCollection::make()->getBreadcrumbs('grp.org.shops.show.catalogue.collections.show', $routeParameters),
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
