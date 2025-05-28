<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RequestApiUploadProductToShopify extends RetinaAction implements ShouldBeUnique
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $jobQueue = 'shopify';

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body): void
    {
        $productShopify = [];
        $client = $shopifyUser->getShopifyClient();

        $response = $client->request('POST', '/admin/api/2024-04/products.json', $body);
        if ($response['errors']) {
            $this->update($portfolio, [
                'errors_response' => Arr::get($response, 'body.errors')
            ]);

            \Sentry::captureMessage("Product upload failed: " . json_encode(Arr::get($response, 'body')));
        } else {
            $productShopify = Arr::get($response, 'body.product');
        }

        $inventoryVariants = [];
        foreach (Arr::get($productShopify, 'variants') as $variant) {
            $variant['available_quantity'] = $portfolio->item->available_quantity;
            $inventoryVariants[] = $variant;
        }

        HandleApiInventoryProductShopify::dispatch($shopifyUser, $inventoryVariants);

        $this->update($portfolio, [
            'shopify_product_id' => Arr::get($productShopify, 'id')
        ]);
    }

    public function getJobUniqueId(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body): int
    {
        return rand();
    }
}
