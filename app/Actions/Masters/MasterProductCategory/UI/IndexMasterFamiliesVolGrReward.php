<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\MasterGoldRewardTabsEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
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

class IndexMasterFamiliesVolGrReward extends OrgAction
{
    use WithMasterCatalogueSubNavigation;

    private MasterShop $parent;

    protected function getElementGroups(MasterShop $parent): array
    {
        $activeMasterProducts       = $parent->stats->number_current_master_product_categories_type_family;
        $discontinuedMasterProducts = $parent->stats->number_master_product_categories_type_family - $parent->stats->number_current_master_product_categories_type_family;

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => [
                    'active'       => [
                        __('Active'),
                        $activeMasterProducts
                    ],
                    'discontinued' => [
                        __('Discontinued'),
                        $discontinuedMasterProducts
                    ],
                ],
                'engine' => function ($query, $elements) {
                    if (in_array('discontinued', $elements)) {
                        $query->where('master_product_categories.status', false);
                    } else {
                        $query->where('master_product_categories.status', true);
                    }
                }
            ],
        ];
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle(parent: $masterShop, prefix: MasterGoldRewardTabsEnum::WITH->value);
    }

    public function handle(MasterShop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.slug', $value)
                    ->orWhereStartWith('departments.name', $value)
                    ->orWhereStartWith('sub_departments.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);

        $queryBuilder
            ->where('master_product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('master_product_categories.master_shop_id', $parent->id)
            ->where('master_product_categories.has_gr_vol_discount', true)
            ->leftJoin(
                'master_product_category_stats',
                'master_product_categories.id',
                '=',
                'master_product_category_stats.master_product_category_id'
            )
            ->leftJoin(
                'master_product_categories as departments',
                'departments.id',
                '=',
                'master_product_categories.master_department_id'
            )
            ->leftJoin(
                'master_product_categories as sub_departments',
                'sub_departments.id',
                '=',
                'master_product_categories.master_sub_department_id'
            )
            ->leftJoin(
                'master_shops',
                'master_shops.id',
                '=',
                'master_product_categories.master_shop_id'
            )
            ->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $selects = [
            'master_product_categories.id',
            'master_product_categories.slug',
            'master_product_categories.code',
            'master_product_categories.name',
            'master_product_categories.status',
            'master_product_categories.description',
            'master_product_categories.created_at',
            'master_product_categories.updated_at',
            'master_product_categories.web_images',

            'master_product_category_stats.number_current_families as used_in',
            'master_product_category_stats.number_current_master_assets_type_product as products',

            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',

            'departments.slug as master_department_slug',
            'departments.code as master_department_code',
            'departments.name as master_department_name',

            'sub_departments.slug as master_sub_department_slug',
            'sub_departments.code as master_sub_department_code',
            'sub_departments.name as master_sub_department_name',

            'currencies.code as currency_code',
        ];

        $queryBuilder->select($selects);

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts([
                'code',
                'name',
                'used_in',
                'products',
                'master_department_code',
                'master_sub_department_code',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop $parent, ?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __('No master families found'),
                        'count' => $parent->masterProductCategories()
                            ->where('master_product_categories.type', ProductCategoryTypeEnum::FAMILY)
                            ->where('master_product_categories.has_gr_vol_discount', true)
                            ->count(),
                    ],
                )
                ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'master_department_code', label: __('M. Department'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'master_sub_department_code', label: __('M. Sub-department'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current families with this master'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'products', label: __('Products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterFamilies): AnonymousResourceCollection
    {
        return MasterFamiliesResource::collection($masterFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $title         = __('Master Families Has GR/VOL Reward');
        $subNavigation = $this->getMasterShopNavigation($this->parent);

        return Inertia::render('Masters/MasterFamiliesGR', [
            'breadcrumbs' => $this->getBreadcrumbs(
                $this->parent,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => [],
            'title'       => __('Master Families'),
            'pageHead'    => [
                'title'         => $title,
                'icon'          => [
                    'icon'  => ['fal', 'fa-medal'],
                    'title' => $title,
                ],
                'model'         => '',
                'afterTitle'    => null,
                'iconRight'     => [
                    'icon' => 'fal fa-folder-tree',
                ],
                'actions'       => [],
                'subNavigation' => $subNavigation,
            ],
            'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($this->parent, 'shops')),
            'tabs'     => [
                'current'    => MasterGoldRewardTabsEnum::WITH->value,
                'navigation' => [
                    MasterGoldRewardTabsEnum::WITH->value => MasterGoldRewardTabsEnum::WITH->blueprint(),
                ],
            ],
            MasterGoldRewardTabsEnum::WITH->value => fn () => MasterFamiliesResource::collection($masterFamilies),
        ])->table($this->tableStructure($this->parent, prefix: MasterGoldRewardTabsEnum::WITH->value));
    }

    public function getBreadcrumbs(MasterShop $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowMasterShop::make()->getBreadcrumbs($parent),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('GR/VOL Reward'),
                        'icon'  => 'fal fa-medal'
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
