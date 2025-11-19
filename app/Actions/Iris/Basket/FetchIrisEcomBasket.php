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
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        $orderArr['order_data'] = [
            'reference' => $order->reference,
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

        $taxCategory  = $order->taxCategory;
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
        //
        //        web_image_thumbnail
        //offers_data
        //name
        //canonical_url
        //code
        //quantity_ordered_new
        //quantity_ordered
        //available_quantity

        $productsData = DB::table('transactions')
            ->select(
                'transactions.id',
                'net_amount',
                'quantity_ordered',
                'gross_amount',
                'products.url',
                'products.offers_data',
                'products.name',
                'products.code',
                'products.available_quantity',
                'products.web_images',
                'webpages.url as canonical_url'
            )
            ->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id')
            ->leftjoin('products', 'assets.model_id', '=', 'products.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->whereNotNull('transactions.deleted_at')
            ->where('transactions.model_type', 'Product')
            ->where('order_id', $order->id)->get();


        $transactions = [];
        foreach ($productsData as $productData) {
            $imageData         = is_string($productData->web_images) ? json_decode($productData->web_images, true) : $productData->web_images;
            $webImageThumbnail = Arr::get($imageData, 'main.thumbnail');

            $transactions[] = [
                'id'                  => $productData->id,
                'net_amount'          => $productData->net_amount,
                'gross_amount'        => $productData->gross_amount,
                'quantity_ordered'    => $productData->quantity_ordered,
                'available_quantity'  => $productData->available_quantity,
                'canonical_url'       => $productData->canonical_url,
                'offers_data'         => json_decode($productData->offers_data, 1),
                'name'                => $productData->name,
                'code'                => $productData->code,
                'web_image_thumbnail' => $webImageThumbnail,

            ];
        }



        $orderArr['products'] = $transactions;

        return $orderArr;
    }
}
