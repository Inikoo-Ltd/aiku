<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Events\UploadProductToAmazonProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
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

            $imageUrls = [];
            $images = [];
            if (app()->isProduction()) {
                foreach ($product->images as $image) {
                    $images[] = [
                        'src' => GetImgProxyUrl::run($image->getImage()->extension('jpg'))
                    ];
                }

                $imageUrls = [
                    'imageUrls' => $images
                ];
            }

            $inventoryItem = [
                'sku' => $product->code,
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => $product->available_quantity
                    ]
                ],
                'condition' => 'NEW',
                'product' => [
                    'title' => $portfolio->customer_product_name,
                    'description' => $portfolio->customer_description,
                    ...$imageUrls
                ]
            ];

            $amazonUser->storeProduct($inventoryItem);
            // $amazonUser->storeOffer($amazonProduct);

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => $inventoryItem['sku'],
            ]);

            UploadProductToAmazonProgressEvent::dispatch($amazonUser, $portfolio);
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
        }
    }



}
