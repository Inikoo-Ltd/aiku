<?php

/*
 * author Arya Permana - Kirin
 * created on 21-01-2025-10h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Pricing\UI;

use App\Actions\Retina\Fulfilment\UI\IndexRetinaPricing;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Fulfilment\PhysicalGoodsResource;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\Fulfilment;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaGoods extends RetinaAction
{
    use WithRetinaPricingSubNavigation;
    protected function getElementGroups(Fulfilment $parent): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels(),
                    ProductStateEnum::count($parent->shop)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }

            ],
        ];
    }

    public function handle($prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.shop_id', $this->fulfilment->shop_id);
        $queryBuilder->join('assets', 'products.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');


        foreach ($this->getElementGroups($this->fulfilment) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->defaultSort('products.id')
            ->select([
                'products.id',
                'products.slug',
                'products.name',
                'products.code',
                'products.state',
                'products.created_at',
                'products.price',
                'products.unit',
                'currencies.code as currency_code',
                'assets.current_historic_asset_id as historic_asset_id',
            ]);


        return $queryBuilder->allowedSorts(['id','code','name','price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle();
    }



    public function htmlResponse(LengthAwarePaginator $goods, ActionRequest $request): Response
    {
        // dd(ServicesResource::collection($services));
        return Inertia::render(
            'Pricing/RetinaGoods',
            [
                'title'       => __('Goods'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => __('Goods')
                    ],
                    'model'    => __('Pricing'),
                    'title'         => __('Goods'),
                    'subNavigation' => $this->getPricingNavigation($this->fulfilment),
                ],

                'data'        => PhysicalGoodsResource::collection($goods),
            ]
        )->table(
            $this->tableStructure(
                parent: $this->fulfilment,
            )
        );
    }

    public function tableStructure(
        Fulfilment $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
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

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __("No rentals found"),
                            'count' => $parent->shop->stats->number_assets_type_product,
                        ],
                        default => null
                    }
                );

            $table
            ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
            ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono', align: 'right')
            ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $goods): AnonymousResourceCollection
    {
        return PhysicalGoodsResource::collection($goods);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) use ($suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Goods'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return
            array_merge(
                IndexRetinaPricing::make()->getBreadcrumbs(routeName: $routeName),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            );
    }


}
