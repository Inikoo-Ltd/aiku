<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AssignFulfillmentOrderRequest extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser)
    {
        try {
            $query = <<<'QUERY'
query assignedFulfillmentOrders($first: Int!, $assignmentStatus: FulfillmentOrderAssignmentStatus!) {
  shop {
    assignedFulfillmentOrders(first: $first, assignmentStatus: $assignmentStatus) {
      edges {
        node {
          id
          destination {
            firstName
            lastName
            address1
            city
            province
            zip
            countryCode
            phone
          }
          order {
            customer {
              id
              firstName
              lastName
              email
              phone
            }
          }
          lineItems(first: 10) {
            edges {
              node {
                id
                productTitle
                sku
                remainingQuantity
                lineItem {
                  variant {
                    id
                  }
                  product {
                    id
                  }
                }
              }
            }
          }
          merchantRequests(first: 10, kind: FULFILLMENT_REQUEST) {
            edges {
              node {
                message
              }
            }
          }
        }
      }
    }
  }
}
QUERY;

            $variables = [
                'first'            => 10,
                'assignmentStatus' => 'FULFILLMENT_REQUESTED',
            ];

            $client = $shopifyUser->getShopifyClient();

            $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
                'json' => [
                    'query'     => $query,
                    'variables' => $variables
                ]
            ]);

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: '.json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            // Access the fulfillment orders data
            $fulfillmentOrders = $body['data']['shop']['assignedFulfillmentOrders']['edges'] ?? [];

            foreach ($fulfillmentOrders as $edge) {
                $fulfillmentOrder = $edge['node'];

                if (! isset($fulfillmentOrder['destination'])) {
                    continue;
                }

                AcceptFulfillmentRequest::run($shopifyUser, $fulfillmentOrder);
            }

        } catch (\Exception $e) {
            return [false, 'Exception occurred: ' . $e->getMessage()];
        }
    }
}
