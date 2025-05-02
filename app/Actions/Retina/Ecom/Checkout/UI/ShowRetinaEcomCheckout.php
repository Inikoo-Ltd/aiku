<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Checkout\UI;

use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomCheckout extends RetinaAction
{
    use IsOrder;

    public function handle(Customer $customer, $prefix = null): Order|null
    {
        if (!$customer->current_order_in_basket_id) {
            return null;
        }
        return Order::find($customer->current_order_in_basket_id);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): Order|null
    {
        $this->initialisation($request);

        $customer = $request->user()->customer;

        return $this->handle($customer);
    }

    public function htmlResponse(Order|null $order): Response
    {
        return Inertia::render(
            'Ecom/Checkout',
            [
                    'breadcrumbs' => $this->getBreadcrumbs(),
                    'title'       => __('Baskets'),
                    'pageHead'    => [
                        'title' => __('Baskets'),
                        'icon'  => 'fal fa-shopping-basket'
                    ],



                    'data'     => $order ? $this->getOrderBoxStats($order) : null,
                ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.orders.index'
                            ],
                            'label'  => __('Orders'),
                        ]
                    ]
                ]
            );
    }
}
