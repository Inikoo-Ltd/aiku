<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Market\ProductCategory\UI;

use App\Actions\InertiaAction;
use App\Actions\Market\Shop\UI\IndexShops;
use App\Actions\Market\Shop\UI\ShowShop;
use App\Http\Resources\Market\FamilyResource;
use App\Models\Market\ProductCategory;
use App\Models\Market\Shop;
use App\Models\Tenancy\Tenant;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IndexFamilies extends InertiaAction
{
    private Shop|ProductCategory|Tenant $parent;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->can('shops.edit');

        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('shops.view')
            );
    }

    public function inTenant(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = app('currentTenant');

        return $this->handle(parent: app('currentTenant'));
    }

    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $shop;

        return $this->handle(parent: $shop);
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function handle(Shop|ProductCategory|Tenant $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhere('product_categories.code', 'ilike', "$value%");
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                prefix: $prefix,
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }

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
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('is_family', true)
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'Shop') {
                    $query->where('product_categories.parent_type', 'Shop');
                    $query->where('product_categories.parent_id', $parent->id);
                } elseif (class_basename($parent) == 'Tenant') {
                    $query->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
                    $query->addSelect('shops.slug as shop_slug');
                }
            })
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory|Tenant $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->defaultSort('code')
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Tenant' => [

                            'title'       => __("No families found"),
                            'description' => $this->canEdit && app('currentTenant')->marketStats->number_shops == 0 ? __('Get started by creating a new shop. ✨')
                                : __("In fact, is no even a shop yet 🤷🏽‍♂️"),
                            'count'       => app('currentTenant')->marketStats->number_families,
                            'action'      => $this->canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new shop'),
                                'label'   => __('shop'),
                                'route'   => [
                                    'name'       => 'shops.create',
                                    'parameters' => array_values($this->originalParameters)
                                ]
                            ] : null
                        ],
                        default => null
                    }
                )
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamilyResource::collection($families);
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        return Inertia::render(
            'Market/Families',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => __('families'),
                'pageHead'    => [
                    'title'   => __('families'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('family')
                    ],
                    'actions' => [
                        $this->canEdit && $this->routeName == 'shops.show.families.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new family'),
                            'label'   => __('family'),
                            'route'   => [
                                'name'       => 'shops.show.families.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],
                'data'        => FamilyResource::collection($families),
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
                        'label' => __('families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'shops.families.index' =>
            array_merge(
                IndexShops::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'shops.show.families.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
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
