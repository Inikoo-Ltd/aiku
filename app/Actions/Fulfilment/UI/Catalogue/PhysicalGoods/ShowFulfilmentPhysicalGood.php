<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:53 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods;

use App\Actions\Catalogue\Product\UI\GetPhysicalGoodShowcase;
use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\UI\Fulfilment\FulfilmentAssetTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentPhysicalGood extends OrgAction
{
    use WithFulfilmentShopAuthorisation;


    public function handle(Product $product): Product
    {
        return $product;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, Product $product, ActionRequest $request): Product
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentAssetTabsEnum::values());
        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/PhysicalGood',
            [
                'title'       => __('physical goods'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($product, $request),
                    'next'     => $this->getNext($product, $request),
                ],
                'pageHead'    => [
                    'model'   => __('physical goods'),
                    'title'   => $product->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Product')
                        ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentAssetTabsEnum::navigation()
                ],


                FulfilmentAssetTabsEnum::SHOWCASE->value => $this->tab == FulfilmentAssetTabsEnum::SHOWCASE->value ?
                    fn () => GetPhysicalGoodShowcase::run($product)
                    : Inertia::lazy(fn () => GetPhysicalGoodShowcase::run($product)),

            ]
        );
    }

    public function jsonResponse(Product $product): ProductsResource
    {
        return new ProductsResource($product);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Product $product, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Goods')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $product->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $product = Product::where('slug', $routeParameters['product'])->first();

        return match ($routeName) {
            'grp.org.fulfilments.show.catalogue.physical_goods.show' =>
            array_merge(
                (new ShowFulfilmentCatalogueDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Product $product, ActionRequest $request): ?array
    {
        $previous = Product::where('shop_id', $this->shop->id)->where('slug', '<', $product->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Product $product, ActionRequest $request): ?array
    {
        $next = Product::where('shop_id', $this->shop->id)->where('slug', '>', $product->slug)->orderBy('slug')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Product $product, string $routeName): ?array
    {
        if (!$product) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.catalogue.physical_goods.show' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'fulfilment'   => $this->fulfilment->slug,
                        'product'        => $product->slug,
                    ],
                ],
            ],
            default => null,
        };
    }
}
