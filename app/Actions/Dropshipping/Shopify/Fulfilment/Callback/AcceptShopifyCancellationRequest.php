<?php

/*
 * author Louis Perez
 * created on 25-03-2026-08h-50m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Sentry;

class AcceptShopifyCancellationRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * Whether Shopify took the acceptance; a refusal is reported to Sentry and never thrown.
     *
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID, string $message): bool
    {
        try {
            $mutation = <<<'MUTATION'
                    mutation acceptCancellationRequest($id: ID!, $message: String!) {
                        fulfillmentOrderAcceptCancellationRequest(
                            id: $id,
                            message: $message
                        ) {
                            fulfillmentOrder {
                                id
                                status
                                requestStatus
                            }
                            userErrors {
                                field
                                message
                            }
                        }
                    }
            MUTATION;

            $variables = [
                'id'      => $orderID,
                'message' => $message,
            ];

            [$status, $response] = $this->doPost($shopifyUser, $mutation, $variables);

            if (!$status) {
                return false;
            }

            return empty(data_get($response['body']->toArray(), 'data.fulfillmentOrderAcceptCancellationRequest.userErrors'));
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return false;
        }
    }

    public string $commandSignature = 'AcceptShopifyCancellationRequest {shopify_user_id} {order}';

    public function asCommand(Command $command): void
    {
        $orderId = $command->argument('order');
        $shopifyUser = ShopifyUser::find($command->argument('shopify_user_id'));

        $this->handle($shopifyUser, $orderId, 'Accepted');
    }
}
