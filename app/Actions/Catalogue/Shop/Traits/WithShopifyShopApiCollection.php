<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithShopifyShopApiCollection
{
    protected array $graphqlQueries = [
        'products' => '
            query ($first: Int!) {
                products(first: $first) {
                    edges {
                        node {
                            id
                            title
                            handle
                            description
                            variants(first: 10) {
                                edges {
                                    node {
                                        id
                                        price
                                        sku
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ',
        'orders' => '
            query ($first: Int!) {
                orders(first: $first) {
                    edges {
                        node {
                            id
                            name
                            totalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            customer {
                                id
                                email
                            }
                        }
                    }
                }
            }
        ',
        'shop' => '
            query {
                shop {
                    name
                    primaryDomain {
                        url
                    }
                    billingAddress {
                        address1
                        address2
                        city
                        provinceCode
                        zip
                        countryCodeV2
                    }
                }
            }
        ',
        // Add other GraphQL queries as needed
    ];

    /**
     * Execute a GraphQL query
     *
     * @param string $queryName
     * @param array $variables
     * @return array
     */
    protected function executeGraphQLQuery(string $queryName, array $variables = []): array
    {
        $shopifyClient = $this->getShopifyClient(true);
        $query = $this->graphqlQueries[$queryName] ?? '';

        $response = $shopifyClient->request($query,$variables);

        if (!empty($response['errors']) || !isset($response['body'])) {
            data_set($result, 'error', true);
            return $result;
        }

        return $response['body']->toArray();
    }

    /**
     * Get products with optional variables
     *
     * @param array $variables
     * @return array
     */
    public function getShopifyProducts(array $variables = ['first' => 10]): array
    {
        return $this->executeGraphQLQuery('products', $variables);
    }

    /**
     * Get orders with optional variables
     *
     * @param array $variables
     * @return array
     */
    public function getShopifyOrders(array $variables = ['first' => 10]): array
    {
        return $this->executeGraphQLQuery('orders', $variables);
    }

    /**
     * Get shop information
     *
     * @return array
     */
    public function getShopifyShop(): array
    {
        return $this->executeGraphQLQuery('shop');
    }
}
