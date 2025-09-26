<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:46:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods;

use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Fulfilment\PhysicalGoodsTabsEnum;
use App\Http\Resources\Fulfilment\PhysicalGoodsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentPhysicalGoods extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

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

    public function handle(Fulfilment $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.shop_id', $parent->shop_id);
        $queryBuilder->join('assets', 'products.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'products.currency_id', '=', 'currencies.id');


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
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




    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PhysicalGoodsTabsEnum::values());

        return $this->handle($fulfilment, PhysicalGoodsTabsEnum::PHYSICAL_GOODS->value);
    }


    public function htmlResponse(LengthAwarePaginator $physicalGoods, ActionRequest $request): Response
    {

        $actions = null;
        if ($request->user()->authTo("supervisor-fulfilment-shop.".$this->fulfilment->id)) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Create good'),
                    'route' => [
                        'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ],
            ];
        }

        return Inertia::render(
            'Org/Fulfilment/PhysicalGoods',
            [
                'title'       => __('fulfilment'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => __('Physical goods')
                    ],
                    'title'         => __('Physical goods'),
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PhysicalGoodsTabsEnum::navigation()
                ],



                PhysicalGoodsTabsEnum::PHYSICAL_GOODS->value => $this->tab == PhysicalGoodsTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => PhysicalGoodsResource::collection($physicalGoods)
                    : Inertia::lazy(fn () => PhysicalGoodsResource::collection($physicalGoods)),

            ]
        )->table(
            $this->tableStructure(
                parent: $this->fulfilment,
                prefix: PhysicalGoodsTabsEnum::PHYSICAL_GOODS->value
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
                            'title' => __("No services found"),
                            'count' => $parent->shop->stats->number_assets_type_service,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, align: 'right', className: 'text-right font-mono')
                ->column(key: 'workflow', label: __('workflow'))
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $physicalGoods): AnonymousResourceCollection
    {
        return PhysicalGoodsResource::collection($physicalGoods);
    }


    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
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
                ShowFulfilmentCatalogueDashboard::make()->getBreadcrumbs(routeParameters: $routeParameters, icon: 'fal fa-ballot'),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.index',
                        'parameters' => $routeParameters
                    ]
                )
            );
    }


}
