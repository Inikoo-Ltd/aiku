<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Enums\UI\Catalogue\MasterGoldRewardTabsEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterFamiliesGR extends OrgAction
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

                'default' => 'active',

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
        $this->initialisationFromGroup($masterShop->group, $request)->withTab(MasterGoldRewardTabsEnum::values());

        $currentTab = $this->tab ?? MasterGoldRewardTabsEnum::WITH->value;

        if ($currentTab === MasterGoldRewardTabsEnum::NOT_FOLLOW_MASTER->value) {
            return $this->handleNotFollowMaster(parent: $masterShop, prefix: $currentTab);
        }

        $isGR = $currentTab === MasterGoldRewardTabsEnum::WITH->value;

        return $this->handle(parent: $masterShop, prefix: $currentTab, isGR: $isGR);
    }

    public function handle(MasterShop $parent, $prefix = null, $isGR = null): LengthAwarePaginator
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

            // Stats
            ->leftJoin(
                'master_product_category_stats',
                'master_product_categories.id',
                '=',
                'master_product_category_stats.master_product_category_id'
            )

            // Department
            ->leftJoin(
                'master_product_categories as departments',
                'departments.id',
                '=',
                'master_product_categories.master_department_id'
            )

            // Sub Department
            ->leftJoin(
                'master_product_categories as sub_departments',
                'sub_departments.id',
                '=',
                'master_product_categories.master_sub_department_id'
            )

            // Shop
            ->leftJoin(
                'master_shops',
                'master_shops.id',
                '=',
                'master_product_categories.master_shop_id'
            )
            ->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');

        // Element Groups (Filters)
        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null,
            );
        }

        if ($isGR !== null) {
            if ($isGR) {
                $queryBuilder->where('master_product_categories.has_gr_vol_discount', true);
            } else {
                $queryBuilder->where('master_product_categories.has_gr_vol_discount', false);
            }
        }

        $selects = [
            // family
            'master_product_categories.id',
            'master_product_categories.slug',
            'master_product_categories.code',
            'master_product_categories.name',
            'master_product_categories.status',
            'master_product_categories.description',
            'master_product_categories.created_at',
            'master_product_categories.updated_at',
            'master_product_categories.web_images',
            'master_product_categories.gr_vol_discount_percentage',
            'master_product_categories.gr_vol_discount_quantity',

            // Stats
            'master_product_category_stats.number_current_families as used_in',
            'master_product_category_stats.number_current_master_assets_type_product as products',

            // Shop
            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',

            // Department
            'departments.slug as master_department_slug',
            'departments.code as master_department_code',
            'departments.name as master_department_name',

            // Sub Department
            'sub_departments.slug as master_sub_department_slug',
            'sub_departments.code as master_sub_department_code',
            'sub_departments.name as master_sub_department_name',
            'currencies.code as currency_code',
        ];

        $queryBuilder->select($selects);

        $queryBuilder
            ->selectSub(function ($query) {
                $query->from('product_categories')
                    ->selectRaw('count(*)')
                    ->whereColumn('product_categories.master_product_category_id', 'master_product_categories.id')
                    ->where('product_categories.has_gr_vol_discount', true)
                    ->where('product_categories.follow_master_gr', true);
            }, 'number_following_master_gr')
            ->selectSub(function ($query) {
                $query->from('product_categories')
                    ->selectRaw('count(*)')
                    ->whereColumn('product_categories.master_product_category_id', 'master_product_categories.id')
                    ->where('product_categories.has_gr_vol_discount', true)
                    ->where('product_categories.follow_master_gr', false);
            }, 'number_not_following_master_gr');

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts([
                'code',
                'name',
                'used_in',
                'products',
                'master_department_code',
                'master_sub_department_code',
                'number_following_master_gr',
                'number_not_following_master_gr',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop $parent, ?array $modelOperations = null, $prefix = null): Closure
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
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null,
                );
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master families found"),
                        'count' => $parent->stats->number_current_master_product_categories_type_family
                    ],
                )
                ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'gr_detail', label: __('GR detail'), tooltip: __('Percentage & trigger quantity'), canBeHidden: false)
                ->column(key: 'number_following_master_gr', label: __('Following master'), tooltip: __('Families following master GR'), canBeHidden: false, sortable: true)
                ->column(key: 'number_not_following_master_gr', label: __('Not following master'), tooltip: __('Families not following master GR'), canBeHidden: false, sortable: true);
        };
    }

    public function handleNotFollowMaster(MasterShop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.has_gr_vol_discount', true)
            ->where('product_categories.follow_master_gr', false)
            ->join(
                'master_product_categories',
                'master_product_categories.id',
                '=',
                'product_categories.master_product_category_id'
            )
            ->where('master_product_categories.master_shop_id', $parent->id)
            ->leftJoin('product_category_stats', 'product_categories.id', '=', 'product_category_stats.product_category_id')
            ->leftJoin('shops', 'product_categories.shop_id', '=', 'shops.id')
            ->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id')
            ->with(['getGROffer.offerAllowances']);

        $queryBuilder->select([
            'product_categories.id',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.state',
            'product_categories.image_id',
            'product_categories.web_images',
            'product_categories.master_product_category_id',
            'product_category_stats.number_current_products',
            'shops.slug as shop_slug',
            'shops.code as shop_code',
            'shops.name as shop_name',
            'organisations.slug as organisation_slug',
        ]);

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->allowedSorts(['code', 'name', 'shop_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function notFollowMasterTableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No families found'),
                    ],
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'shop_code', label: __('Shop'), tooltip: __('Shop code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'gr_detail', label: __('GR detail'), tooltip: __('Percentage & trigger quantity'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterFamilies): AnonymousResourceCollection
    {
        return MasterFamiliesResource::collection($masterFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $navigation    = MasterGoldRewardTabsEnum::navigation();
        $subNavigation = $this->getMasterShopNavigation($this->parent);
        $title         = __('Gold Reward');
        $icon          = [
            'icon'  => ['fal', 'fa-tags'],
            'title' => __('Gold Reward')
        ];

        $baseData = [
            'breadcrumbs' => $this->getBreadcrumbs(
                $this->parent,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => [],
            'title'       => __('Master Families'),
            'pageHead'    => [
                'title'         => $title,
                'icon'          => $icon,
                'model'         => '',
                'afterTitle'    => null,
                'iconRight'     => [
                    'icon' => 'fal fa-folder-tree',
                ],
                'actions'       => [],
                'subNavigation' => $subNavigation,
            ],
            'shopsData'   => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($this->parent, 'shops')),
        ];

        $baseData['tabs'] = [
            'current'    => $this->tab,
            'navigation' => $navigation,
        ];

        $baseData[MasterGoldRewardTabsEnum::WITH->value] = $this->tab === MasterGoldRewardTabsEnum::WITH->value
            ? fn () => MasterFamiliesResource::collection($masterFamilies)
            : Inertia::lazy(fn () => MasterFamiliesResource::collection(
                $this->handle(parent: $this->parent, prefix: MasterGoldRewardTabsEnum::WITH->value, isGR: true)
            ));

        $baseData[MasterGoldRewardTabsEnum::WITHOUT->value] = $this->tab === MasterGoldRewardTabsEnum::WITHOUT->value
            ? fn () => MasterFamiliesResource::collection($masterFamilies)
            : Inertia::lazy(fn () => MasterFamiliesResource::collection(
                $this->handle(parent: $this->parent, prefix: MasterGoldRewardTabsEnum::WITHOUT->value, isGR: false)
            ));

        $baseData[MasterGoldRewardTabsEnum::NOT_FOLLOW_MASTER->value] = $this->tab === MasterGoldRewardTabsEnum::NOT_FOLLOW_MASTER->value
            ? fn () => FamiliesResource::collection($masterFamilies)
            : Inertia::lazy(fn () => FamiliesResource::collection(
                $this->handleNotFollowMaster(parent: $this->parent, prefix: MasterGoldRewardTabsEnum::NOT_FOLLOW_MASTER->value)
            ));

        return Inertia::render('Masters/MasterFamiliesGR', $baseData)
            ->table($this->tableStructure($this->parent, prefix: MasterGoldRewardTabsEnum::WITH->value))
            ->table($this->tableStructure($this->parent, prefix: MasterGoldRewardTabsEnum::WITHOUT->value))
            ->table($this->notFollowMasterTableStructure(prefix: MasterGoldRewardTabsEnum::NOT_FOLLOW_MASTER->value));
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
                        'label' => __('Gold Reward'),
                        'icon'  => 'fal fa-tags'
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
