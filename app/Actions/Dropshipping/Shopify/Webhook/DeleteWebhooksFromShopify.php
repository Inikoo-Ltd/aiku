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

class DeleteWebhooksFromShopify extends OrgAction
{
    use WithActionUpdate;


    public function handle(ShopifyUser $shopifyUser): array
    {

        list($status, $result) = GetWebhooksFromShopify::run($shopifyUser);

        if (!$status) {
            return [false, $result];
        }

        $webhooks = $result;
        $deletedCount = 0;
        $errors = [];

        foreach ($webhooks as $webhookID) {
            list($deleteStatus, $deleteResult) = $this->deleteWebhook($shopifyUser, $webhookID);

            if ($deleteStatus) {
                $deletedCount++;
            } else {
                $errors[] = "Failed to delete webhook $webhookID: $deleteResult";
            }
        }

        if (!empty($errors)) {
            return [false, $errors];
        }

        return [true, ['deleted_count' => $deletedCount]];
    }

    private function deleteWebhook(ShopifyUser $shopifyUser, string $webhookID): array
    {
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        try {
            // GraphQL mutation to delete a webhook subscription
            $mutation = <<<'MUTATION'
            mutation webhookSubscriptionDelete($id: ID!) {
              webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            $variables = [
                'id' => $webhookID
            ];

            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: ' . json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['webhookSubscriptionDelete']['userErrors'])) {
                $errors = $body['data']['webhookSubscriptionDelete']['userErrors'];
                return [false, 'User errors: ' . json_encode($errors)];
            }

            // Return the deleted webhook subscription ID
            $deletedId = $body['data']['webhookSubscriptionDelete']['deletedWebhookSubscriptionId'] ?? null;

            if (!$deletedId) {
                return [false, 'No deleted ID in response'];
            }

            return [true, ['id' => $deletedId]];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error deleting webhook subscription: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:delete-webhooks {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $command->info("Deleting webhooks for customer sales channel: $customerSalesChannel->slug");

        list($success, $results) = $this->handle($customerSalesChannel->user);

        if (!$success) {
            if (is_array($results)) {
                foreach ($results as $error) {
                    $command->error($error);
                }
            } else {
                $command->error($results);
            }
            return;
        }

        $deletedCount = $results['deleted_count'] ?? 0;
        $command->info("Successfully deleted $deletedCount webhook(s).");
    }
}
