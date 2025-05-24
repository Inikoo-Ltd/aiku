<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 May 2025 15:46:41 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInGroup extends OrgAction
{
    use WithGroupOverviewAuthorisation;


    protected function getElementGroups(Group $group, $bucket = null): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels($bucket),
                    ProductStateEnum::count($group, $bucket)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle(Group $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->orderBy('products.state');
        $queryBuilder->leftJoin('shops', 'products.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('asset_sales_intervals', 'products.asset_id', 'asset_sales_intervals.asset_id');
        $queryBuilder->leftJoin('asset_ordering_intervals', 'products.asset_id', 'asset_ordering_intervals.asset_id');
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.group_id', $parent->id);


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
                'invoices_all',
                'sales_all',
                'customers_invoiced_all',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['organisation_code', 'shop_code', 'code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table->withGlobalSearch();


            $table
                ->column(key: 'organisation_code', label: __('Org'), tooltip: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customers_invoiced_all', label: __('customers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'invoices_all', label: __('invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sales_all', label: __('amount'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $group = group();

        $navigation = ProductsTabsEnum::navigation();

        unset($navigation[ProductsTabsEnum::SALES->value]);


        $title      = __('Products');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => $title
        ];
        $afterTitle = [
            'label' => '@'.__('group')
        ];
        $iconRight  = null;
        $model      = null;


        $routes = null;


        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'                  => $this->getBreadcrumbs(),
                'title'                        => __('Products'),
                'pageHead'                     => [
                    'title'      => $title,
                    'model'      => $model,
                    'icon'       => $icon,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,

                ],
                'routes'                       => $routes,
                'data'                         => ProductsResource::collection($products),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::lazy(fn () => ProductsResource::collection($products)),

                ProductsTabsEnum::SALES->value => $this->tab == ProductsTabsEnum::SALES->value ?
                    fn () => ProductsResource::collection($products)
                    : Inertia::lazy(fn () => ProductsResource::collection($products)),


            ]
        )->table($this->tableStructure(group: $group, prefix: ProductsTabsEnum::INDEX->value))
            ->table($this->tableStructure(group: $group, prefix: ProductsTabsEnum::SALES->value));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(parent: group());
    }


    public function getBreadcrumbs(): array
    {
        $headCrumb = function () {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [],
                        'label' => __('Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                ]
            ];
        };


        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            $headCrumb()
        );
    }
}
