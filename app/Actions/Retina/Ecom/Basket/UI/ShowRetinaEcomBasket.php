<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\RetinaEcomBasketTransactionsResources;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Http\Resources\Sales\OrderResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaEcomBasket extends RetinaAction
{
    use IsOrder;

    public function handle(Customer $customer): Order|null
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
        return $this->handle($this->customer);
    }

    public function htmlResponse(Order|null $order): Response|RedirectResponse
    {

        return Inertia::render(
            'Ecom/Basket',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Baskets'),
                'pageHead'    => [
                        'title' => __('Baskets'),
                        'icon'  => 'fal fa-shopping-basket'
                    ],

                'routes'    => [
                        'update_route' => [
                            'name'       => 'retina.models.order.update',
                            'parameters' => [
                                'order' => $order->id
                            ],
                            'method'     => 'patch'
                        ],
                        'submit_route' => [
                            'name'       => 'retina.models.order.submit',
                            'parameters' => [
                                'order' => $order->id
                            ],
                            'method'     => 'patch'
                        ]
                    ],

                'voucher' => [],

                'order'          => $order ? OrderResource::make($order)->resolve() : [],
                'summary'     => $order ? $this->getOrderBoxStats($order) : null,

                'balance'       => $this->customer->balance,
                'total_to_pay'  => $order?->total_amount,
                'transactions'  => $order ? RetinaEcomBasketTransactionsResources::collection(IndexBasketTransactions::run($order)) : null,
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
