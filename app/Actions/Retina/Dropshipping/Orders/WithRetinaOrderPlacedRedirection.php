<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 15:44:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

trait WithRetinaOrderPlacedRedirection
{
    public function htmlResponse(array $arr): RedirectResponse
    {
        $itemsToPushLayer = [];

        /** @var Order $order */
        $order = Arr::get($arr, 'order');

        if ($order) {
            $transactionsData = DB::table('transactions')
                ->select('webpages.slug', 'products.name', 'products.price', 'transactions.quantity_ordered')
                ->where('transactions.order_id', $order->id)
                ->where('transactions.deleted_at', null)
                ->where('transactions.model_type', 'Product')
                ->whereNotNull('webpages.slug')
                ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
                ->leftJoin('webpages', 'products.webpage_id', '=', 'webpages.id')
                ->get();

            $index = 1;
            foreach ($transactionsData as $transactionData) {
                $itemsToPushLayer[] = (object)[
                    'item_id'   => 'webpage-'.$transactionData->slug,
                    'item_name' => $transactionData->name,
                    'index'     => $index++,
                    'price'     => (float)$transactionData->price,
                    'quantity'  => (float)$transactionData->quantity_ordered,
                ];
            }
        }

        if (Arr::get($arr, 'success')) {
            $notification = [
                'status'      => 'success',
                'title'       => __('Success!'),
                'description' => __('Your order has been submitted.'),
            ];

            $gtm = [
                'key'            => 'retina_dropshipping_order_placed',
                'event'          => 'purchase',
                'data_to_submit' => [
                    'ecommerce' => [
                        'transaction_id' => $arr['order']->id,
                        'value'          => (float)$arr['order']->total_amount,
                        'currency'       => $arr['order']->shop->currency->code,
                        'items'          => $itemsToPushLayer
                    ]
                ]
            ];

            if ($arr['order']->shop->type == ShopTypeEnum::DROPSHIPPING) {
                return Redirect::route(
                    'retina.dropshipping.customer_sales_channels.orders.show',
                    [
                        'customerSalesChannel' => $arr['order']->customerSalesChannel->slug,
                        'order'                => $arr['order']->slug
                    ]
                )
                    ->with('modal', $notification)
                    ->with('gtm', $gtm)
                    ->with('confetti', [
                        'key' => 'dropshipping_order_placed'.$arr['order']->id,
                    ]);
            } else {
                return Redirect::route(
                    'retina.ecom.orders.show',
                    [
                        'order' => $arr['order']->slug
                    ]
                )->with('notification', $notification)
                    ->with('gtm', $gtm)
                    ->with('confetti', [
                        'key' => 'ecom_order_placed'.$arr['order']->id,
                    ]);
            }
        } elseif (Arr::get($arr, 'reason') == 'Insufficient balance') {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('You do not have enough balance to pay for this order.'),
            ]);
        } else {
            return Redirect::back()->with('modal', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('An error occurred while processing your order:').' '.Arr::get($arr, 'reason'),
            ]);
        }
    }
}
