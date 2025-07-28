<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteAllFulfilmentServices
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            return [false, 'No Shopify user found'];
        }

        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        try {
            // GraphQL query to get all fulfillment services
            $query = <<<'QUERY'
            {
              shop {
                fulfillmentServices {
                  id
                  serviceName
                  type
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
                return [false, 'Error in API response: ' . json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            $fulfillmentServices = $body['data']['shop']['fulfillmentServices'] ?? [];

            if (empty($fulfillmentServices)) {
                return [true, 'No fulfillment services found to delete'];
            }

            $results = [];
            $success = true;
            $deleteFulfilmentService = new DeleteFulfilmentService();

            foreach ($fulfillmentServices as $service) {
                $serviceId = $service['id'];
                $serviceName = $service['serviceName'] ?? 'Unknown';

                list($deleteSuccess, $deleteResult) = $deleteFulfilmentService->handle($customerSalesChannel, $serviceId);

                $results[] = [
                    'id' => $serviceId,
                    'name' => $serviceName,
                    'success' => $deleteSuccess,
                    'message' => is_array($deleteResult) ? 'Deleted successfully' : $deleteResult
                ];

                if (!$deleteSuccess) {
                    $success = false;
                }
            }

            return [$success, $results];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error deleting fulfillment services: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:delete_all_fulfilment_service {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        list($success, $results) = $this->handle($customerSalesChannel);

        if (!$success || !is_array($results)) {
            $command->info($results);
            return;
        }

        $tableData = [];
        $counter = 1;

        foreach ($results as $result) {
            $tableData[] = [
                'counter' => $counter,
                'name' => $result['name'] ?? 'Unknown',
                'id' => $result['id'] ?? 'N/A',
                'status' => $result['success'] ? 'Success' : 'Failed',
                'message' => $result['message'] ?? ''
            ];
            $counter++;
        }

        if (empty($tableData)) {
            $command->info("No fulfillment services found to delete.");
            return;
        }

        // Output results in table format
        $this->table(
            ['#', 'Name', 'ID', 'Status', 'Message'],
            $tableData
        );

        // Summary
        $totalServices = count($results);
        $successfulDeletes = count(array_filter($results, function ($result) {
            return $result['success'] ?? false;
        }));

        $command->info("\nSummary: Deleted $successfulDeletes out of $totalServices fulfillment services.");
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows): void
    {
        $width = [];

        // Calculate column widths
        foreach ($headers as $i => $header) {
            $width[$i] = strlen($header);
            foreach ($rows as $row) {
                $width[$i] = max($width[$i], strlen($row[$header] ?? ''));
            }
        }

        // Output headers
        echo "\n";
        foreach ($headers as $i => $header) {
            echo str_pad($header, $width[$i] + 2);
        }
        echo "\n";

        // Output separator
        foreach ($width as $w) {
            echo str_repeat('-', $w + 2);
        }
        echo "\n";

        // Output rows
        foreach ($rows as $row) {
            foreach ($headers as $i => $header) {
                echo str_pad($row[$header] ?? '', $width[$i] + 2);
            }
            echo "\n";
        }
        echo "\n";
    }

}
