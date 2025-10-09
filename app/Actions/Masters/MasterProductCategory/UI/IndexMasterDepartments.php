<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterDepartmentsResource;
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

class IndexMasterDepartments extends OrgAction
{
    use WithMasterCatalogueSubNavigation;

    private MasterShop|Group $parent;

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $masterShop);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $group);
    }

    public function handle(Group|MasterShop $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('master_product_categories.type', ProductCategoryTypeEnum::DEPARTMENT);

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
            'master_product_category_stats.number_current_departments as used_in',
            'master_product_category_stats.number_current_master_product_categories_type_family as families',
            'master_product_category_stats.number_current_master_assets_type_product as products',
            'master_product_category_stats.number_current_master_product_categories_type_sub_department as sub_departments',
            'master_product_category_stats.number_collections_state_active as collections',
        ]);
        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
        } else {
            $queryBuilder->where('master_product_categories.group_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_product_categories.master_shop_id');
            $queryBuilder->addSelect([
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
            ]);
        }

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts(['code', 'name','used_in','sub_departments','collections','families','products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop $parent, ?array $modelOperations = null, $prefix = null): Closure
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
                        'title' => __("No master departments found"),
                    ],
                );


            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('M. Shop'), sortable: true);
            }

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current shops with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sub_departments', label: __('M. Sub-departments'), tooltip: __('current sub departments'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'collections', label: __('M. Collections'), tooltip: __('current collections'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'families', label: __('M. Families'), tooltip: __('current master families'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'products', label: __('M. Products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterDepartments): AnonymousResourceCollection
    {
        return MasterDepartmentsResource::collection($masterDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $masterDepartments, ActionRequest $request): Response
    {
        $model = '';
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $title         = $this->parent->name;

            $icon       = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('Master shop')
            ];
            $afterTitle = [
                'label' => __('Master Departments')
            ];
            $iconRight  = [
                'icon' => 'fal fa-folder-tree',
            ];
        } else {
            $title         = __('Master departments');
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => $title
            ];
            $afterTitle    = [
                'label' => __('In group')
            ];
            $iconRight     = [
                'icon' => 'fal fa-city',
            ];
            $subNavigation = null;
        }

        $actions = [];
        if ($request->route()->getName() == 'grp.masters.master_shops.show.master_departments.index') {
            $actions = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New master department'),
                    'label'   => __('master department'),
                    'route'   => match ($this->parent::class) {
                        MasterProductCategory::class => [
                            'name'       => 'grp.masters.master_departments.create',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        default => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.create',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    }
                ],
            ];
        }


        return Inertia::render(
            'Masters/MasterDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterDepartmentsResource::collection($masterDepartments),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(MasterShop|Group $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_departments.index' =>
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
            'grp.masters.master_shops.show.master_departments.index' =>
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


            default => []
        };
    }
}
