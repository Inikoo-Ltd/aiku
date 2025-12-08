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
use Illuminate\Validation\ValidationException;
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
            $includeVat = Arr::get($ebayUser->customerSalesChannel->settings, 'tax_category.checked', false);
            $customerPrice = $includeVat ? $portfolio->customer_price : $portfolio->customer_price * 0.8;

            $handleError = function ($result) use ($portfolio, $ebayUser, $logs) {
                if (isset($result['error']) || isset($result['errors'])) {
                    $params = '';
                    if (isset($result['errors'])) {
                        $errorMessage = $result;

                        $params = Arr::get($result['errors'], '0.parameters.0.name');

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
                            'params' => $params,
                            'message' => $displayError
                        ]
                    ]);

                    if (! blank($params)) {
                        throw ValidationException::withMessages(['title' => $displayError]);
                    }

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

            $categories = $ebayUser->getCategorySuggestions($product->department->name);

            $categoryId = Arr::get($categories, 'categorySuggestions.0.category.categoryId');
            $categoryName = Arr::get($categories, 'categorySuggestions.0.category.categoryName');

            if (! $categoryId) {
                $categories = $ebayUser->searchAvailableProducts($product->department->name);

                if ($handleError($categories)) {
                    return;
                }

                $categoryId = Arr::get($categories, 'itemSummaries.0.categories.0.categoryId');
                $categoryName = Arr::get($categories, 'itemSummaries.0.categories.0.categoryName');
            }

            if ($categoryId == '261186') {
                // This force not to use book category
                $categoryId = '29511';
            }

            if ($handleError($categories)) {
                return;
            }

            $categoryAspects = $ebayUser->getItemAspectsForCategory($categoryId);
            $productAttributes = $ebayUser->extractProductAttributes($product, $categoryAspects);

            $aspects = [];
            if (!blank($productAttributes)) {
                $aspects['aspects'] = $productAttributes;
            }

            $height = Arr::get($product->marketing_dimensions, 'h');
            $h = in_array($height, [null, 0]) ? 0.5 : $height;

            $length = Arr::get($product->marketing_dimensions, 'l');
            $l = in_array($length, [null, 0]) ? 0.5 : $length;

            $width = Arr::get($product->marketing_dimensions, 'w');
            $w = in_array($width, [null, 0]) ? 0.5 : $width;

            $availableQuantity = $product->available_quantity;

            if ($availableQuantity >= 50) {
                $availableQuantity = 50; // Based on discuss with tomas we agree to limit 50 only
            }

            $inventoryItem = [
                'sku' => $portfolio->sku,
                'availability' => [
                    'shipToLocationAvailability' => [
                        'availabilityDistributions' => [
                                [
                                    'merchantLocationKey' => $ebayUser->location_key,
                                    'quantity' => $availableQuantity
                                ]
                            ],
                        'quantity' => $availableQuantity
                    ]
                ],
                'condition' => 'NEW',
                'packageWeightAndSize' => [
                    'dimensions' => [
                        'height' => $h,
                        'length' => $l,
                        'unit' => 'CENTIMETER',
                        'width' => $w,
                    ],
                    'weight' => [
                        'unit' => 'KILOGRAM',
                        'value' => (in_array($product->marketing_weight, [null, 0]) ? 100 : $product->marketing_weight) / 1000
                    ]
                ],
                'product' => [
                    'title' => mb_substr($portfolio->customer_product_name, 0, 80),
                    'description' => $descriptions,
                    ...$aspects,
                    'brand' => 'Ancient Wisdom',
                    'mpn' => $product->code,
                    ...$imageUrls
                ]
            ];

            UpdatePortfolio::run($portfolio, [
                'data' => [
                    'product' => [
                        ...Arr::get($inventoryItem, 'product'),
                        'category' => [
                            'id' => $categoryId,
                            'name' => $categoryName
                        ]
                    ]
                ]
            ]);

            $offerExist = $ebayUser->getOffers([
                'sku' => Arr::get($inventoryItem, 'sku')
            ]);

            if (Arr::get($offerExist, 'offers.0.status', '') !== "PUBLISHED") {
                $productResult = $ebayUser->storeProduct($inventoryItem);

                if ($handleError($productResult)) {
                    return;
                }
            }

            if (Arr::get($offerExist, 'offers.0')) {
                $offer = Arr::get($offerExist, 'offers.0');

                $ebayUser->updateOffer(
                    Arr::get($offer, 'offerId'),
                    [
                        'sku' => Arr::get($inventoryItem, 'sku'),
                        'description' => Arr::get($inventoryItem, 'product.description'),
                        'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity'),
                        'price' => $customerPrice,
                        'currency' => $portfolio->shop->currency->code,
                        'category_id' => $categoryId
                    ]
                );
            } else {
                $offer = $ebayUser->storeOffer([
                    'sku' => Arr::get($inventoryItem, 'sku'),
                    'description' => Arr::get($inventoryItem, 'product.description'),
                    'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity'),
                    'price' => $customerPrice,
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
            throw $e;
        }
    }
}
