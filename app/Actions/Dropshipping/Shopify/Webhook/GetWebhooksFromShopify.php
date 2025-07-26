<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Webhook;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

class GetWebhooksFromShopify extends OrgAction
{
    use WithActionUpdate;


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
                      ... on WebhookEventBridgeEndpoint {
                        arn
                      }
                      ... on WebhookPubSubEndpoint {
                        pubSubProject
                        pubSubTopic
                      }
                    }
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
                return [true, []];
            }

            $webhookIds = [];
            foreach ($webhooks as $webhook) {
                $webhookIds[] = $webhook['node']['id'];
            }

            return [true, $webhookIds];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error fetching webhook IDs: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:get-webhook-ids {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        list($success, $results) = $this->handle($customerSalesChannel->user);

        if (!$success) {
            $command->error($results);
            return;
        }

        if (empty($results)) {
            $command->info("No webhook subscriptions found.");
            return;
        }

        // Output results in table format
        $tableData = [];
        foreach ($results as $index => $webhookId) {
            $tableData[] = [
                'index' => $index + 1,
                'id' => $webhookId
            ];
        }

        $command->table(['#', 'Webhook ID'], $tableData);

        // Summary
        $totalWebhooks = count($results);
        $command->info("\nTotal webhook subscriptions: $totalWebhooks");
    }
}
