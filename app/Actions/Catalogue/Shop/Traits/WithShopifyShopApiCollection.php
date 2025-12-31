<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithShopifyShopApiCollection
{
    protected string $baseShopifyUrl;
    protected array $defaultShopifyHeaders = [];
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
        // Add other GraphQL queries as needed
    ];

    /**
     * Initialize the API configuration
     *
     * @return void
     */
    protected function initializeShopifyApi(): void
    {
        $shopDomain = Arr::get($this->settings, 'shopify.shop_domain');
        $this->baseShopifyUrl = "https://{$shopDomain}/admin/api/2025-07/graphql.json";

        $headers = [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => Arr::get($this->settings, 'shopify.access_token')
        ];

        $this->defaultShopifyHeaders = $headers;
    }

    /**
     * Execute a GraphQL query
     *
     * @param string $queryName
     * @param array $variables
     * @return array
     */
    protected function executeGraphQLQuery(string $queryName, array $variables = []): array
    {
        $this->initializeShopifyApi();

        $query = $this->graphqlQueries[$queryName] ?? '';

        $response = Http::withHeaders($this->defaultShopifyHeaders)
            ->post($this->baseShopifyUrl, [
                'query' => $query,
                'variables' => $variables
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        return [
            'success' => false,
            'error' => $response->json()
        ];
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
}
