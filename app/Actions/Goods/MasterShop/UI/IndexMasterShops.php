<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:12:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\MasterShop\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Http\Resources\Goods\Catalogue\MasterShopsResource;
use App\InertiaTable\InertiaTable;
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

class IndexMasterShops extends GrpAction
{
    use WithMasterCatalogueSubNavigation;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("goods.{$this->group->id}.view");
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_shops.code', $value)
                    ->orWhereStartWith('master_shops.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterShop::class);
        $queryBuilder->leftJoin('master_shop_stats', 'master_shops.id', '=', 'master_shop_stats.master_shop_id');

        $queryBuilder->where('master_shops.group_id', $group->id);

        return $queryBuilder
            ->defaultSort('master_shops.code')
            ->select(
                [
                    'master_shops.id',
                    'master_shops.code',
                    'master_shops.name',
                    'master_shops.slug',
                    'master_shops.type',
                    'master_shop_stats.number_current_shops as used_in',
                    'master_shop_stats.number_current_master_product_categories_type_department as departments',
                    'master_shop_stats.number_current_master_product_categories_type_family as families',
                    'master_shop_stats.number_current_master_assets_type_product as products',
                ]
            )
            ->allowedSorts(['code', 'name', 'departments', 'families', 'products', 'used_in'])
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
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master shops found"),
                    ],
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current shops with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'departments', label: __('departments'), tooltip: __('current master departments'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'families', label: __('families'), tooltip: __('current master families'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'products', label: __('products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterShops): AnonymousResourceCollection
    {
        return MasterShopsResource::collection($masterShops);
    }

    public function htmlResponse(LengthAwarePaginator $masterShops, ActionRequest $request): Response
    {
        $subNavigation = $this->getMasterCatalogueSubNavigation($this->group);

        return Inertia::render(
            'Goods/MasterShops',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
                'title'       => __('master shops'),
                'pageHead'    => [
                    'title'         => __('Master Shops'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-store-alt'],
                        'title' => __('master shops')
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterShopsResource::collection($masterShops),

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $suffix = null): array
    {
        $headCrumb = function (?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'  => [
                            'name'       => 'grp.goods.catalogue.shops.index',
                            'parameters' => []
                        ],
                        'label'  => __('Master shops'),
                        'icon'   => 'fal fa-bars',
                        'suffix' => $suffix
                    ],


                ],
            ];
        };

        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            $headCrumb(
                $suffix
            ),
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($group, $request);
    }

}
