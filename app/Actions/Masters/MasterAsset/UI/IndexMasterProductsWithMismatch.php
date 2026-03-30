<?php

/*
 * author Louis Perez
 * created on 09-03-2026-10h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterDepartmentNavigation;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterFamilyNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
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

class IndexMasterProductsWithMismatch extends GrpAction
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
            $activeMasterProducts       = $parent->stats->number_mismatched_master_products_active;
            $discontinuedMasterProducts = $parent->stats->number_mismatched_master_products_inactive;
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
                        __('Discontinued/Not for sale'),
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

            // family
            'families.slug as master_family_slug',
            'families.code as master_family_code',
            'families.name as master_family_name',

            //variants
            'master_variant.slug as variant_slug',
            'master_variant.slug as variant_code',
            'master_assets.is_variant_leader as is_variant_leader',
        ];

        $queryBuilder
            ->with('tradeUnits.stocks');

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

        if ($parent instanceof MasterProductCategory && $parent->type != MasterProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('master_assets.is_main', true);
        }

        $queryBuilder->where('master_assets.mismatch_detected', true);
        $queryBuilder->addSelect('master_assets.mismatch_detected');

        return $queryBuilder
            ->defaultSort('master_assets.code')
            ->allowedSorts(['code', 'name', 'used_in', 'sales_grp_currency_external', 'invoices', 'dropshippers', 'listings', 'sold', 'variant_slug'])
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

            $table
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'status_icon', label: '', type: 'icon')
                ->column(key: 'code', label: __('Code'), sortable: true);

            $table
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'unit', label: __('Unit'), sortable: true)
                ->column(key: 'master_family_code', label: __('M. Family'), sortable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current products with this master'), sortable: true)
                ->defaultSort('code');
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
                'masterProductCategoryId' => $this->parent->id,
                'editable_table'          => false,
                'shopsData'               => $shopsData,
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterProductsTabsEnum::navigationExcept([MasterProductsTabsEnum::SALES]),
                ],
                MasterProductsTabsEnum::INDEX->value => $this->tab == MasterProductsTabsEnum::INDEX->value ?
                    fn () => MasterProductsResource::collection($masterAssets)
                    : Inertia::lazy(fn () => MasterProductsResource::collection(IndexMasterProducts::run($this->parent, prefix: MasterProductsTabsEnum::INDEX->value))),
            ]
        )->table($this->tableStructure($this->parent, prefix: MasterProductsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => trim('('.__('Has mismatch').') '.$suffix)
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_products.mismatch_detected.index' =>
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
            default => []
        };
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterShop;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterShop, prefix: MasterProductsTabsEnum::INDEX->value);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterFamily;
        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::values());

        return $this->handle($masterFamily, prefix: MasterProductsTabsEnum::INDEX->value);
    }
}
