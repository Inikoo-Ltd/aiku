<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Events\UploadProductToEbayProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class RequestApiUploadProductEbay extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio)
    {
        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->images as $image) {
                    $images[] = [
                        'src' => GetImgProxyUrl::run($image->getImage()->extension('jpg'))
                    ];
                }
            }

            $ebayProduct = [
                'sku' => $product->code,
                'quantity' => $product->available_quantity,
                'title' => $portfolio->customer_product_name,
                'description' => $portfolio->customer_description,
                'aspects' => Arr::get($product, 'attributes', []),
                'imageUrls' => $images
            ];

            $result = $ebayUser->storeProduct($ebayProduct);

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => $ebayProduct['sku'],
            ]);

            UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
        }
    }
}
