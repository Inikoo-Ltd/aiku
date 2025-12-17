<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:37:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Fulfilment\FulfilmentAssetsTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ShowFulfilmentCatalogueDashboard extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    protected function getElementGroups(Fulfilment $fulfilment): array
    {
        return [
            'type'  => [
                'label'    => __('Type'),
                'elements' => array_merge_recursive(
                    AssetTypeEnum::labels($fulfilment->shop),
                    AssetTypeEnum::count($fulfilment->shop)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('type', $elements);
                }

            ],
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    AssetStateEnum::labels(),
                    AssetStateEnum::count($fulfilment->shop)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }

    public function handle(Fulfilment $fulfilment, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('assets.name', $value)
                    ->orWhereStartWith('assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Asset::class);
        $queryBuilder->where('assets.shop_id', $fulfilment->shop_id);

        $queryBuilder->leftJoin('currencies', 'assets.currency_id', 'currencies.id');
        foreach ($this->getElementGroups($fulfilment) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->defaultSort('assets.code')
            ->select([
                'currencies.code as currency_code',
                'assets.code',
                'assets.name',
                'assets.state',
                'assets.type',
                'assets.price',
                'assets.created_at',
                'assets.updated_at',
                'assets.slug'
            ])
            ->leftJoin('asset_stats', 'assets.id', 'asset_stats.asset_id');


        return $queryBuilder->allowedSorts(['code', 'name', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentAssetsTabsEnum::values());

        return $this->handle($fulfilment, FulfilmentAssetsTabsEnum::ASSETS->value);
    }

    public function htmlResponse(LengthAwarePaginator $assets, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/Products',
            [
                'title'       => __('fulfilment'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-ballot'],
                        'title' => __('Catalogue')
                    ],
                    'title' => __('Catalogue'),
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentAssetsTabsEnum::navigation()
                ],


                FulfilmentAssetsTabsEnum::ASSETS->value => $this->tab == FulfilmentAssetsTabsEnum::ASSETS->value ?
                    fn () => FulfilmentProductsResource::collection($assets)
                    : Inertia::lazy(fn () => FulfilmentProductsResource::collection($assets)),


            ]
        )->table(
            $this->tableStructure(
                fulfilment: $this->fulfilment,
                prefix: FulfilmentAssetsTabsEnum::ASSETS->value
            )
        );
    }

    public function tableStructure(
        Fulfilment $fulfilment,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($fulfilment, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($fulfilment) as $key => $elementGroup) {
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
                    match (class_basename($fulfilment)) {
                        'Fulfilment' => [
                            'title' => __("No assets found"),
                            'count' => $fulfilment->shop->stats->number_assets,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'type', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }


    public function jsonResponse(Fulfilment $fulfilment): FulfilmentProductsResource
    {
        return new FulfilmentProductsResource($fulfilment);
    }


    public function getBreadcrumbs(array $routeParameters, $suffix = null, $icon = 'fal fa-bars'): array
    {
        $headCrumb = function (array $routeParameters = []) use ($suffix, $icon) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Catalogue'),
                        'icon'  => $icon
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.catalogue.index',
                        'parameters' => $routeParameters
                    ]
                )
            );
    }

    public function getPrevious(Fulfilment $fulfilment, ActionRequest $request): ?array
    {
        $previous = Shop::where('organisation_id', $this->organisation->id)->where('type', ShopTypeEnum::FULFILMENT)->where('code', '<', $fulfilment->shop->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous?->fulfilment, $request->route()->getName());
    }

    public function getNext(Fulfilment $fulfilment, ActionRequest $request): ?array
    {
        $next = Shop::where('organisation_id', $this->organisation->id)->where('type', ShopTypeEnum::FULFILMENT)->where('code', '>', $fulfilment->shop->code)->orderBy('code')->first();

        return $this->getNavigation($next?->fulfilment, $request->route()->getName());
    }

    private function getNavigation(?Fulfilment $fulfilment, string $routeName): ?array
    {
        if (!$fulfilment) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.dashboard' => [
                'label' => $fulfilment->shop?->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'fulfilment'   => $fulfilment->slug
                    ]

                ]
            ]
        };
    }
}
