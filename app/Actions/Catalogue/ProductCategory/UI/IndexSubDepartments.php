<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSubDepartments extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDepartmentSubNavigation;

    private Shop|ProductCategory|Organisation $parent;


    public function asController(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(department: $department);
    }


    public function handle(ProductCategory $department, $prefix = null): LengthAwarePaginator
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

        foreach ($this->getElementGroups($department) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->where('product_categories.department_id', $department->id);
        $queryBuilder->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT);


        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.updated_at',
                'departments.slug as department_slug',
                'departments.code as department_code',
                'departments.name as department_name',

            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->allowedSorts(['code', 'name', 'shop_code', 'department_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
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

            $buttonLabel = __('New Sub-department');

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    $canEdit ?
                        [
                            'title'  => __("This department has no Sub-departments"),
                            'count'  => $parent->stats->number_sub_departments,
                            'action' => [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => $buttonLabel,
                                'label'   => $buttonLabel,
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.create',
                                    'parameters' => [
                                        'organisation' => $parent->organisation->slug,
                                        'shop'         => $parent->shop->slug,
                                        'department'   => $parent->slug
                                    ]
                                ]
                            ]
                        ] : null
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->withModelOperations($modelOperations);


            if ($parent instanceof Organisation) {
                $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'department_code', label: __('department'), canBeHidden: false, sortable: true, searchable: true);
            }


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $subDepartment): AnonymousResourceCollection
    {
        return SubDepartmentsResource::collection($subDepartment);
    }

    public function htmlResponse(LengthAwarePaginator $subDepartment, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof ProductCategory && $this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $subNavigation = $this->getDepartmentSubNavigation($this->parent);
        }

        $title      = __('Sub-departments');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-dot-circle'],
            'title' => __('Sub-department')
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof ProductCategory && $this->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $title      = $this->parent->name;
            $icon       = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('department')
            ];
            $iconRight  = [
                'icon' => 'fal fa-dot-circle',
            ];
            $afterTitle = [

                'label' => __('Sub-departments')
            ];
        }

        return Inertia::render(
            'Org/Catalogue/SubDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('sub-departments'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new Sub-department'),
                            'label'   => __('Sub-department'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]

                            : false,
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'data'        => SubDepartmentsResource::collection($subDepartment),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Sub-departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.families.index' => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.departments.show.sub_departments.index' => array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.index',
                        'parameters' => [
                            $routeParameters['organisation'],
                            $routeParameters['shop'],
                            $routeParameters['department']
                        ]
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
                        ProductCategoryStateEnum::countSubDepartment($parent)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('product_categories.state', $elements);
                    }
                ]
            ];
    }
}
