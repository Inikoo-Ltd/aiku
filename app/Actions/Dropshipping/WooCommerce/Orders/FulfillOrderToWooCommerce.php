<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Feb 2025 16:53:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = Arr::get($order->data, 'order_key');

        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $order->customerSalesChannel->user;

        $shipments = [];
        foreach ($order->shipments ?? [] as $shipment) {
            $shipments[] = [
                'tracking_provider'    => 'Other',
                'tracking_number'      => $shipment->tracking,
                'custom_tracking_link' => $shipment->combined_label_url,
                'date_shipped'         => now()->timestamp // current timestamp
            ];
        }

        $wooCommerceUser->updateWooCommerceOrder($fulfillOrderId, [
            'status' => 'completed', // or 'processing', 'on-hold', etc.
            'meta_data' => [
                [
                    'key'   => '_wc_shipment_tracking_items',
                    'value' => $shipments
                ]
            ]
        ]);
    }
}
