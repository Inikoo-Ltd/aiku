<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 May 2025 16:10:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil;

use App\Actions\Catalogue\Product\UI\IndexProducts as IndexUIProducts;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Dropshipping\ShopifyUser;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPupilProducts extends RetinaAction
{
    public function handle(ShopifyUser $scope): ShopifyUser
    {
        return $scope;
    }

    public function asController(ActionRequest $request): ShopifyUser
    {
        $this->initialisation($request);

        $user = $request->user();

        return $this->handle($user);
    }

    public function htmlResponse(ShopifyUser $scope): Response
    {
        $shop = $scope->customer->shop;
        $routes = [
            'store_product' => [
                'name' => 'pupil.models.dropshipping.shopify_user.product.store',
                'parameters' => [
                    'shopifyUser' => $scope->id
                ]
            ],
        ];

        return Inertia::render(
            'Dropshipping/Products',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('All Products'),
                'pageHead' => [
                    'model' => $scope->name,
                    'title' => __('All Products'),
                    'icon' => 'fal fa-cube'
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => ProductTabsEnum::navigation()
                ],
                'routes' => $routes,

                'products' => ProductsResource::collection(IndexUIProducts::make()->inDropshipping($scope, 'all'))
            ]
        )->table(IndexUIProducts::make()->tableStructure($shop, prefix: 'products'));
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.portfolios.products.index'
                            ],
                            'label' => __('Products'),
                        ]
                    ]
                ]
            );
    }
}
