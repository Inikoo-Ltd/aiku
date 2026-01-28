<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterDepartmentNavigation;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterFamilyNavigation;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\MasterProductsTabsEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;

class IndexMasterProducts extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;
    use WithMastersAuthorisation;

    private Group|MasterShop|MasterProductCategory $parent;


    public function getElementGroups(Group|MasterShop|MasterProductCategory $parent): array
    {
        $activeMasterProducts       = 0;
        $discontinuedMasterProducts = 0;

        if ($parent instanceof MasterShop || $parent instanceof MasterProductCategory) {
            $activeMasterProducts       = $parent->stats->number_current_master_assets;
            $discontinuedMasterProducts = $parent->stats->number_master_assets - $parent->stats->number_current_master_assets;
        }


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
                        $query->where('master_assets.status', false);
                    } else {
                        $query->where('master_assets.status', true);
                    }
                }

            ],

        ];
    }

    public function handle(Group|MasterShop|MasterProductCategory $parent, $prefix = null, $filterInVariant = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(MasterAsset::class)
            // stats
            ->leftJoin(
                'master_asset_stats',
                'master_assets.id',
                '=',
                'master_asset_stats.master_asset_id'
            )
            // group & currency
            ->leftJoin('groups', 'master_assets.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id')

            // categories (ALWAYS JOIN)
            ->leftJoin(
                'master_product_categories as departments',
                'departments.id',
                '=',
                'master_assets.master_department_id'
            )
            ->leftJoin(
                'master_product_categories as sub_departments',
                'sub_departments.id',
                '=',
                'master_assets.master_sub_department_id'
            )
            ->leftJoin(
                'master_product_categories as families',
                'families.id',
                '=',
                'master_assets.master_family_id'
            )
            ->leftJoin(
                'master_variants as master_variant',
                'master_variant.id',
                '=',
                'master_assets.master_variant_id'
            );

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $selects = [
            'master_assets.id',
            'master_assets.code',
            'master_assets.name',
            'master_assets.slug',
            'master_assets.status',
            'master_assets.price',
            'master_assets.unit',
            'master_assets.units',
            'master_assets.rrp',
            'master_assets.web_images',

            'master_asset_stats.number_current_assets as used_in',
            'currencies.code as currency_code',

            // department
            'departments.slug as master_department_slug',
            'departments.code as master_department_code',
            'departments.name as master_department_name',

            // sub department
            'sub_departments.slug as master_sub_department_slug',
            'sub_departments.code as master_sub_department_code',
            'sub_departments.name as master_sub_department_name',

            // family
            'families.slug as master_family_slug',
            'families.code as master_family_code',
            'families.name as master_family_name',

            //variants
            'master_variant.slug as variant_slug',
            'master_variant.slug as variant_code',
            'master_assets.is_variant_leader as is_variant_leader',
        ];

        if ($prefix === MasterProductsTabsEnum::SALES->value) {
            // Use reusable time series aggregation method
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'master_asset_time_series',
                timeSeriesRecordsTable: 'master_asset_time_series_records',
                foreignKey: 'master_asset_id',
                aggregateColumns: [
                    'sales_grp_currency' => 'sales',
                    'invoices'           => 'invoices'
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );

            $selects[] = $timeSeriesData['selectRaw']['sales'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['sales_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }

        $queryBuilder->select($selects);

        // PARENT FILTER ONLY
        if ($parent instanceof Group) {
            $queryBuilder
                ->where('master_assets.group_id', $parent->id)
                ->leftJoin(
                    'master_shops',
                    'master_shops.id',
                    '=',
                    'master_assets.master_shop_id'
                )
                ->addSelect([
                    'master_shops.slug as master_shop_slug',
                    'master_shops.code as master_shop_code',
                    'master_shops.name as master_shop_name',
                ]);
        } elseif ($parent instanceof MasterShop) {
            $queryBuilder->where(
                'master_assets.master_shop_id',
                $parent->id
            );
        } elseif ($parent instanceof MasterProductCategory) {
            match ($parent->type) {
                MasterProductCategoryTypeEnum::FAMILY =>
                $queryBuilder->where(
                    'master_assets.master_family_id',
                    $parent->id
                ),

                MasterProductCategoryTypeEnum::DEPARTMENT =>
                $queryBuilder->where(
                    'master_assets.master_department_id',
                    $parent->id
                ),

                default =>
                $queryBuilder->where(
                    'master_assets.master_sub_department_id',
                    $parent->id
                ),
            };
        } else {
            abort(419);
        }

        if ($filterInVariant) {
            if ($filterInVariant == 'none') {
                $queryBuilder->whereNull('master_assets.master_variant_id');
            } else {
                $queryBuilder->whereNull('master_assets.master_variant_id')->orWhere('master_assets.master_variant_id', $filterInVariant);
            }
            $queryBuilder->where('master_assets.status', true); // Only fetch MasterAssets that are used as a material for Variant
        } elseif ($parent instanceof MasterProductCategory && $parent->type != MasterProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('master_assets.is_main', true);
        }

        return $queryBuilder
            ->defaultSort('master_assets.code')
            ->allowedSorts(['code', 'name', 'used_in', 'sales', 'invoices'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null, $sales = false): \Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($sales) {
                $table->betweenDates(['date']);
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master shops found"),
                        'count' => $parent->stats->number_master_assets,
                    ],
                );

            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('Shop'), sortable: true);
                $table->column('master_department_code', __('Department'), sortable: true);
                $table->column('master_family_code', __('Family'), sortable: true);
            }

            if ($sales) {
                $table
                    ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table
                    ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                    ->column(key: 'status_icon', label: '', type: 'icon')
                    ->column(key: 'code', label: __('Code'), sortable: true);

                if ($parent instanceof MasterProductCategory && $parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                    $table->column(key: 'variant_slug', label: 'Variant');
                }

                $table
                    ->column(key: 'name', label: __('Name'), sortable: true)
                    ->column(key: 'unit', label: __('Unit'), sortable: true)
                    ->column(key: 'master_department_code', label: __('M. Department'), sortable: true)
                    ->column(key: 'master_sub_department_code', label: __('M. Sub-department'), sortable: true)
                    ->column(key: 'master_family_code', label: __('M. Family'), sortable: true)
                    ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current products with this master'), sortable: true)
                    ->column(key: 'actions', label: __('Actions'), sortable: true)
                    ->defaultSort('code');
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $title = __('Master products');

        $icon            = '';
        $model           = null;
        $afterTitle      = null;
        $iconRight       = null;
        $subNavigation   = null;
        $familyId        = null;
        $shopsData       = null;
        $modelNavigation = [];
        if ($this->parent instanceof Group) {
            $model      = '';
            $icon       = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => $title
            ];
            $afterTitle = [
                'label' => __('In group')
            ];
            $iconRight  = [
                'icon' => 'fal fa-city',
            ];
        } elseif ($this->parent instanceof MasterShop) {
            $masterShop    = $this->parent;
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $title         = $this->parent->name;
            $model         = '';
            $icon          = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('Master shop')
            ];
            $afterTitle    = [
                'label' => __('Master Products')
            ];
            $iconRight     = [
                'icon' => 'fal fa-cube',
            ];
            $shopsData     = OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterShop, 'shops'));
        } elseif ($this->parent instanceof MasterProductCategory) {
            $masterShop = $this->parent->masterShop;
            if ($this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $subNavigation   = $this->getMasterDepartmentSubNavigation($this->parent);
                $modelNavigation = GetMasterDepartmentNavigation::run($this->parent, $request);
            } elseif ($this->parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                $familyId        = $this->parent->id;
                $subNavigation   = $this->getMasterFamilySubNavigation($this->parent);
                $title           = $this->parent->name;
                $model           = '';
                $icon            = [
                    'icon'  => ['fal', 'fa-store-alt'],
                    'title' => __('Master shop')
                ];
                $afterTitle      = [
                    'label' => __('Master Products')
                ];
                $iconRight       = [
                    'icon' => 'fal fa-cube',
                ];
                $modelNavigation = GetMasterFamilyNavigation::run($this->parent, $request);
            }
            $shopsData = OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterShop, 'shops'));
        }

        $isFamily = $this->parent instanceof MasterProductCategory && $this->parent->type == MasterProductCategoryTypeEnum::FAMILY;

        return Inertia::render(
            'Masters/MasterProducts',
            [
                'breadcrumbs'             => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'              => $modelNavigation,
                'title'                   => $title,
                'familyId'                => $familyId,
                'currency'                => $this->parent->group->currency->code,
                'storeProductRoute'       => $isFamily ? [
                    'name'       => 'grp.models.master_family.store-assets',
                    'parameters' => [
                        'masterFamily' => $this->parent->id,
                    ]
                ] : [],
                'pageHead'                => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $isFamily ? [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add a master product to this family'),
                            'label'   => __('Master product'),
                        ],
                    ] : [],
                ],
                'variantSlugs'            => $isFamily ? $masterAssets->pluck('variant_slug')->filter()->unique()->mapWithKeys(fn ($slug) => [$slug => productCodeToHexCode($slug)]) : [],
                'masterProductCategoryId' => $this->parent->id,
                'editable_table'          => false,
                'shopsData'               => $shopsData,

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterProductsTabsEnum::navigation(),
                ],

                MasterProductsTabsEnum::INDEX->value => $this->tab == MasterProductsTabsEnum::INDEX->value ?
                    fn () => MasterProductsResource::collection($masterAssets)
                    : Inertia::lazy(fn () => MasterProductsResource::collection(IndexMasterProducts::run($this->parent, prefix: MasterProductsTabsEnum::INDEX->value))),

                MasterProductsTabsEnum::SALES->value => $this->tab == MasterProductsTabsEnum::SALES->value ?
                    fn () => MasterProductsResource::collection(IndexMasterProducts::run($this->parent, prefix: MasterProductsTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => MasterProductsResource::collection(IndexMasterProducts::run($this->parent, prefix: MasterProductsTabsEnum::SALES->value))),

            ]
        )->table($this->tableStructure($this->parent, prefix: MasterProductsTabsEnum::INDEX->value))
        ->table($this->tableStructure($this->parent, prefix: MasterProductsTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
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
            'grp.masters.master_products.index' =>
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
            'grp.masters.master_shops.show.master_products.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => Arr::only($routeParameters, ['masterShop']),
                    ],
                    $suffix
                ),
            ),
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'grp.masters.master_shops.show.master_families.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index',
            'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index' =>
            array_merge(
                ShowMasterFamily::make()->getBreadcrumbs($parent, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                ),
            ),
            'grp.masters.master_shops.show.master_departments.show.master_products.index' =>
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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($group, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterShop;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterShop, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $group = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterFamily, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $group = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterFamily, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $group = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterFamily, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $group = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterFamily, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShopFilterInVariant(MasterShop $masterShop, MasterProductCategory $masterFamily, string $filterInVariant, ActionRequest $request): LengthAwarePaginator
    {
        $group = group();

        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle(parent: $masterFamily, prefix: MasterProductsTabsEnum::INDEX->value, filterInVariant: $filterInVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $this->initialisation($masterDepartment->group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterDepartment, prefix: MasterProductsTabsEnum::INDEX->value);
    }

}
