<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 15:44:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

trait WithRetinaOrderPlacedRedirection
{
    public function htmlResponse(array $arr): RedirectResponse
    {
        $itemsToPushTolayer = [];
        foreach ($arr['order']?->transactions as $index => $transaction) {
            if($transaction->model_type != 'Product') {
                continue;
            }

            $itemsToPushTolayer[] = (object)[
                'item_id'   => $transaction->model?->getLuigiIdentity(),
                'item_name' => $transaction->model?->name,
                'index'     => $index,
                'price'     => (float) $transaction->model?->price,
                'quantity'  => (float) $transaction->quantity_ordered,
            ];
        }
        
        if ($arr['success']) {
            $notification = [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Your order has been submitted.'),
            ];
            
            $gtm = [
                'key'               => 'retina_dropshipping_order_placed',
                'event'             => 'purchase',
                'data_to_submit'    => [
                    'ecommerce' => [
                        'transaction_id'    => $arr['order']->id,
                        'value'             => (float) $arr['order']->total_amount,
                        'currency'          => $arr['order']->shop->currency->code,
                        'items'             => $itemsToPushTolayer
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
                        'key' => 'dropshipping_order_placed' . $arr['order']->id,
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
                    'key' => 'ecom_order_placed' . $arr['order']->id,
                ]);
            }
        } elseif ($arr['reason'] == 'Insufficient balance') {
            return Redirect::back()->with('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => __('You do not have enough balance to pay for this order.'),
            ]);
        } else {
            return Redirect::back()->with('modal', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => __('An error occurred while processing your order: ').$arr['reason'],
            ]);
        }
    }
}
