<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\MasterProductCategory\UI;

use App\Actions\Catalogue\Collection\UI\ShowCollection;
use App\Actions\Goods\MasterShop\UI\ShowMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Goods\Catalogue\MasterFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\MasterProductCategory;
use App\Models\Goods\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterFamilies extends GrpAction
{
    use WithMasterCatalogueSubNavigation;

    private Group|MasterShop|MasterProductCategory $parent;


    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request);

        return $this->handle(parent: $masterShop);
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
            ])
            ->where('master_product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->allowedSorts(['code', 'name'])
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
                        'title' => __("No master families found"),
                    ],
                );

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterFamilies): AnonymousResourceCollection
    {
        return MasterFamiliesResource::collection($masterFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
        }
        $title      = $this->parent->name;
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('master shop')
        ];
        $afterTitle = [
            'label' => __('Families')
        ];
        $iconRight  = [
            'icon' => 'fal fa-folder-tree',
        ];

        return Inertia::render(
            'Goods/MasterFamilies',
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
                    // 'actions'       => [
                    //     $this->canEdit && $request->route()->getName() == 'grp.org.shops.show.catalogue.departments.index' ? [
                    //         'type'    => 'button',
                    //         'style'   => 'create',
                    //         'tooltip' => __('new department'),
                    //         'label'   => __('department'),
                    //         'route'   => [
                    //             'name'       => 'grp.org.shops.show.catalogue.departments.create',
                    //             'parameters' => $request->route()->originalParameters()
                    //         ]
                    //     ] : false,
                    //     class_basename($this->parent) == 'Collection' ? [
                    //         'type'     => 'button',
                    //         'style'    => 'secondary',
                    //         'key'      => 'attach-department',
                    //         'icon'     => 'fal fa-plus',
                    //         'tooltip'  => __('Attach department to this collection'),
                    //         'label'    => __('Attach department'),
                    //     ] : false
                    // ],
                    'subNavigation' => $subNavigation,
                ],
                // 'routes'      => $routes,
                'data'        => MasterFamiliesResource::collection($masterFamilies),
            ]
        )->table($this->tableStructure());
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
            'grp.goods.catalogue.shops.show.families.index' =>
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
