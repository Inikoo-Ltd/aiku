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
use App\Http\Resources\Ordering\IrisProductsInBasketResource;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

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


        $hasDiscounts = $order->goods_amount != $order->gross_amount;

        if ($hasDiscounts) {
            $itemsData = [
                [
                    [
                        'label'       => __('Gross'),
                        'price_base'  => 'Multiple',
                        'price_total' => $order->gross_amount
                    ],
                    [
                        'label'             => __('Discounts'),
                        'label_class'       => 'text-green-600',
                        'information'       => '',
                        'price_total'       => -($order->gross_amount - $order->goods_amount),
                        'price_total_class' => 'text-green-600 font-medium'
                    ],
                    [
                        'label'       => __('Items net'),
                        'information' => '',
                        'price_total' => $order->goods_amount
                    ],
                ],
            ];
        } else {
            $itemsData = [
                [
                    [
                        'label'       => __('Items'),
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->goods_amount
                    ],
                ]
            ];
        }

        $taxCategory = $order->taxCategory;
        $orderSummary = $itemsData;

        $orderSummary[] = [
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
        ];

        $orderSummary[] =
            [
                [
                    'label'       => __('Net'),
                    'information' => '',
                    'price_total' => $order->net_amount
                ],
                [
                    'label'       => __('Tax').' ('.$taxCategory->name.')',
                    'information' => '',
                    'price_total' => $order->tax_amount
                ]
            ];

        $orderSummary[] = [
            [
                'label'       => __('Total'),
                'price_total' => $order->total_amount
            ],
        ];

        $orderArr['order_summary'] = $orderSummary;

        $orderArr['products'] = IrisProductsInBasketResource::collection(IndexBasketProducts::run($order));
        return $orderArr;
    }
}
