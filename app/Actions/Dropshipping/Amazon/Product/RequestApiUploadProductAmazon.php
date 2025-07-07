<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon\Product;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
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
                'id' => $portfolio->id,
                'code' => $portfolio->code,
                'sku' => 'SKU-'.$product->code,
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

            $product = $amazonUser->createFullProduct(Arr::get($productData, 'sku'), $productData);

            if (Arr::get($product, 'status') === "ACCEPTED") {
                $portfolio = UpdatePortfolio::run($portfolio, [
                    'platform_product_id' => Arr::get($product, 'sku'),
                ]);


                if (! in_array($amazonUser->customerSalesChannel->state, [CustomerSalesChannelStateEnum::READY->value, CustomerSalesChannelStateEnum::PORTFOLIO_ADDED->value])) {
                    UpdateCustomerSalesChannel::run($amazonUser->customerSalesChannel, [
                        'state' => CustomerSalesChannelStateEnum::PORTFOLIO_ADDED
                    ]);
                }
            } elseif (Arr::get($product, 'status') === "INVALID") {
                $portfolio = UpdatePortfolio::run($portfolio, [
                    'errors_response' => Arr::get($product, 'issues', []),
                ]);
            }

            UploadProductToAmazonProgressEvent::dispatch($amazonUser, $portfolio);
        } catch (\Exception $e) {
            Log::info("Failed to upload product due to: " . $e->getMessage());
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
        }
    }



}
