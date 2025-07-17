<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Jul 2025 17:36:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;

trait WithExternalPlatforms
{
    public function hasCredentials(CustomerSalesChannel $customerSalesChannel): bool
    {
        $credentialsOk = true;
        $platformUser  = $customerSalesChannel->user;

        if ($platformUser instanceof ShopifyUser) {
            $settings = $platformUser->settings ?? [];
            if (empty($settings) || !Arr::exists($settings, 'webhooks') || empty(Arr::get($settings, 'webhooks', []))) {
                $credentialsOk = false;
            }
        } elseif ($platformUser instanceof WooCommerceUser) {
            $settings = $platformUser->settings ?? [];

            if (empty($settings['credentials']) || empty($settings['webhooks'])) {
                $credentialsOk = false;
            }
        } elseif ($platformUser instanceof EbayUser) {
            $settings = $platformUser->settings ?? [];

            if (empty($settings['credentials'])) {
                $credentialsOk = false;
            }
        }

        return $credentialsOk;
    }

    public function getShopifyConnectionStatus(CustomerSalesChannel $customerSalesChannel): array
    {
        $error = '';
        if ($customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            return [
                CustomerSalesChannelConnectionStatusEnum::NO_APPLICABLE,
                ''
            ];
        } else {
            $connectionStatus = $customerSalesChannel->connection_status;
            if (!$connectionStatus) {
                $connectionStatus = CustomerSalesChannelConnectionStatusEnum::PENDING;
            }

            if (!$customerSalesChannel->user) {
                return [
                    CustomerSalesChannelConnectionStatusEnum::ERROR,
                    'No platform User'
                ];
            }

            if (!$this->hasCredentials($customerSalesChannel)) {



                return [
                    CustomerSalesChannelConnectionStatusEnum::ERROR,
                    'No webhooks configured'
                ];
            }

            $platform = $customerSalesChannel->platform;
            if ($platform->type == PlatformTypeEnum::SHOPIFY) {
                /** @var ShopifyUser $shopifyUser */
                $shopifyUser = $customerSalesChannel->user;

                list($isConnectedToShopify, $error) = $this->checkIfConnectedToShopify($shopifyUser);
                if ($isConnectedToShopify != null) {
                    $connectionStatus = $isConnectedToShopify;
                }
            }

            return [$connectionStatus, $error];
        }
    }


    public function checkIfConnectedToShopify(ShopifyUser $shopifyUser): array
    {
        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [
                CustomerSalesChannelConnectionStatusEnum::ERROR,
                'No shopify client'
            ];
        }

        try {
            // GraphQL query to get shop data
            $query = <<<'QUERY'
            {
              shop {
                id
                name
                email
                url
                myshopifyDomain
                description
                fulfillmentServices{
                    id
                    inventoryManagement
                    location{
                        id
                        name
                        createdAt
                        isActive
                        fulfillsOnlineOrders
                        address{
                            phone
                            address_line_1: address1
                            address_line_2: address2
                            locality: city
                            administrative_area: province
                            postal_code: zip
                            country_code: countryCode
                        }
                    }
                }
                billingAddress {
                    company_name: company
                    address_line_1: address1
                    address_line_2: address2
                    locality: city
                    administrative_area: province
                    postal_code: zip
                    country_code: countryCodeV2
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
                if ($response['status'] == '404') {
                    $errorData = [
                        'datetime' => now(),
                        'status'   => false,
                        'data'     => [
                            'latest_error' => [
                                'status'     => $response['status'],
                                'errors'     => $response['body'],
                                'msg'        => 'Shop '.$shopifyUser->name.'  not found',
                                'msg_detail' => $response['exception']->getMessage()
                            ]
                        ]
                    ];
                } elseif ($response['status'] == '401') {
                    $errorData = [
                        'datetime' => now(),
                        'status'   => false,
                        'data'     => [
                            'latest_error' => [
                                'status'     => $response['status'],
                                'errors'     => $response['body'],
                                'msg'        => 'Shop '.$shopifyUser->name.'  wrong credentials',
                                'msg_detail' => $response['exception']->getMessage()
                            ]
                        ]
                    ];
                } else {
                    dd($response['status'], $response['errors'], $response['body']);
                }

                $this->update($shopifyUser, [
                    'status' => false,
                    'data'   => [
                        'latest_error' => $errorData
                    ]
                ]);


                return [null, $errorData['data']['latest_error']['msg']];

            }

            if (!isset($response['body'])) {
                dd($response);

                return [null, 'No body response'];

            }


            $body = $response['body']->toArray();


            if ($shopifyShopData = Arr::get($body, 'data.shop')) {
                // Extract company_name from billingAddress and add it at the same level as url
                if (isset($shopifyShopData['billingAddress']) && isset($shopifyShopData['billingAddress']['company_name'])) {
                    $shopifyShopData['company_name'] = $shopifyShopData['billingAddress']['company_name'];
                    unset($shopifyShopData['billingAddress']['company_name']);
                }

                $this->update($shopifyUser, [
                    'status' => true,
                    'data'   => [
                        'shopify_shop' => $shopifyShopData,
                        'latest_error' => null
                    ]
                ]);

                return [CustomerSalesChannelConnectionStatusEnum::CONNECTED, ''];
            }

            return [CustomerSalesChannelConnectionStatusEnum::DISCONNECTED, ''];
        } catch (\Exception $e) {
            print "ERROR\n";
            print_r($e->getMessage());

            return [null, $e->getMessage()];
            ;
        }
    }


}
