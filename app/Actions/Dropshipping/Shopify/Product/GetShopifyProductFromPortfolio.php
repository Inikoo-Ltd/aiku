<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:25:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Exceptions\Api\ShopifyApiResponseException;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class GetShopifyProductFromPortfolio
{
    use AsAction;


    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio)
    {

        if(!$portfolio->platform_handle){
            return null;
        }

        try {
            $response = $shopifyUser->getShopifyClient()->request('GET', '/admin/api/2025-04/products.json', [
                'handle' => $portfolio->platform_handle,
                'limit'  => 1
            ]);

            if (Arr::get($response, 'errors')) {
                Sentry::captureMessage('Error in GetShopifyProductFromPortfolio: >'.$portfolio->platform_handle.'<');

                return null;
            }

            $products = Arr::get($response, 'body.products', []);

            if (empty($products)) {
                $product = null;
            } else {
                $product = $products[0];
            }

            return $product;
        } catch (ShopifyApiResponseException $e) {
            Sentry::captureMessage(
                'Shopify API Error in GetShopifyProductFromPortfolio: '.$e->getMessage(),
                ['response_data' => $e->getResponseData()]
            );

            return null;
        }
    }

    public string $commandSignature = 'shopify:product {customerSalesChannel} {portfolio}';


    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $portfolio            = $customerSalesChannel->portfolios()->where('slug', $command->argument('portfolio'))->firstOrFail();


        $response = $this->handle($customerSalesChannel->user, $portfolio);

        print_r($response);

    }


}
