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
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Sentry;

class FulfillOrderToShopify extends OrgAction
{
    use WithShopifyApi;
    use WithActionUpdate;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order|PalletReturn $order, ?DeliveryNote $deliveryNote = null): void
    {
        $fulfillOrderId = $order->platform_order_id;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $order->customerSalesChannel->user;

        if (!$shopifyUser) {
            return;
        }

        $mutation = <<<'MUTATION'
           mutation fulfillmentCreate($fulfillment: FulfillmentInput!, $message: String) {
              fulfillmentCreate(fulfillment: $fulfillment, message: $message) {
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


        if ($deliveryNote == null && $order instanceof Order) {
            $deliveryNote = $order->deliveryNotes()->where('state', DeliveryNoteStateEnum::DISPATCHED)->first();
        }

        if ($order instanceof PalletReturn) {
            $shipments = $order->shipments;
        } else {
            $shipments = $deliveryNote->shipments;
        }

        $shipper            = $shipments->first()?->shipper;
        $shipperCompanyName = $shipper?->trade_as ?? $shipper?->name;


        $numbers = [];
        $urls    = [];
        foreach ($shipments as $shipment) {
            $trackingNumbers = array_map(fn ($num) => (string)$num, $shipment->trackings);
            $numbers         = array_merge($numbers, $trackingNumbers);
            $urls            = array_merge($urls, $shipment->tracking_urls);
        }


        $trackingInfo = [
            'numbers' => $numbers,
            'company' => $shipperCompanyName,
        ];

        $message = 'Shipper: '.$shipperCompanyName.', '.implode(',', $numbers);

        $validShopifyShippingCompanies = ['Yodel', 'DPD UK', 'Parcelforce'];


        if (!in_array($shipperCompanyName, $validShopifyShippingCompanies) && !empty($urls)) {
            $trackingInfo['urls'] = $urls;
        }

        $variables = [
            'fulfillment' => [
                'lineItemsByFulfillmentOrder' => [
                    [
                        'fulfillmentOrderId' => $fulfillOrderId
                    ]
                ],
                'notifyCustomer'              => true,
                'trackingInfo'                => $trackingInfo
            ],
            'message'     => $message,
        ];


        try {
            list($status, $response) = $this->doPost($shopifyUser, $mutation, $variables);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        if (!$status) {
            throw ValidationException::withMessages(['message' => $response]);
        }


        if (!empty($response['errors'][0]['message'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['errors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }

        $userErrors = Arr::get($response['body']->toArray(), 'data.fulfillmentCreate.userErrors', []);
        if (!empty($userErrors)) {
            Sentry::captureMessage('Shopify refused the fulfilment of order '.$order->id.' ('.$fulfillOrderId.'): '.json_encode($userErrors));
        }
    }
}
