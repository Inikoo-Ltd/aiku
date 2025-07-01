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
        if ($arr['success']) {
            $notification = [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Your order bas been submitted.'),
            ];

            if ($arr['order']->shop->type == ShopTypeEnum::DROPSHIPPING) {
                return Redirect::route(
                    'retina.dropshipping.customer_sales_channels.orders.show',
                    [
                        'customerSalesChannel' => $arr['order']->customerSalesChannel->slug,
                        'order'                => $arr['order']->slug
                    ]
                )
                    ->with('notification', $notification);
            } else {
                return Redirect::route(
                    'retina.ecom.orders.show',
                    [
                        'order' => $arr['order']->slug
                    ]
                )->with('notification', $notification);
            }
        } elseif ($arr['reason'] == 'Insufficient balance') {
            return Redirect::back()->with('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => __('You do not have enough balance to pay for this order.'),
            ]);
        } else {
            return Redirect::back()->with('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => __('An error occurred while processing your order: ').$arr['reason'],
            ]);
        }
    }
}
