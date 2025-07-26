<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Webhook;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexShopifyUserWebhooks
{
    use AsAction;

    public function handle(ShopifyUser $shopifyUser): array
    {
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        try {
            // GraphQL query to get all webhook subscriptions
            $query = <<<'QUERY'
            {
              webhookSubscriptions(first: 100) {
                edges {
                  node {
                    id
                    topic
                    endpoint {
                      __typename
                      ... on WebhookHttpEndpoint {
                        callbackUrl
                      }
                    }
                    format
                    createdAt
                    updatedAt
                  }
                }
              }
            }
            QUERY;

            $response = $client->request($query);

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: ' . json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();
            $webhooks = $body['data']['webhookSubscriptions']['edges'] ?? [];

            if (empty($webhooks)) {
                return [true, 'No webhook subscriptions found'];
            }

            $formattedWebhooks = [];
            foreach ($webhooks as $webhook) {
                $node = $webhook['node'];
                $formattedWebhooks[] = [
                    'id' => $node['id'],
                    'topic' => $node['topic'],
                    'callbackUrl' => $node['endpoint']['callbackUrl'] ?? null,
                    'format' => $node['format'],
                    'createdAt' => $node['createdAt'],
                    'updatedAt' => $node['updatedAt']
                ];
            }

            return [true, $formattedWebhooks];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error fetching webhook subscriptions: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:webhooks {customerSalesChannel}';
    }


    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        list($success, $results) = $this->handle($customerSalesChannel->user);

        if (!is_array($results)) {
            $command->info($results);
            return;
        }

        $tableData = [];
        $counter = 1;

        foreach ($results as $webhook) {
            $tableData[] = [
                'counter' => $counter,
                'topic' => $webhook['topic'] ?? 'Unknown',
                'callbackUrl' => $webhook['callbackUrl'] ?? 'N/A',
                'format' => $webhook['format'] ?? 'N/A',
                'id' => $webhook['id'] ?? 'N/A',
            ];
            $counter++;
        }

        if (empty($tableData)) {
            $command->info("No webhook subscriptions found.");
            return;
        }

        // Output results in table format
        $this->table(
            ['#', 'Topic', 'Callback URL', 'Format', 'ID'],
            $tableData,
            $command
        );

        // Summary
        $totalWebhooks = count($results);
        $command->info("\nTotal webhook subscriptions: $totalWebhooks");
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows, Command $command): void
    {
        $command->table($headers, $rows);
    }
}
