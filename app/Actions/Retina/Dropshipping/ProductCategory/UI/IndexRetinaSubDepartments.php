<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\RetinaAction;
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

class IndexRetinaSubDepartments extends RetinaAction
{
    private Shop|ProductCategory $parent;


    public function inDepartment(ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $department;

        return $this->handle(parent: $department);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $this->shop;

        return $this->handle(parent: $this->shop);
    }


    public function handle(ProductCategory|Shop $parent, $prefix = null): LengthAwarePaginator
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

        if ($parent instanceof ProductCategory && $parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('product_categories.department_id', $parent->id);
        } else {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        }
        $queryBuilder->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUED->value]);

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
                'product_categories.updated_at',
                'departments.slug as department_slug',
                'departments.code as department_code',
                'departments.name as department_name',
                'product_category_stats.number_families as number_families',
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->allowedSorts(['code', 'state', 'name', 'shop_code', 'department_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $buttonLabel = __('New Sub-department');

            $table
                ->defaultSort('code')
                ->withEmptyState([]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon', sortable: true)
                ->withModelOperations($modelOperations);


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_families', label: __('families'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $subDepartment): AnonymousResourceCollection
    {
        return SubDepartmentsResource::collection($subDepartment);
    }

    public function htmlResponse(LengthAwarePaginator $subDepartment, ActionRequest $request): Response
    {
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
            // $iconRight  = [
            //     'icon' => 'fal fa-dot-circle',
            // ];
            $iconRight  = $this->parent->state->stateIcon()[$this->parent->state->value];

            $afterTitle = [

                'label' => __('Sub-departments')
            ];
        }

        return Inertia::render(
            'Catalogue/RetinaSubDepartements',
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
                        'label' => __('Sub Departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'retina.catalogue.sub_department.index' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs(),
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
