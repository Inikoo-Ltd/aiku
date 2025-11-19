<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\IrisProductsInBasketResource;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Sales\OrderResource;

class FetchIrisEcomBasket extends IrisAction
{
    public function handle(ActionRequest $request): Order|null
    {
        $customer = $request->user()?->customer;
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

    public function jsonResponse(?Order $order): array|null
    {
        if (!$order) {
            return null;
        }

        $orderArr['order_data'] =  [
            'reference'           => $order->reference,
        ];

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

            'currency' => [
                'code' => $order->currency->code,
            ]
        ];


        $orderArr['products'] = IrisProductsInBasketResource::collection(IndexBasketProducts::run($order));
        return $orderArr;
    }
}
