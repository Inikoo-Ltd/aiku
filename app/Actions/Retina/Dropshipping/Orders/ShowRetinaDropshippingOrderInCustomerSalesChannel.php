<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Dropshipping\CustomerSalesChannel;

class ShowRetinaDropshippingOrderInCustomerSalesChannel extends RetinaAction
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
        $this->initialisation($request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        return ShowRetinaDropshippingOrder::make()->htmlResponse($order, $request);
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($order->slug),
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $order = Order::where('slug', $routeParameters['order'])->first();

        return array_merge(
            IndexRetinaDropshippingOrdersInPlatform::make()->getBreadcrumbs(),
            $headCrumb(
                $order,
                [
                    'index' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.orders.index',
                        'parameters' => []
                    ],
                    'model' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.orders.show',
                        'parameters' => [$routeParameters['customerSalesChannel'], $order->slug]
                    ]
                ],
                $suffix
            ),
        );
    }
}
