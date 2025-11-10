<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Order\UI\GetOrderDeliveryAddressManagement;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\Ecom\Basket\UI\IndexBasketTransactions;
use App\Actions\Retina\Ecom\Orders\IndexRetinaEcomOrders;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Catalogue\ChargeResource;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Http\Resources\Fulfilment\RetinaEcomBasketTransactionsResources;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Http\Resources\Sales\OrderResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class FetchRetinaEcomBasket extends RetinaAction
{
    use IsOrder;

    public function handle(ActionRequest $request): Order|null
    {
        $customer = auth()->user()->customer()?->first() ?? null;
        if (!$customer?->current_order_in_basket_id) {
            return null;
        }

        return Order::where('id', $customer->current_order_in_basket_id)->where('customer_id', $customer->id)->where('state', OrderStateEnum::CREATING)->first();
    }

    public function asController(ActionRequest $request): Order|null
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function jsonResponse(Order $order): Array|null
    {
        if(!$order) return null;
        // $orderArr = $this->getOrderBoxStats($order); 
        $orderArr['order_summary'] = [
            [
                [
                    'label'       => __('Items'),
                    'quantity'    => $order->stats->number_item_transactions,
                    'price_base'  => 'Multiple',
                    'price_total' => $order->goods_amount
                ],
            ],
            [
                [
                    'label'       => __('Charges'),
                    'information' => '',
                    'price_total' => $order->charges_amount
                ],
                [
                    'label'       => __('Shipping'),
                    'information' => '',
                    'price_total' => $order->shipping_amount
                ]
            ],
            [
                [
                    'label'       => __('Net'),
                    'information' => '',
                    'price_total' => $order->net_amount
                ],
                [
                    'label'       => __('Tax').' ('.$order->taxCategory?->name.')',
                    'information' => '',
                    'price_total' => $order->tax_amount
                ]
            ],
            [
                [
                    'label'       => __('Total'),
                    'price_total' => $order->total_amount
                ],
            ],

            'currency' => CurrencyResource::make($order->currency),
        ]; 
        $orderArr['products'] = IrisAuthenticatedProductsInWebpageResource::collection(IndexBasketTransactions::run($order));
        return $orderArr;
    }
}
