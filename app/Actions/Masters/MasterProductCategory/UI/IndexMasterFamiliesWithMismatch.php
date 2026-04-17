<?php

/*
 * author Louis Perez
 * created on 09-03-2026-12h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\MasterProductCategoryTabsEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
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

class IndexMasterFamiliesWithMismatch extends OrgAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterSubDepartmentSubNavigation;

    private Group|MasterShop|MasterProductCategory $parent;

    protected function getElementGroups(Group|MasterShop|MasterProductCategory $parent): array
    {
        $activeMasterProducts       = null;
        $discontinuedMasterProducts = null;

        if ($parent instanceof MasterShop || $parent instanceof MasterProductCategory) {
            $activeMasterProducts       = $parent->stats->number_mismatched_master_families_active;
            $discontinuedMasterProducts = $parent->stats->number_mismatched_master_families_inactive;
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

    public function handle(Group|MasterShop|MasterProductCategory $parent, string $parentType = 'department', $prefix = null): LengthAwarePaginator
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
        match (true) {
            $parent instanceof MasterShop =>
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id),

            $parent instanceof MasterProductCategory && $parentType === 'department' =>
            $queryBuilder->where('master_product_categories.master_department_id', $parent->id),

            $parent instanceof MasterProductCategory =>
            $queryBuilder->where('master_product_categories.master_sub_department_id', $parent->id),

            default =>
            $queryBuilder->where('master_product_categories.group_id', $parent->id),
        };

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

        $queryBuilder->where('master_product_categories.mismatch_detected', true);
        $queryBuilder->addSelect('master_product_categories.mismatch_detected');

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

    public function tableStructure(Group|MasterShop|MasterProductCategory $parent, ?array $modelOperations = null, $prefix = null): Closure
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

            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('Shop'), sortable: true);
                $table->column('master_department_code', __('Department'), sortable: true);
            }


            $table
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
        $navigation      = MasterProductCategoryTabsEnum::navigation();
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
            'label' => __('Master Families')
        ];
        $iconRight       = [
            'icon' => 'fal fa-folder-tree',
        ];
        $parentType      = 'department';

        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $masterShop    = $this->parent;
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
        } elseif ($this->parent instanceof MasterProductCategory) {
            if ($this->parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $icon            = [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('Master department')
                ];
                $subNavigation   = $this->getMasterDepartmentSubNavigation($this->parent);
                $modelNavigation = GetMasterDepartmentNavigation::run($this->parent, $request);
            } elseif ($this->parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $parentType      = 'sub_department';
                $icon            = [
                    'icon'  => ['fal', 'fa-folder'],
                    'title' => __('Master sub-department')
                ];
                $subNavigation   = $this->getMasterSubDepartmentSubNavigation($this->parent);
                $modelNavigation = GetMasterSubDepartmentNavigation::run($this->parent, $request);
            }
            $masterShop = $this->parent->masterShop;
        }

        $baseData = [
            'breadcrumbs' => $this->getBreadcrumbs(
                $this->parent,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => $modelNavigation,
            'title'       => __('Master Families'),
            'pageHead'    => [
                'title'         => $title,
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
            : Inertia::lazy(fn () => MasterFamiliesResource::collection(IndexMasterFamilies::run($this->parent, parentType: $parentType, prefix: MasterProductCategoryTabsEnum::INDEX->value)));

        $baseData[MasterProductCategoryTabsEnum::SALES->value] = $this->tab == MasterProductCategoryTabsEnum::SALES->value ?
            fn () => MasterFamiliesResource::collection(IndexMasterFamilies::run($this->parent, parentType: $parentType, prefix: MasterProductCategoryTabsEnum::SALES->value))
            : Inertia::lazy(fn () => MasterFamiliesResource::collection(IndexMasterFamilies::run($this->parent, parentType: $parentType, prefix: MasterProductCategoryTabsEnum::SALES->value)));

        return Inertia::render('Masters/MasterFamilies', $baseData)
            ->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::INDEX->value))
            ->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::SALES->value));
    }

    public function getActions(): array
    {
        $actions = [];

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

    public function getBreadcrumbs(Group|MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
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
                    'suffix' => trim('('.__('Has mismatch').') '.$suffix)
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_family.mismatch_detected.index' =>
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
            default => []
        };
    }
}
