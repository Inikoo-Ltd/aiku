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
use Illuminate\Validation\ValidationException;
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
        $client = $shopifyUser->api()->getRestClient();

        $response = $client->request('POST', '/admin/api/2024-04/products.json', $body);

        if ($response['errors']) {
            throw ValidationException::withMessages(['Internal server error, please wait a while']);
        }

        $productShopify = Arr::get($response, 'body.product');

        $inventoryVariants = [];
        foreach (Arr::get($productShopify, 'variants') as $variant) {
            $variant['available_quantity'] = $portfolio->item->available_quantity;
            $inventoryVariants[] = $variant;
        }

        HandleApiInventoryProductShopify::run($shopifyUser, $inventoryVariants);

        $this->update($portfolio->shopifyPortfolio, [
            'shopify_product_id' => Arr::get($productShopify, 'id')
        ]);

        $this->update($portfolio, [
            'data' => []
        ]);
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array']
        ];
    }
    public function getJobUniqueId(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body): string
    {
        return rand();
    }
}
