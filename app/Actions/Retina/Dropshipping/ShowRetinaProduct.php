<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:45:56 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping;

use App\Actions\Catalogue\Product\UI\GetProductShowcase;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaPortfolios;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Enums\UI\Catalogue\RetinaProductTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaProduct extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;
    public function handle(Product $product): Product
    {
        return $product;
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request)->withTab(RetinaProductTabsEnum::values());

        return $this->handle($product);
    }

    public function inPlatform(CustomerSalesChannel $customerSalesChannel, Product $product, ActionRequest $request): Product
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request)->withTab(RetinaProductTabsEnum::values());

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'Dropshipping/Product/Product',
            [
                'title'       => __('product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $product->code,
                    'model'   => __('product'),
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('product')
                        ],
                    'actions' => [
                        // [
                        //     'type'  => 'button',
                        //     'style' => 'edit',
                        //     'route' => [
                        //         'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                        //         'parameters' => $request->route()->originalParameters()
                        //     ]
                        // ]

                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaProductTabsEnum::navigation()
                ],


                RetinaProductTabsEnum::SHOWCASE->value => $this->tab == RetinaProductTabsEnum::SHOWCASE->value ?
                    fn () => GetProductShowcase::run($product)
                    : Inertia::lazy(fn () => GetProductShowcase::run($product)),




            ]
        )->table(IndexOrders::make()->tableStructure($product->asset))
            ->table(IndexCustomers::make()->tableStructure($product->shop))
            ->table(IndexMailshots::make()->tableStructure($product));
    }

    public function jsonResponse(Product $product): ProductsResource
    {
        return new ProductsResource($product);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Product $product, array $routeParameters, string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($product->slug),
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $portfolio = Product::where('slug', $routeParameters['product'])->first();

        return array_merge(
            IndexRetinaPortfolios::make()->getBreadcrumbs($this->customerSalesChannel),
            $headCrumb(
                $portfolio,
                [
                    'index' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.index',
                        'parameters' => [$this->customerSalesChannel->slug]
                    ],
                    'model' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.show',
                        'parameters' => [$this->customerSalesChannel->slug, $portfolio->slug]
                    ]
                ],
                $suffix
            ),
        );
    }

}
