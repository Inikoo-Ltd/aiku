<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UI\GetOrderAddressManagement;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\RetinaOrderTabsEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Dropshipping\CustomerSalesChannel;

class ShowRetinaDropshippingOrder extends RetinaAction
{
    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }
    public function asController(CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request)->withTab(RetinaOrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $finalTimeline = ShowOrder::make()->getOrderTimeline($order);


        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $action = [];

        $this->tab = $this->tab ?: RetinaOrderTabsEnum::TRANSACTIONS->value;

        return Inertia::render(
            'Dropshipping/RetinaDropshippingOrder',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs($order),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('customer client')
                    ],
                    'actions' => $action,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaOrderTabsEnum::navigation()
                ],

                'routes' => [
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

                'timelines' => $finalTimeline,

                'address_management' => GetOrderAddressManagement::run(order: $order, isRetina: true),

                'box_stats' => ShowOrder::make()->getOrderBoxStats($order),
                'currency'  => CurrencyResource::make($order->currency)->toArray(request()),
                'data'      => OrderResource::make($order),


                RetinaOrderTabsEnum::TRANSACTIONS->value => $this->tab == RetinaOrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))),


            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: RetinaOrderTabsEnum::TRANSACTIONS->value
                )
            );

    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(Order $order): array
    {
        return array_merge(
            IndexRetinaDropshippingOrders::make()->getBreadcrumbs($order->customerSalesChannel),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.dropshipping.customer_sales_channels.orders.show',
                            'parameters' => [
                                'customerSalesChannel' => $order->customerSalesChannel->slug,
                                'order'       => $order->slug
                            ]
                        ],
                        'label' => $order->reference,
                    ]
                ]
            ]
        );
    }


}
