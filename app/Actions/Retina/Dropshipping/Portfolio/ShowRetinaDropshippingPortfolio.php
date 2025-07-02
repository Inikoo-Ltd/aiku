<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 23:18:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Catalogue\Product\UI\GetProductShowcase;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\RetinaAction;
use App\Enums\UI\Catalogue\RetinaProductTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaDropshippingPortfolio extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Portfolio $portfolio): Portfolio
    {
        return $portfolio;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, ActionRequest $request): Portfolio
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request)->withTab(RetinaProductTabsEnum::values());

        return $this->handle($portfolio);
    }

    public function htmlResponse(Portfolio $portfolio, ActionRequest $request): Response
    {
        /** @var Product $product */
        $product = $portfolio->item;


        $title = $product->code;
        if ($portfolio->reference && $portfolio->reference != $product->code) {
            $title .= ' ('.$portfolio->reference.')';
        }

        return Inertia::render(
            'Dropshipping/Product/Product',
            [
                'title'       => __('product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters(),
                    $title
                ),
                'pageHead'    => [
                    'title' => $title,
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => $title
                        ],
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

    public function getBreadcrumbs(array $routeParameters, $label): array
    {
        return
            array_merge(
                IndexRetinaPortfolios::make()->getBreadcrumbs($this->customerSalesChannel),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.customer_sales_channels.portfolios.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $label,
                        ]
                    ]
                ]
            );
    }

}
