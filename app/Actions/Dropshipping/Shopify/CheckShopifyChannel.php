<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Actions\Dropshipping\Shopify\FulfilmentService\GetFulfilmentServiceName;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckShopifyChannel
{
    use asAction;
    use WithActionUpdate;


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

        $shopName = Arr::get($storeData, 'name');
        $this->update($customerSalesChannel, [
            'name'                    => $shopName,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform,
        ]);
        if ($customerSalesChannel->user) {
            $this->update($customerSalesChannel->user, $updateData);
        }



        return $customerSalesChannel;
    }

    public function getCommandSignature(): string
    {
        return 'shopify:check {customerSalesChannel? : The slug of the customer sales channel to check (optional, processes all channels with platform_id=1 if not provided)}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if ($customerSalesChannelSlug) {
            // Process a single customer sales channel
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->firstOrFail();
            $customerSalesChannel = $this->handle($customerSalesChannel);

            // Display CustomerSalesChannel status information
            $this->displayChannelInfo($command, $customerSalesChannel);
        } else {

            $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();

            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $shopifyPlatform->id)->get();

            if ($customerSalesChannels->isEmpty()) {
                $command->info('No customer sales channels found with platform_id=1.');
                return;
            }

            $command->info("Processing {$customerSalesChannels->count()} customer sales channels with platform_id=1...");

            // Create a progress bar
            $bar = $command->getOutput()->createProgressBar($customerSalesChannels->count());
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $bar->start();

            $successCount = 0;
            $failCount = 0;

            /** @var CustomerSalesChannel $customerSalesChannel */
            foreach ($customerSalesChannels as $customerSalesChannel) {
                try {
                    $this->handle($customerSalesChannel);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    $command->error("Error processing $customerSalesChannel->slug: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $command->newLine(2);
            $command->info("Processed {$customerSalesChannels->count()} customer sales channels: $successCount successful, $failCount failed.");
        }
    }

    /**
     * Display detailed information about a customer sales channel
     */
    private function displayChannelInfo(Command $command, CustomerSalesChannel $customerSalesChannel): void
    {
        // Display CustomerSalesChannel status information
        $statusData = [
            ['Customer Sales Channel', $customerSalesChannel->slug],
            ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
            ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
            ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No']
        ];

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
            $addressData = [
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
                    $location = $service['location'];
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
                        $address = $location['address'];
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

        $command->info("\nCustomer Sales Channel Status:");
        $command->table(['Field', 'Value'], $statusData);

        $command->info("\nShop data updated successfully.");
    }

    public function getShopifyShopData(CustomerSalesChannel $customerSalesChannel): ?array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            return ['fail', ['error' => 'No shopify user']];
        }

        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return ['fail', ['error' => 'No shopify client']];
        }

        try {
            // GraphQL query to get shop data
            $query = <<<'QUERY'
            {
              shop {
                id
                name
                email
                url
                myshopifyDomain
                description
                fulfillmentServices{
                    id
                    serviceName
                    inventoryManagement
                    callbackUrl
                    type
                    location{
                        id
                        name
                        createdAt
                        isActive
                        fulfillsOnlineOrders
                        address{
                            phone
                            address_line_1: address1
                            address_line_2: address2
                            locality: city
                            administrative_area: province
                            postal_code: zip
                            country_code: countryCode
                        }
                    }
                }
                billingAddress {
                    company_name: company
                    address_line_1: address1
                    address_line_2: address2
                    locality: city
                    administrative_area: province
                    postal_code: zip
                    country_code: countryCodeV2
                }
              }
            }
            QUERY;


            $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
                'json' => [
                    'query' => $query
                ]
            ]);


            if (!empty($response['errors']) || !isset($response['body'])) {
                return ['fail', []];
            }


            $body = $response['body']->toArray();


            if ($shopifyShopData = Arr::get($body, 'data.shop')) {
                // Extract company_name from billingAddress and add it at the same level as url
                if (isset($shopifyShopData['billingAddress']) && isset($shopifyShopData['billingAddress']['company_name'])) {
                    $shopifyShopData['company_name'] = $shopifyShopData['billingAddress']['company_name'];
                    unset($shopifyShopData['billingAddress']['company_name']);
                }


                return ['ok', $shopifyShopData];
            }

            return ['fail', []];
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return null;
        }
    }

}
