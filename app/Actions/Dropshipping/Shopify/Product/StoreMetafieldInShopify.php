<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreMetafieldInShopify
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser): bool
    {
        try {
            $mutation = '
            mutation metafieldDefinitionCreate($definition: MetafieldDefinitionInput!) {
                metafieldDefinitionCreate(definition: $definition) {
                    createdDefinition {
                        id
                        name
                        namespace
                        key
                        type {
                            name
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

            $variables = [
                'definition' => [
                    'name' => 'Custom ID',
                    'namespace' => 'custom',
                    'key' => 'id',
                    'description' => 'Custom identifier for products',
                    'type' => 'number_integer',
                    'ownerType' => 'PRODUCT'
                ]
            ];

            $response = $shopifyUser->getShopifyClient()->request('POST', '/admin/api/2024-04/graphql.json', [
                'json' => [
                    'query' => $mutation,
                    'variables' => $variables
                ]
            ]);

            if (Arr::get($response, 'errors')) {
                throw new \Exception(Arr::get($response, 'errors'));
            }

            return true;
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error in CheckExistPortfolioInShopify: ' . $e->getMessage(), 'error');

            return false;
        }
    }
}
