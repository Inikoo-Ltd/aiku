<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Feb 2025 15:06:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateTrackingFulfilmentOrderShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'dropshipping:shopify:fulfilment:update-tracking {orderId}';

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn|Order $parent): void
    {
        if ($parent instanceof Order) {
            $fulfilmentOrderId = Arr::get($parent->data, 'shopify_data.id');
            $deliveryNote = $parent->deliveryNotes->first();
            $shipments = $deliveryNote->shipments;
        } else {
            $fulfilmentOrderId = $parent->shopifyFulfilment->shopify_fulfilment_id;
            $shipments = $parent->shipments;
        }

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $parent->customerSalesChannel->user;

        $client = $shopifyUser->api()->getRestClient();
        $response = $client->request('GET', "/admin/api/2025-04/fulfillment_orders/" . $fulfilmentOrderId . "/fulfillments.json");

        if ($response['body']) {
            $fulfilmentId = Arr::get($response['body'], 'fulfillments.0.id');

            foreach ($shipments as $shipment) {
                $client->request('POST', "/admin/api/2025-04/fulfillments/" . $fulfilmentId . "/update_tracking.json", [
                    'fulfillment' => [
                        'notify_customer' => true,
                        'tracking_info' => [
                            'number' => $shipment->tracking,
                            'url' => $shipment->combined_label_url
                        ]
                    ]
                ]);
            }
        }
    }

    public function asCommand(Command $command)
    {
        $palletReturn = Order::where('id', $command->argument('orderId'))->firstOrFail();

        $this->handle($palletReturn);
    }
}
