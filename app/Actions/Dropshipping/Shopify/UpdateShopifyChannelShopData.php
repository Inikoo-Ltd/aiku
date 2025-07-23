<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Dropshipping\Shopify\FulfilmentService\GetFulfilmentServiceName;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShopifyChannelShopData
{
    use asAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        $canConnectToPlatform = false;
        $existInPlatform      = false;
        $platformStatus       = false;

        $updateData = [];

        [$status, $storeData] = $this->getShopifyShopData($customerSalesChannel);

        if ($status === null) {
            // try latter
            return $customerSalesChannel;
        }


        if ($status == 'ok') {
            $canConnectToPlatform = true;
            $updateData           = [
                'data' => [
                    'shop' => $storeData
                ]
            ];


            $fulfilmentServiceName = GetFulfilmentServiceName::run($customerSalesChannel);


            // Extract shopID from the response and save it to shopify_shop_id
            if ($storeData && isset($storeData['id'])) {
                $updateData['shopify_shop_id'] = $storeData['id'];
            }

            // Loop over fulfillmentServices and look for one with name = $fulfilmentServiceName
            // and save that id in shopify_fulfilment_service_id
            if ($storeData && isset($storeData['fulfillmentServices']) && is_array($storeData['fulfillmentServices'])) {
                foreach ($storeData['fulfillmentServices'] as $service) {
                    if (isset($service['serviceName']) && $service['serviceName'] === $fulfilmentServiceName) {
                        $existInPlatform                             = true;
                        $platformStatus                              = true;
                        $updateData['shopify_fulfilment_service_id'] = $service['id'];

                        // Extract location_id from the found fulfillmentService and save it to shopify_location_id
                        if (isset($service['location']['id'])) {
                            $updateData['shopify_location_id'] = $service['location']['id'];
                        }

                        break;
                    }
                }
            }
        }



        $this->update($customerSalesChannel, [
            'platform_status'            => $platformStatus,
            'platform_can_connect'       => $canConnectToPlatform,
            'platform_exist_in_platform' => $existInPlatform,
        ]);
        $this->update($customerSalesChannel->user, $updateData);


        return $customerSalesChannel;
    }

    public function getCommandSignature(): string
    {
        return 'shopify:shop_data {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $customerSalesChannel = $this->handle($customerSalesChannel);

        $shopData = $customerSalesChannel->user->data['shop'] ?? [];


        if (empty($shopData)) {
            $command->info("No shop data found.");

            return;
        }


        // Basic shop information
        $tableData = [
            ['ID', $shopData['id'] ?? 'N/A'],
            ['Name', $shopData['name'] ?? 'N/A'],
            ['Email', $shopData['email'] ?? 'N/A'],
            ['URL', $shopData['url'] ?? 'N/A'],
            ['Myshopify Domain', $shopData['myshopifyDomain'] ?? 'N/A'],
            ['Description', $shopData['description'] ?? 'N/A'],
            ['Company Name', $shopData['company_name'] ?? 'N/A']
        ];

        $command->info("\nShop Information:");
        $command->table(['Field', 'Value'], $tableData);

        // Billing address information if available
        if (!empty($shopData['billingAddress'])) {
            $billingAddress = $shopData['billingAddress'];
            $addressData    = [
                ['Address Line 1', $billingAddress['address_line_1'] ?? 'N/A'],
                ['Address Line 2', $billingAddress['address_line_2'] ?? 'N/A'],
                ['City', $billingAddress['locality'] ?? 'N/A'],
                ['Province/State', $billingAddress['administrative_area'] ?? 'N/A'],
                ['Postal/ZIP Code', $billingAddress['postal_code'] ?? 'N/A'],
                ['Country Code', $billingAddress['country_code'] ?? 'N/A']
            ];

            $command->info("\nBilling Address:");
            $command->table(['Field', 'Value'], $addressData);
        }

        // Fulfillment services information if available
        if (!empty($shopData['fulfillmentServices'])) {
            $command->info("\nFulfillment Services:");

            $counter = 1;
            foreach ($shopData['fulfillmentServices'] as $service) {
                $serviceData = [
                    ['ID', $service['id'] ?? 'N/A'],
                    ['Name', $service['serviceName'] ?? 'N/A'],
                    ['callbackUrl', $service['callbackUrl'] ?? 'N/A'],
                    ['Type', $service['type'] ?? 'N/A'],
                    ['Inventory Management', $service['inventoryManagement'] ? 'Yes' : 'No']
                ];

                $command->info("\nService #$counter:");
                $command->table(['Field', 'Value'], $serviceData);

                // Location information if available
                if (!empty($service['location'])) {
                    $location     = $service['location'];
                    $locationData = [
                        ['ID', $location['id'] ?? 'N/A'],
                        ['Name', $location['name'] ?? 'N/A'],
                        ['Created At', $location['createdAt'] ?? 'N/A'],
                        ['Active', $location['isActive'] ? 'Yes' : 'No'],
                        ['Fulfills Online Orders', $location['fulfillsOnlineOrders'] ? 'Yes' : 'No']
                    ];

                    $command->info("Location:");
                    $command->table(['Field', 'Value'], $locationData);

                    // Location address if available
                    if (!empty($location['address'])) {
                        $address     = $location['address'];
                        $addressData = [
                            ['Phone', $address['phone'] ?? 'N/A'],
                            ['Address Line 1', $address['address_line_1'] ?? 'N/A'],
                            ['Address Line 2', $address['address_line_2'] ?? 'N/A'],
                            ['City', $address['locality'] ?? 'N/A'],
                            ['Province/State', $address['administrative_area'] ?? 'N/A'],
                            ['Postal/ZIP Code', $address['postal_code'] ?? 'N/A'],
                            ['Country Code', $address['country_code'] ?? 'N/A']
                        ];

                        $command->info("Address:");
                        $command->table(['Field', 'Value'], $addressData);
                    }
                }

                $counter++;
            }
        }

        $command->info("\nShop data updated successfully.");
    }

}
