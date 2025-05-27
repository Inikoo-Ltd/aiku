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
use App\Models\Dropshipping\ShopifyUserHasProduct;
use Closure;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Options;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
        $shopifyUser->api()->getOptions()->setGuzzleOptions([
            'http_errors' => false
        ]);

        try {
            $response = $client->request('POST', '/admin/api/2024-04/products.json', $body);
        } catch (\Exception $e) {
            $response = [
                'errors' => true,
                'body' => [
                    'errors' => $e->getMessage()
                ]
            ];
        }

        Log::info('right-after-upload-' .$portfolio->id);

        if ($response['errors']) {
            \Sentry\captureMessage("Product upload failed: " . json_encode(Arr::get($response, 'body')));
        }

        $productShopify = Arr::get($response, 'body.product');

        $inventoryVariants = [];
        foreach (Arr::get($productShopify, 'variants') as $variant) {
            $variant['available_quantity'] = $portfolio->item->available_quantity;
            $inventoryVariants[] = $variant;
        }

        HandleApiInventoryProductShopify::dispatch($shopifyUser, $inventoryVariants);

        if (Arr::get($productShopify, 'id')) {
            ShopifyUserHasProduct::updateOrCreate([
                'shopify_user_id' => $shopifyUser->id,
                'product_type' => $portfolio->item->getMorphClass(),
                'product_id' => $portfolio->item->id,
                'portfolio_id' => $portfolio->id
            ], [
                'shopify_user_id' => $shopifyUser->id,
                'product_type' => $portfolio->item->getMorphClass(),
                'product_id' => $portfolio->item->id,
                'portfolio_id' => $portfolio->id,
                'shopify_product_id' => Arr::get($productShopify, 'id')
            ]);
        }

        $this->update($portfolio, [
            'data' => [
                'api_response' => Arr::get($response, 'body')
            ]
        ]);

        Log::info('end-dispatch-' .$portfolio->id);
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array']
        ];
    }

    public function getJobUniqueId(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body): int
    {
        return rand();
    }

    public static function shopifyApiClosure(): Closure
    {
        return function (Options $opts) {
            $ts = config('shopify-app.api_time_store');
            $ls = config('shopify-app.api_limit_store');
            $sd = config('shopify-app.api_deferrer');

            // Custom Guzzle options
            $opts->setGuzzleOptions(['timeout' => 90.0]);

            return new BasicShopifyAPI(
                $opts,
                new $ts(),
                new $ls(),
                new $sd()
            );
        };
    }
}
