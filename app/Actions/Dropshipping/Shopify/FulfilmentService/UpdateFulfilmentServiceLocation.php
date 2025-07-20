<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Actions\Dropshipping\Shopify\UpdateShopifyChannelShopData;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateFulfilmentServiceLocation
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser) {
            return [false, 'No Shopify user provided'];
        }

        $locationID = $shopifyUser->shopify_location_id;
        if (!$locationID) {
            return [false, 'No location ID provided'];
        }

        $warehouse = $customerSalesChannel->organisation->warehouses()->first();
        if (!$warehouse) {
            return [false, 'No warehouse found'];
        }

        $address = $warehouse->address;
        if (!$address) {
            return [false, 'No address found for warehouse'];
        }

        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        try {
            // GraphQL mutation to update a location
            $mutation = <<<'MUTATION'
            mutation locationEdit($id: ID!, $input: LocationEditInput!) {
              locationEdit(id: $id, input: $input) {
                location {
                  id
                  name
                  address {
                    address1
                    address2
                    city
                    province
                    zip
                    country
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            $variables = [
                'id' => $locationID,
                'input' => [
                    'address' => [
                        'address1' => $address->address_line_1 ?? '',
                        'address2' => $address->address_line_2 ?? '',
                        'city' => $address->locality ?? '',
                        'zip' => $address->postal_code ?? '',
                        'countryCode' => $address->country_code ?? ''
                    ]
                ]
            ];

            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: ' . json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['locationEdit']['userErrors'])) {
                $errors = $body['data']['locationEdit']['userErrors'];
                return [false, 'User errors: ' . json_encode($errors)];
            }

            // Return the updated location
            $location = $body['data']['locationEdit']['location'] ?? null;

            if (!$location) {
                return [false, 'No location data in response'];
            }
            UpdateShopifyChannelShopData::run($customerSalesChannel);


            return [true, $location];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error editing location: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:update_fulfilment_service_location {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        list($success, $result) = $this->handle($customerSalesChannel);

        if (!is_array($result)) {
            $command->info($result);
            return;
        }

        if (!$success) {
            $command->error("Failed to update location: " . json_encode($result));
            return;
        }

        // Display the updated location information
        $address = $result['address'] ?? [];

        $tableData = [
            ['ID', $result['id'] ?? 'N/A'],
            ['Name', $result['name'] ?? 'N/A'],
            ['Address Line 1', $address['address1'] ?? 'N/A'],
            ['Address Line 2', $address['address2'] ?? 'N/A'],
            ['City', $address['city'] ?? 'N/A'],
            ['Province/State', $address['province'] ?? 'N/A'],
            ['Postal/ZIP Code', $address['zip'] ?? 'N/A'],
            ['Country', $address['country'] ?? 'N/A']
        ];

        $command->table(['Field', 'Value'], $tableData);
        $command->info("\nLocation updated successfully.");
    }

}
