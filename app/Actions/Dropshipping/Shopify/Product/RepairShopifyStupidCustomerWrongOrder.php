<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyStupidCustomerWrongOrder
{
    use WithShopifyApi;
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'shopify:update_stupid_order {orderTarget} {orderSource}';
    }

    /**
     * @throws ValidationException
     */
    public function handle(Order $targetOrder, Order $sourceOrder, Command $command): void
    {
        $fulfillOrderId = $targetOrder->platform_order_id;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $targetOrder->customerSalesChannel->user;

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

        $deliveryNote = $sourceOrder->deliveryNotes->first();
        $shipments = $deliveryNote->shipments;

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

        if (!empty($response['body']['data']['fulfillmentCreateV2']['userErrors'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['body']['data']['fulfillmentCreateV2']['userErrors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }
    }

    public function asCommand(Command $command): void
    {
        $orderTarget = $command->argument('orderTarget');
        $orderSource = $command->argument('orderSource');

        $command->info("Starting to process order repair from $orderSource to $orderTarget...");

        $order = Order::where('slug', $orderTarget)->first();

        if (!$order) {
            $command->error("Target order $orderTarget not found!");
            return;
        }

        $sourceOrder = Order::where('slug', $orderSource)->first();

        if (!$sourceOrder) {
            $command->error("Source order $orderSource not found!");
            return;
        }

        $this->handle($order, $sourceOrder, $command);
    }
}
