<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 21:18:13 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

trait WithShopifyOrderRetrieval
{
    /**
     * @throws \Throwable
     */
    public function retrieveOrders(ShopifyUser $shopifyUser, string $assignmentStatus)
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
                                        id
                                        createdAt
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
                'first'            => 100,
                'assignmentStatus' => $assignmentStatus,
            ];

            list($status, $response) = $this->doPost($shopifyUser, $query, $variables);

            if (!$status) {
                return $response;
            }

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: '.json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            $fulfillmentOrders = $body['data']['shop']['assignedFulfillmentOrders']['edges'] ?? [];


            return $this->processFulfillmentOrders($shopifyUser, $fulfillmentOrders);
        } catch (\Exception $e) {
            return [false, 'Exception occurred: '.$e->getMessage()];
        }
    }

    /**
     * Process the fulfillment orders - to be implemented by the classes using this trait
     */
    abstract protected function processFulfillmentOrders(ShopifyUser $shopifyUser, array $fulfillmentOrders): array;

    /**
     * @throws \Throwable
     */
    public function executeCommand(Command $command, string $argumentName): void
    {
        $customerSalesChanel = CustomerSalesChannel::where('slug', $command->argument($argumentName))->first();
        $this->handle($customerSalesChanel->user);
    }
}
