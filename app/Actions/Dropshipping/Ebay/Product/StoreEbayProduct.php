<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Events\UploadProductToEbayProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEbayProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio)
    {
        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type'   => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $handleError = function ($result) use ($portfolio, $ebayUser, $logs) {
                if (isset($result['error']) || isset($result['errors'])) {
                    if (isset($result['errors'])) {
                        $errorMessage = $result;

                        if (isset($errorMessage['errors'][0]['message'])) {
                            $errorMessage = $errorMessage['errors'][0]['message'];
                        }
                    } else {
                        $errorMessage = $result['error'];
                    }

                    if (is_string($errorMessage) && str_contains($errorMessage, 'eBay API request failed:')) {
                        $jsonPart = str_replace('eBay API request failed: ', '', $errorMessage);
                        $decoded = json_decode($jsonPart, true);

                        if (isset($decoded['errors'][0]['message'])) {
                            $errorMessage = $decoded['errors'][0]['message'];
                        }
                    }

                    $displayError = $ebayUser->getDisplayErrors($errorMessage) ?? $errorMessage;

                    UpdatePlatformPortfolioLog::run($logs, [
                        'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                        'response' => $displayError
                    ]);

                    UpdatePortfolio::make()->action($portfolio, [
                        'upload_warning' => $displayError,
                        'errors_response' => [
                            'message' => $displayError
                        ]
                    ]);

                    return $displayError;
                }

                return false;
            };

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

            $descriptions = mb_substr(strip_tags($portfolio->customer_description), 0, 4000);
            $decoded = html_entity_decode($descriptions, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $noTags = strip_tags($decoded);
            $clean = preg_replace('/[^A-Za-z0-9.,;\'"!? \n-]/', ' ', $noTags);
            $clean = preg_replace('/\s+/', ' ', trim($clean));
            $descriptions = str_replace('(', '', str_replace(')', '', str_replace('.', ' ', $clean)));

            if (!$descriptions) {
                $descriptions = $portfolio->item->name;
            }

            $categories = $ebayUser->getCategorySuggestions($product->family->name);
            $categoryId = Arr::get($categories, 'categorySuggestions.0.category.categoryId');

            if (! $categoryId) {
                $categories = $ebayUser->searchAvailableProducts($product->family->name);

                if ($handleError($categories)) {
                    return;
                }

                $categoryId = Arr::get($categories, 'itemSummaries.0.categories.0.categoryId');
            }

            $categoryAspects = $ebayUser->getItemAspectsForCategory($categoryId);
            $productAttributes = $ebayUser->extractProductAttributes($product, $categoryAspects);

            $aspects = [];
            if (!blank($productAttributes)) {
                $aspects = [
                    'aspects' =>  $productAttributes,
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
                    'description' => $descriptions,
                    ...$aspects,
                    'brand' => 'AncientWisdom',
                    'mpn' => $product->code,
                    ...$imageUrls
                ]
            ];

            $productResult = $ebayUser->storeProduct($inventoryItem);

            if ($handleError($productResult)) {
                return;
            }

            if ($handleError($categories)) {
                return;
            }

            $offerExist = $ebayUser->getOffers([
                'sku' => Arr::get($inventoryItem, 'sku')
            ]);

            if (Arr::get($offerExist, 'offers.0')) {
                $offer = Arr::get($offerExist, 'offers.0');

                $ebayUser->updateOffer(
                    Arr::get($offer, 'offerId'),
                    [
                        'sku' => Arr::get($inventoryItem, 'sku'),
                        'description' => Arr::get($inventoryItem, 'product.description'),
                        'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity'),
                        'price' => $portfolio->customer_price,
                        'currency' => $portfolio->shop->currency->code,
                        'category_id' => $categoryId
                    ]
                );
            } else {
                $offer = $ebayUser->storeOffer([
                    'sku' => Arr::get($inventoryItem, 'sku'),
                    'description' => Arr::get($inventoryItem, 'product.description'),
                    'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity'),
                    'price' => $portfolio->customer_price,
                    'currency' => $portfolio->shop->currency->code,
                    'category_id' => $categoryId
                ]);
            }

            if ($handleError($offer)) {
                return;
            }

            $publishedOffer = $ebayUser->publishListing(Arr::get($offer, 'offerId'));

            if ($handleError($publishedOffer)) {
                return;
            }

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($offer, 'offerId'),
                'platform_product_variant_id' => Arr::get($publishedOffer, 'listingId'),
                'upload_warning' => null,
                'errors_response' => []
            ]);

            CheckEbayPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            }

            UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);
        } catch (\Exception $e) {
            UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);
        }
    }
}
