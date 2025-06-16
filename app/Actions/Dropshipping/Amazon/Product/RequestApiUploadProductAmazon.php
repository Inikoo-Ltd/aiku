<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Events\UploadProductToAmazonProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class RequestApiUploadProductAmazon extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(AmazonUser $amazonUser, Portfolio $portfolio)
    {
        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $productData = [
                'title' => $portfolio->customer_product_name,
                'description' => $portfolio->customer_description,
                'product_type' => $product->type ?? null,
                'brand' => $product->brand ?? null,
                'bullet_points' => $product->bullet_points ?? [],
                'dimensions' => [
                    'weight' => $product->gross_weight,
                ],
                'quantity' => $product->available_quantity,
                'price' => $product->price,
                'currency' => $product->currency,
                'images' => app()->isProduction()
                    ? collect($product->images)->map(function ($image) {
                        return GetImgProxyUrl::run($image->getImage()->extension('jpg'));
                    })->toArray()
                    : [],
            ];

            $product = $amazonUser->createFullProduct($product->code, $productData);

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($product, 'id'),
            ]);

            UploadProductToAmazonProgressEvent::dispatch($amazonUser, $portfolio);
        } catch (\Exception $e) {
            Log::info("Failed to upload product due to: " . $e->getMessage());
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
        }
    }



}
