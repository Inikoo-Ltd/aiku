<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Feb 2025 16:53:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;


class FulfillOrderToShopify extends OrgAction
{
    use WithShopifyApi;
    use WithActionUpdate;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): void
    {
        $fulfillOrderId = $order->platform_order_id;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $order->customerSalesChannel->user;


        $mutation = <<<'MUTATION'
           mutation fulfillmentCreate($fulfillment: FulfillmentInput!) {
              fulfillmentCreate(fulfillment: $fulfillment) {
                fulfillment {
                  id
                }
                userErrors {
                  field
                  message
                }
              }
            }
        MUTATION;

        $deliveryNotes = $order->deliveryNotes->first();
        $shipments     = $deliveryNotes->shipments;

        if (blank($shipments)) {
            throw ValidationException::withMessages([
                'messages' => __('The shipments are empty.')
            ]);
        }

        $shipper= $shipments->first()->shipper;
        $shipperCompanyName = $shipper->trade_as??$shipper->name;


        $numbers=[];
        $urls=[];
        foreach ($shipments as $shipment) {
            $numbers=array_merge($numbers,$shipment->trackings);
            $urls=array_merge($urls,$shipment->tracking_urls);
        }


        $trackingInfo = [
            'numbers' => $numbers,
            'company' => $shipperCompanyName,
        ];

        $validShopifyShippingCompanies=['Yodel','DPD UK','Parcelforce'];

        if(!in_array($shipperCompanyName,$validShopifyShippingCompanies)){
            $trackingInfo['urls']=$urls;
        }

        $variables=[
            'fulfillment' => [
                'lineItemsByFulfillmentOrder' => [
                    [
                        'fulfillmentOrderId' => $fulfillOrderId
                    ]
                ],
                'trackingInfo'                => $trackingInfo
            ]
        ];



        try {
            list($status, $response) = $this->doPost($shopifyUser, $mutation, $variables);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        if(!$status){
            throw ValidationException::withMessages(['message' =>$response]);
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
