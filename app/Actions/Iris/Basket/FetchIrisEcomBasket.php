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
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
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
            'id'                    => $order->id,
            'reference'             => $order->reference,
            'is_premium_dispatch'   => $order->is_premium_dispatch,
            'has_extra_packing'     => $order->has_extra_packing,
            'has_insurance'         => $order->has_insurance,
        ];

        $premiumDispatch = $order?->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->where('state', ChargeStateEnum::ACTIVE)->first();
        $extraPacking    = $order?->shop->charges()->where('type', ChargeTypeEnum::PACKING)->where('state', ChargeStateEnum::ACTIVE)->first();
        $insurance       = $order?->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->where('state', ChargeStateEnum::ACTIVE)->first();

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

        $productsData = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.offers_data',
                'net_amount',
                'quantity_ordered',
                'gross_amount',
                'products.url',
                'products.name',
                'products.units',
                'products.code',
                'products.available_quantity',
                'products.web_images',
                'webpages.url as canonical_url'
            )
            ->where('transactions.model_type', 'Product')
            ->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id')
            ->leftjoin('products', 'assets.model_id', '=', 'products.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.order_id', $order->id)
            ->orderBy('transactions.created_at', 'desc')
            ->get();


        $transactions = [];
        foreach ($productsData as $productData) {
            $imageData         = is_string($productData->web_images) ? json_decode($productData->web_images, true) : $productData->web_images;
            $webImageThumbnail = Arr::get($imageData, 'main.thumbnail');

            $transactions[] = [
                'transaction_id'       => $productData->id,
                'net_amount'           => $productData->net_amount,
                'gross_amount'         => $productData->gross_amount,
                'quantity_ordered'     => $productData->quantity_ordered,
                'quantity_ordered_new' => $productData->quantity_ordered,
                'available_quantity'   => $productData->available_quantity,
                'canonical_url'        => $productData->canonical_url,
                'offers_data'          => json_decode($productData->offers_data, 1),
                'name'                 => $productData->name,
                'code'                 => $productData->code,
                'units'                => (int) $productData->units,
                'web_image_thumbnail'  => $webImageThumbnail,

            ];
        }


        $orderArr['products'] = $transactions;

        $orderArr['charges'] = [
            'premium_dispatch'  => $premiumDispatch ? [
                'id'                => $premiumDispatch->id,
                'key_db'            => 'is_premium_dispatch',
                'route_update'  => [
                    'name'  => 'iris.models.order.update_premium_dispatch',
                    'parameters' => [
                        'order' => $order->id
                    ]
                ],
                'description'       => $premiumDispatch->description,
                'amount'            => Arr::get($premiumDispatch->settings, 'amount', 0),
                'label'             => $premiumDispatch->label ?? $premiumDispatch->name,
                'name'              => $premiumDispatch->name,
            ] : null,
            'extra_packing'     => $extraPacking ? [
                'id'                => $extraPacking->id,
                'key_db'            => 'has_extra_packing',
                'route_update'  => [
                    'name'  => 'iris.models.order.update_extra_packing',
                    'parameters' => [
                        'order' => $order->id
                    ]
                ],
                'description'       => $extraPacking->description,
                'amount'            => Arr::get($extraPacking->settings, 'amount', 0),
                'label'             => $extraPacking->label ?? $extraPacking->name,
                'name'              => $extraPacking->name,
            ] : null,
            'insurance'         => $insurance ? [
                'id'                => $insurance->id,
                'key_db'            => 'has_insurance',
                'route_update'  => [
                    'name'  => 'iris.models.order.update_insurance',
                    'parameters' => [
                        'order' => $order->id
                    ]
                ],
                'description'       => $insurance->description,
                'amount'            => Arr::get($insurance->settings, 'amount', 0),
                'label'             => $insurance->label ?? $insurance->name,
                'name'              => $insurance->name,
            ] : null,
        ];

        return $orderArr;
    }
}
