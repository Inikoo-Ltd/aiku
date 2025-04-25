<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Http\Resources\Masters\MasterProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterAssets extends GrpAction
{
    use WithMasterCatalogueSubNavigation;

    private Group|MasterShop|MasterProductCategory $parent;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("masters.{$this->group->id}.view");
    }

    public function handle(Group|MasterShop|MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereStartWith('master_assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class);
        $queryBuilder->select(
            [
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.slug',
                'master_assets.status',
                'master_assets.price',
            ]
        );
        if ($parent instanceof Group) {
            $queryBuilder->where('master_assets.group_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_assets.master_shop_id');
            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_assets.master_department_id');
            $queryBuilder->leftJoin('master_product_categories as families', 'families.id', 'master_assets.master_family_id');

            $queryBuilder->addSelect([
                'families.slug as master_family_slug',
                'families.code as master_family_code',
                'families.name as master_family_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name',
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
            ]);
        } elseif ($parent instanceof MasterShop) {
            $queryBuilder->where('master_assets.master_shop_id', $parent->id);
            $queryBuilder->leftJoin('master_product_categories as departments', 'departments.id', 'master_assets.master_department_id');
            $queryBuilder->leftJoin('master_product_categories as families', 'families.id', 'master_assets.master_family_id');
            $queryBuilder->addSelect([
                'families.slug as master_family_slug',
                'families.code as master_family_code',
                'families.name as master_family_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name'
            ]);
        } else {
            abort(419);
        }

        return $queryBuilder
            ->defaultSort('master_assets.code')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master shops found"),
                    ],
                );

            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('Shop'), sortable: true);
                $table->column('master_department_code', __('Department'), sortable: true);
                $table->column('master_family_code', __('Family'), sortable: true);
            }

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $title = __('master products');

        if ($this->parent instanceof Group) {
            $subNavigation = null;
            $model         = '';
            $icon          = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => $title
            ];
            $afterTitle    = [
                'label' => __('In group')
            ];
            $iconRight     = [
                'icon' => 'fal fa-city',
            ];
        } elseif ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $title         = $this->parent->name;
            $model         = '';
            $icon          = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('master shop')
            ];
            $afterTitle    = [
                'label' => __('Products')
            ];
            $iconRight     = [
                'icon' => 'fal fa-cube',
            ];
        }

        return Inertia::render(
            'Masters/MasterProducts',
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
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterProductsResource::collection($masterAssets),

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
                        'label' => __('Master products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.products.index' =>
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => []
                    ],
                    $suffix
                ),
            ),
            'grp.masters.shops.show.products.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => [
                            'masterShop' => $this->parent->slug
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request);

        return $this->handle($group, $request);
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterShop;
        $this->initialisation($group, $request);

        return $this->handle($masterShop, $request);
    }

}
