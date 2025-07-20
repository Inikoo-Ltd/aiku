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
use Sentry;

class StoreWebhooksToShopify extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        $webhooks = [
            [
                'topic' => 'APP_UNINSTALLED',
                'webhookSubscription' => [
                    'uri' => "https://aiku.test/webhooks/shopify-user/{$shopifyUser->id}/app/uninstalled",
                    'format' => 'JSON',
                ]
            ],
        ];

        $results = [];
        $success = true;

        foreach ($webhooks as $webhook) {
            try {
                // GraphQL mutation to create a webhook subscription
                $mutation = <<<'MUTATION'
                mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
                  webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                    webhookSubscription {
                      id
                      topic
                      endpoint {
                        __typename
                        ... on WebhookHttpEndpoint {
                          callbackUrl
                        }
                      }
                      format
                    }
                    userErrors {
                      field
                      message
                    }
                  }
                }
                MUTATION;

                $variables = [
                    'topic' => $webhook['topic'],
                    'webhookSubscription' => [
                        'callbackUrl' => $webhook['webhookSubscription']['uri'],
                        'format' => $webhook['webhookSubscription']['format']
                    ]
                ];

                $response = $client->request($mutation, $variables);

                if (!empty($response['errors']) || !isset($response['body'])) {
                    $errorMessage = 'Error in API response: ' . json_encode($response['errors'] ?? []);
                    $results[] = [
                        'topic' => $webhook['topic'],
                        'callbackUrl' => $webhook['webhookSubscription']['uri'],
                        'success' => false,
                        'message' => $errorMessage
                    ];
                    $success = false;
                    continue;
                }

                $body = $response['body']->toArray();

                // Check for user errors in the response
                if (!empty($body['data']['webhookSubscriptionCreate']['userErrors'])) {
                    $errors = $body['data']['webhookSubscriptionCreate']['userErrors'];
                    $errorMessage = 'User errors: ' . json_encode($errors);
                    $results[] = [
                        'topic' => $webhook['topic'],
                        'callbackUrl' => $webhook['webhookSubscription']['uri'],
                        'success' => false,
                        'message' => $errorMessage
                    ];
                    $success = false;
                    continue;
                }

                // Get the created webhook subscription
                $webhookSubscription = $body['data']['webhookSubscriptionCreate']['webhookSubscription'] ?? null;

                if (!$webhookSubscription) {
                    $results[] = [
                        'topic' => $webhook['topic'],
                        'callbackUrl' => $webhook['webhookSubscription']['uri'],
                        'success' => false,
                        'message' => 'No webhook subscription in response'
                    ];
                    $success = false;
                    continue;
                }

                $results[] = [
                    'id' => $webhookSubscription['id'],
                    'topic' => $webhookSubscription['topic'],
                    'callbackUrl' => $webhookSubscription['endpoint']['callbackUrl'] ?? null,
                    'format' => $webhookSubscription['format'],
                    'success' => true,
                    'message' => 'Webhook created successfully'
                ];
            } catch (\Exception $e) {
                Sentry::captureMessage('Error creating webhook subscription: ' . $e->getMessage());
                $results[] = [
                    'topic' => $webhook['topic'],
                    'callbackUrl' => $webhook['webhookSubscription']['uri'],
                    'success' => false,
                    'message' => 'Exception: ' . $e->getMessage()
                ];
                $success = false;
            }
        }

        return [$success, $results];



    }

    public function getCommandSignature(): string
    {
        return 'shopify:create-webhooks {customerSalesChannel}';
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

        foreach ($results as $result) {
            $tableData[] = [
                'counter' => $counter,
                'topic' => $result['topic'] ?? 'Unknown',
                'callbackUrl' => $result['callbackUrl'] ?? 'N/A',
                'format' => $result['format'] ?? 'N/A',
                'status' => $result['success'] ? 'Success' : 'Failed',
                'message' => $result['message'] ?? '',
                'id' => $result['id'] ?? 'N/A',
            ];
            $counter++;
        }

        if (empty($tableData)) {
            $command->info("No webhook subscriptions created.");
            return;
        }

        // Output results in table format
        $this->table(
            ['#', 'Topic', 'Callback URL', 'Format', 'Status', 'Message', 'ID'],
            $tableData,
            $command
        );

        // Summary
        $totalWebhooks = count($results);
        $successfulCreates = count(array_filter($results, function ($result) {
            return $result['success'] ?? false;
        }));

        $command->info("\nSummary: Created $successfulCreates out of $totalWebhooks webhook subscriptions.");
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows, Command $command): void
    {
        $command->table($headers, $rows);
    }

}
