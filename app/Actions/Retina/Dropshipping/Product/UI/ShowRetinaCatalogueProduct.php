<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 23:18:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Product\UI;

use App\Actions\Catalogue\Product\UI\GetProductShowcase;
use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\RetinaProductTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCatalogueProduct extends RetinaAction
{
    public function handle(Product $product): Product
    {
        return $product;
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request)->withTab(RetinaProductTabsEnum::values());

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {

        $title = $product->code;

        return Inertia::render(
            'Dropshipping/Product/Product',
            [
                'title'       => __('product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => $title
                    ],
                    'model' => __('Product'),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaProductTabsEnum::navigation()
                ],


                RetinaProductTabsEnum::SHOWCASE->value => $this->tab == RetinaProductTabsEnum::SHOWCASE->value ?
                    fn () => GetProductShowcase::run($product)
                    : Inertia::lazy(fn () => GetProductShowcase::run($product)),

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
                            'label' => __('Products')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $product->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $product = Product::where('slug', $routeParameters['product'])->first();

        return match ($routeName) {

            'retina.catalogue.products.show' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $product,
                    [
                        'index' => [
                            'name'       => 'retina.catalogue.products.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.catalogue.products.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

}
