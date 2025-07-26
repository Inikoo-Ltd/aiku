<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Feb 2025 16:53:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = $order->platform_order_id;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $order->customerSalesChannel->user;

        $mutation = <<<'GRAPHQL'
            mutation fulfillmentCreateV2($fulfillmentInput: FulfillmentV2Input!) {
                fulfillmentCreateV2(fulfillment: $fulfillmentInput) {
                    fulfillment {
                        id
                        status
                        trackingInfo {
                            company
                            number
                            url
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $deliveryNotes = $order->deliveryNotes->first();
        $shipments     = $deliveryNotes->shipments;

        if (blank($shipments)) {
            throw ValidationException::withMessages([
                'messages' => __('The shipments are empty.')
            ]);
        }

        $shipper= $shipments->first()->shipper;
        $shipperCompanyName = $shipper->trade_as??$shipper->name;


        $trackingInfo = [
            'numbers' => $shipments->pluck('tracking')->toArray(),
            'company' => $shipperCompanyName,
            'urls'    => $shipments->pluck('tracking_urls')->toArray(),
        ];



        try {


            $response = $shopifyUser->getShopifyClient(true)->request($mutation, [
                'fulfillmentInput' => [
                    'lineItemsByFulfillmentOrder' => [
                        [
                            'fulfillmentOrderId' => $fulfillOrderId
                        ]
                    ],
                    'trackingInfo'                => $trackingInfo
                ]
            ]);



        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        if (!empty($response['errors'][0]['message'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['errors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }

        if (!empty($response['body']['data']['fulfillmentCreateV2']['userErrors'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['body']['data']['fulfillmentCreateV2']['userErrors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }
    }
}
