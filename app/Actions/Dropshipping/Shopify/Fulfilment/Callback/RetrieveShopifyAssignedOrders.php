<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;

class RetrieveShopifyAssignedOrders extends OrgAction
{
    use WithShopifyApi;

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
                'first'            => 100,
                'assignmentStatus' => 'FULFILLMENT_REQUESTED',
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

            foreach ($fulfillmentOrders as $edge) {
                $fulfillmentOrder = $edge['node'];


                if (!isset($fulfillmentOrder['destination'])) {
                    RejectShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder['id'], __('Fulfillment request destination not found.'));;
                } else {
                    AcceptShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder);
                }
            }

            return [true, 'Retrieved assigned fulfillment orders'];
        } catch (\Exception $e) {
            return [false, 'Exception occurred: '.$e->getMessage()];
        }
    }

    public string $commandSignature = 'shopify:retrieve_orders {customerSalesChannel}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): void
    {
        $customerSalesChanel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChanel->user);
    }
}
