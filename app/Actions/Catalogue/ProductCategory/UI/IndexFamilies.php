<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\ProductCategoryTabsEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFamilies extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDepartmentSubNavigation;
    use WithCollectionSubNavigation;
    use WithSubDepartmentSubNavigation;

    private bool $sales = true;

    private Group|Shop|ProductCategory|Organisation $parent;

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->sales  = false;
        $this->initialisationFromGroup(group(), $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle($this->parent, prefix: ProductCategoryTabsEnum::INDEX->value);
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $organisation, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $department, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function inSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $subDepartment, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(ProductCategoryTabsEnum::values());

        return $this->handle(parent: $shop, prefix: ProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(Group|Shop|ProductCategory|Organisation|Collection $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'product_categories.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('product_category_sales_intervals', 'product_category_sales_intervals.product_category_id', 'product_categories.id');
        $queryBuilder->leftJoin('product_category_ordering_intervals', 'product_category_ordering_intervals.product_category_id', 'product_categories.id');
        if ($parent instanceof Group) {
            $queryBuilder->where('product_categories.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('product_categories.organisation_id', $parent->id);
        } elseif (class_basename($parent) == 'ProductCategory') {
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('product_categories.department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('product_categories.sub_department_id', $parent->id);
            } else {
                // todo
                abort(419);
            }
        }


        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.image_id',
                'product_categories.updated_at',
                'departments.slug as department_slug',
                'departments.code as department_code',
                'departments.name as department_name',
                'sub_departments.slug as sub_department_slug',
                'sub_departments.code as sub_department_code',
                'sub_departments.name as sub_department_name',
                'product_category_stats.number_current_products',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'product_category_sales_intervals.sales_grp_currency_all as sales_all',
                'product_category_ordering_intervals.invoices_all as invoices_all',

            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->leftjoin('product_categories as sub_departments', 'sub_departments.id', 'product_categories.sub_department_id')
            ->allowedSorts(['code', 'name', 'shop_code', 'department_code', 'number_current_products', 'sub_department_name', 'department_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|ProductCategory|Organisation|Collection $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false, $sales = true): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit, $sales) {
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
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No families found"),
                            'description' => $canEdit ?
                                $parent->catalogueStats->number_shops == 0 ? __("In fact, is no even a shop yet 🤷🏽‍♂️") : ''
                                : '',
                            'count'       => $parent->catalogueStats->number_families,
                            'action'      => $canEdit && $parent->catalogueStats->number_shops == 0
                                ?
                                [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('new shop'),
                                    'label'   => __('shop'),
                                    'route'   => [
                                        'name'       => 'grp.org.shops.show.catalogue.families.create',
                                        'parameters' => [$parent->slug]
                                    ]
                                ] : null

                        ],
                        'Shop', 'ProductCategory' => [
                            'title' => __("No families found"),
                            'count' => $parent->stats->number_families,
                        ],
                        default => null
                    }
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);

            if ($sales) {
                $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('sales'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'invoices', label: __('invoices'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                if ($parent instanceof Organisation) {
                    $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
                    $table->column(key: 'department_code', label: __('department'), canBeHidden: false, sortable: true, searchable: true);
                }
                $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sub_department_name', label: __('sub department'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'department_name', label: __('Department'), canBeHidden: false, sortable: true, searchable: true);

                if ($parent instanceof Group) {
                    $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
                }

                if (class_basename($parent) != 'Collection') {
                    $table->column(key: 'number_current_products', label: __('current products'), canBeHidden: false, sortable: true, searchable: true);
                }

                if (class_basename($parent) == 'Collection') {
                    $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
                }

                if($parent instanceof ProductCategory && $parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                    $table->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
                }
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }


    public function getActions(ActionRequest $request): array
    {
        $actions = [];
        if ($this->canEdit) {
            if ($this->parent instanceof ProductCategory) {
                $createRoute = "grp.org.shops.show.catalogue.departments.show.families.create";

                if ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                    $createRoute = "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.create";
                }

                $actions[] = [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('new family'),
                    'label'   => __('family'),
                    'route'   => [
                        'name'       => $createRoute,
                        'parameters' => $request->route()->originalParameters()
                    ]
                ];
            }
        }


        return $actions;
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        $navigation = ProductCategoryTabsEnum::navigation();
        if ($this->parent instanceof Group) {
            unset($navigation[ProductCategoryTabsEnum::SALES->value]);
        }
        $subNavigation = null;
        if ($this->parent instanceof ProductCategory) {
            if ($this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $subNavigation = $this->getDepartmentSubNavigation($this->parent);
            } elseif ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $subNavigation = $this->getSubDepartmentSubNavigation($this->parent);
            }
        }
        if ($this->parent instanceof Collection) {
            $subNavigation = $this->getCollectionSubNavigation($this->parent);
        }


        $title      = __('families');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-folder'],
            'title' => __('family')
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof ProductCategory) {
            if ($this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $title      = $this->parent->name;
                $model      = '';
                $icon       = [
                    'icon'  => ['fal', 'fa-folder-tree'],
                    'title' => __('department')
                ];
                // $iconRight  = [
                //     'icon' => 'fal fa-folder',
                // ];
                $iconRight  = $this->parent->state->stateIcon()[$this->parent->state->value];
                $afterTitle = [

                    'label' => __('Families')
                ];
            } elseif ($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $title      = $this->parent->name;
                $model      = '';
                $icon       = [
                    'icon'  => ['fal', 'fa-dot-circle'],
                    'title' => __('sub department')
                ];
                // $iconRight  = [
                //     'icon' => 'fal fa-folder',
                // ];
                $iconRight  = $this->parent->state->stateIcon()[$this->parent->state->value];
                $afterTitle = [

                    'label' => __('Families')
                ];
            }
        }

        $routes = null;

        if($this->parent instanceof PRoductCategory) 
        {
            if($this->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $routes = [
                    'attach' => [
                        'name'       => 'grp.models.sub-department.families.attach ',
                        'parameters' => [
                            'subDepartment' => $this->parent->id
                        ]
                    ],
                    'detach' => [
                        'name'       => 'grp.models.sub-department.family.detach',
                        'parameters' => [
                            'subDepartment' => $this->parent->id
                        ]
                    ],
                    'list'   => [
                        'name'      =>  'grp.json.product_category.families.index',
                        'parameters' => [
                            'productCategory' => $this->parent->slug
                        ]
                    ]

                ];
            }
        }


        return Inertia::render(
            'Org/Catalogue/Families',
            [
                'breadcrumbs'                         => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                               => __('families'),
                'pageHead'                            => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $this->getActions($request),
                    'subNavigation' => $subNavigation,
                ],
                'routes'                              => $routes,
                'data'                                => FamiliesResource::collection($families),
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductCategoryTabsEnum::INDEX->value => $this->tab == ProductCategoryTabsEnum::INDEX->value ?
                    fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection($families)),

                ProductCategoryTabsEnum::SALES->value => $this->tab == ProductCategoryTabsEnum::SALES->value ?
                    fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection($families)),
            ]
        )->table($this->tableStructure(parent: $this->parent, modelOperations: null, canEdit: false, prefix: ProductCategoryTabsEnum::INDEX->value, sales: false))
            ->table($this->tableStructure(parent: $this->parent, modelOperations: null, canEdit: false, prefix: ProductCategoryTabsEnum::SALES->value, sales: $this->sales));
    }

    public function getBreadcrumbs(Group|Shop|ProductCategory|Organisation|Collection $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.families.index' => array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.families.index' => array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.families.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department']
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index' => array_merge(
                ShowSubDepartment::make()->getBreadcrumbs($parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department'],
                            $routeParameters['subDepartment']
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.overview.catalogue.families.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs($routeParameters),
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

    protected function getElementGroups($parent): array
    {
        return
            [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        ProductCategoryStateEnum::labels(),
                        ProductCategoryStateEnum::countFamily($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('product_categories.state', $elements);
                    }
                ]
            ];
    }
}
