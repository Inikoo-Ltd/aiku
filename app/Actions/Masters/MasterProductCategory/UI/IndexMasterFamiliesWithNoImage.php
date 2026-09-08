<?php

/*
 * Author Louis Perez
 * Created on 05-08-2026-13h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\MasterProductCategoryTabsEnum;
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

class IndexMasterFamiliesWithNoImage extends OrgAction
{
    use WithMastersAuthorisation;
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterSubDepartmentSubNavigation;

    private MasterShop $parent;

    protected function getElementGroups(MasterShop $parent): array
    {
        $activeMasterProducts       = $parent->stats->number_mismatched_master_families_active;
        $discontinuedMasterProducts = $parent->stats->number_mismatched_master_families_inactive;

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
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterShop, prefix: MasterProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(MasterShop $parent, string $parentType = 'department', $prefix = null): LengthAwarePaginator
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
                prefix: $prefix
            );
        }

        // Parent Filter (ONLY affects data scope)
        $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);

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

            // Stats
            'master_product_category_stats.number_current_families as used_in',
            'master_product_category_stats.number_current_master_assets as products',

            // Shop
            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',

            'currencies.code as currency_code',
        ];

        $queryBuilder->select($selects);

        $queryBuilder->whereNull('master_product_categories.image_id');

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts([
                'code',
                'name',
                'used_in',
                'products',
                'master_department_code',
                'master_sub_department_code',
                'sales_grp_currency_external',
                'invoices',
                'dropshippers',
                'listings',
                'sold',
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
                    elements: $elementGroup['elements']
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
                );

            $table
                ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
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
        $navigation      = MasterProductCategoryTabsEnum::navigationExcept([MasterProductCategoryTabsEnum::SALES]);
        $masterShop      = null;
        $subNavigation   = null;
        $modelNavigation = [];
        $title           = $this->parent->name;
        $model           = '';
        $icon            = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('Master shop')
        ];
        $afterTitle      = [
            'label' => __('Master Families'). ' ('.__('Missing Image').')'
        ];
        $iconRight       = [
            'icon' => 'fal fa-folder-tree',
        ];
        $parentType      = 'department';

        $subNavigation = $this->getMasterShopNavigation($this->parent);
        $masterShop    = $this->parent;
        $baseData = [
            'breadcrumbs' => $this->getBreadcrumbs(
                $this->parent,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => $modelNavigation,
            'title'       => __('Master Families (Missing Image)'),
            'pageHead'    => [
                'title'         => $title,
                'is_negative'   => true,
                'icon'          => $icon,
                'model'         => $model,
                'afterTitle'    => $afterTitle,
                'iconRight'     => $iconRight,
                'actions'       => $this->getActions(),
                'subNavigation' => $subNavigation,
            ],
            'shopsData'   => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($masterShop, 'shops')),
        ];

        $baseData['storeRoute'] = match ($this->parent::class) {
            MasterShop::class => [
                'name'       => 'grp.models.master_shops.master_family.store',
                'parameters' => [
                    'masterShop' => $this->parent->id
                ]
            ],
            MasterProductCategory::class => $this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT
                ? [
                    'name'       => 'grp.models.master_family.store',
                    'parameters' => [
                        'masterDepartment' => $this->parent->id
                    ]
                ]
                : [
                    'name'       => 'grp.models.master-sub-department.master_family.store',
                    'parameters' => [
                        'masterSubDepartment' => $this->parent->id
                    ]
                ],
            default => null
        };

        $baseData['tabs'] = [
            'current'    => $this->tab,
            'navigation' => $navigation,
        ];

        $baseData[MasterProductCategoryTabsEnum::INDEX->value] = $this->tab == MasterProductCategoryTabsEnum::INDEX->value ?
            fn () => MasterFamiliesResource::collection($masterFamilies)
            : Inertia::optional(fn () => MasterFamiliesResource::collection(IndexMasterFamilies::run($this->parent, parentType: $parentType, prefix: MasterProductCategoryTabsEnum::INDEX->value)));

        return Inertia::render('Masters/MasterFamilies', $baseData)
            ->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::INDEX->value));
    }

    public function getActions(): array
    {
        $actions = [];

        if (!$this->canEdit) {
            return $actions;
        }

        if ($this->parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT || $this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $actions[] = [
                'type'    => 'button',
                'key'     => 'add-master-family',
                'style'   => 'create',
                'tooltip' => __('Create master family'),
                'label'   => __('Master Family'),
            ];
        }


        return $actions;
    }

    public function getBreadcrumbs(MasterShop $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
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
                    'suffix' => trim('('.__('Missing Image').') '.$suffix)
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_family.missing_image.index' =>
                array_merge(
                    ShowMasterShop::make()->getBreadcrumbs($parent),
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
