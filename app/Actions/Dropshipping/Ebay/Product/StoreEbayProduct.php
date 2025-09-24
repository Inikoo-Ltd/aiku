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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreEbayProduct extends RetinaAction
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
                    $images[] = GetImgProxyUrl::run($image->getImage()->extension('jpg'));
                }
            } else {
                $images[] = Arr::get($product->web_images, 'all.0.gallery.original');
            }

            $imageUrls = [
                'imageUrls' => $images
            ];

            $descriptions = $portfolio->customer_description;

            if (!$descriptions) {
                $descriptions = $portfolio->item->name;
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
                    'description' => $descriptions,
                    'aspects' =>  [
                        'Brand' => [
                            'AncientWisdom'
                        ],
                        'Type' => [
                            $product->department->name.'/'.$product->family->name
                        ],
                    ],
                    'brand' => 'AncientWisdom',
                    'mpn' => $product->code,
                    ...$imageUrls
                ]
            ];

            $handleError = function ($result) use ($portfolio, $ebayUser) {
                if (isset($result['error'])) {
                    $errorMessage = $result['error'];

                    if (is_string($errorMessage) && str_contains($errorMessage, 'eBay API request failed:')) {
                        $jsonPart = str_replace('eBay API request failed: ', '', $errorMessage);
                        $decoded = json_decode($jsonPart, true);

                        if (isset($decoded['errors'][0]['message'])) {
                            $errorMessage = $decoded['errors'][0]['message'];
                        }
                    }

                    UpdatePortfolio::make()->action($portfolio, ['upload_warning' => $errorMessage]);

                    return $errorMessage;
                }
                return false;
            };

            $productResult = $ebayUser->storeProduct($inventoryItem);

            if ($handleError($productResult)) {
                throw ValidationException::withMessages(['message' => $handleError($productResult)]);
            }

            $categories = $ebayUser->getCategorySuggestions($product->family->name);
            if ($handleError($categories)) {
                throw ValidationException::withMessages(['message' => $handleError($categories)]);
            }

            $offerExist = $ebayUser->getOffers([
                'sku' => Arr::get($inventoryItem, 'sku')
            ]);
            if (Arr::get($offerExist, 'offers.0')) {
                $offer = Arr::get($offerExist, 'offers.0');
            } else {
                $offer = $ebayUser->storeOffer([
                    'sku' => Arr::get($inventoryItem, 'sku'),
                    'description' => Arr::get($inventoryItem, 'product.description'),
                    'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity'),
                    'price' => $portfolio->customer_price,
                    'currency' => $portfolio->shop->currency->code,
                    'category_id' => Arr::get($categories, 'categorySuggestions.0.category.categoryId')
                ]);
            }

            if ($handleError($offer)) {
                throw ValidationException::withMessages(['message' => $handleError($offer)]);
            }

            $publishedOffer = $ebayUser->publishListing(Arr::get($offer, 'offerId'));

            if ($handleError($publishedOffer)) {
                throw ValidationException::withMessages(['message' => $handleError($publishedOffer)]);
            }

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($offer, 'offerId'),
                'platform_product_variant_id' => Arr::get($publishedOffer, 'listingId'),
                'upload_warning' => null,
            ]);

            CheckEbayPortfolio::run($portfolio);

            UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);
        } catch (\Exception $e) {
            $portfolio = UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);

            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
            throw $e;
        }
    }
}
