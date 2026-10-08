<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Ebay\UpdateEbayUser;
use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
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
    use WithPortfolioErrorResponse;

    private const string FALLBACK_CATEGORY_ID = '29511';

    private const int MAX_MISSING_ASPECT_ATTEMPTS = 6;

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, Portfolio $portfolio): Portfolio
    {
        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;
            $customerPrice = $portfolio->customer_price;

            $customerSalesChannel = $ebayUser->customerSalesChannel;

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

                    $displayError  = $ebayUser->getDisplayErrors($errorMessage) ?? $errorMessage;
                    $errorResponse = $this->portfolioErrorResponse($displayError) ?? [];

                    UpdatePlatformPortfolioLog::dispatch($logs, [
                        'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                        'response' => $displayError
                    ]);

                    UpdatePortfolio::make()->action($portfolio, [
                        'upload_warning' => Arr::get($errorResponse, 'message'),
                        'errors_response' => ['params' => $params] + $errorResponse
                    ]);

                    return $displayError ?: true;
                }

                return false;
            };

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->orderedImages() as $image) {
                    $images[] = GetImgProxyUrl::run($image->getImage()->extension('jpg')->resize(1600, 1600));
                }
            } else {
                $images[] = Arr::get($product->web_images, 'all.0.gallery.original');
            }

            $imageUrls = [
                'imageUrls' => $images
            ];

            $descriptions = mb_substr($portfolio->customer_description, 0, 4000);

            if (!$descriptions) {
                $descriptions = $portfolio->item->name;
            }

            // $descriptions = $ebayUser->getFormattedDescriptions($descriptions);

            $family = $product->family?->name;

            if (!$family) {
                $family = $product->name;
            }

            $categoryId = null;
            $categoryName = null;

            $categoryKeywords = array_filter([
                $family,
                $product->subDepartment?->name,
                $product->department?->name
            ]);

            foreach ($categoryKeywords as $categoryKeyword) {
                $categories = $ebayUser->getCategorySuggestions($categoryKeyword);

                $suggestedId = Arr::get($categories, 'categorySuggestions.0.category.categoryId');
                $suggestedName = Arr::get($categories, 'categorySuggestions.0.category.categoryName');

                if (!$suggestedId || $suggestedName === 'Other') {
                    continue;
                }

                if (!$ebayUser->categoryAcceptsNewCondition($suggestedId)) {
                    continue;
                }

                $categoryId = $suggestedId;
                $categoryName = $suggestedName;

                break;
            }

            if (!$categoryId) {
                $categories = $ebayUser->searchAvailableProducts($family);

                if ($handleError($categories)) {
                    return $portfolio;
                }

                $categoryId = Arr::get($categories, 'itemSummaries.0.categories.0.categoryId');
                $categoryName = Arr::get($categories, 'itemSummaries.0.categories.0.categoryName');
            }

            $categoryBodySoap = '180924';
            $includedCategories = ['261186', '116113'];
            if (!$product->barcode && $categoryId === $categoryBodySoap) {
                $includedCategories[] = $categoryBodySoap;
            }

            if (in_array($categoryId, $includedCategories)) {
                // This force not to use book category
                $categoryId = self::FALLBACK_CATEGORY_ID;
                $categoryName = null;
            }

            if ($handleError($categories)) {
                return $portfolio;
            }

            if (!$categoryId || !$ebayUser->categoryAcceptsNewCondition($categoryId)) {
                $categoryId = self::FALLBACK_CATEGORY_ID;
                $categoryName = null;
            }

            $categoryAspects = $ebayUser->getItemAspectsForCategory($categoryId);
            $productAttributes = $ebayUser->extractProductAttributes($product, $categoryAspects);

            $aspects = [];
            if (!blank($productAttributes)) {
                $aspects['aspects'] = $productAttributes;
            }

            $ean = [];
            if (!blank($product->barcode)) {
                $ean = [
                    'ean' => [$product->barcode]
                ];
            }

            $height = Arr::get($product->marketing_dimensions, 'h');
            $h = in_array($height, [null, 0]) ? 0.5 : $height;

            $length = Arr::get($product->marketing_dimensions, 'l');
            $l = in_array($length, [null, 0]) ? 0.5 : $length;

            $width = Arr::get($product->marketing_dimensions, 'w');
            $w = in_array($width, [null, 0]) ? 0.5 : $width;

            $availableQuantity = $product->available_quantity;

            if ($availableQuantity < 1) {
                $availableQuantity = 1;
            }

            if ($customerSalesChannel->max_quantity_advertise > 0) {
                $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
            }

            $sku = $portfolio->sku;
            if ($product->is_bundle) {
                $sku = $product->code;
            }

            $inventoryItem = [
                'sku' => $sku,
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
                        'value' => ($product->gross_weight ?: $product->marketing_weight ?: 100) / 1000
                    ]
                ],
                'product' => [
                    'title' => mb_substr($portfolio->customer_product_name, 0, 80),
                    'description' => $descriptions,
                    ...$ean,
                    ...$aspects,
                    'brand' => 'Ancient Wisdom',
                    'mpn' => $product->code,
                    ...$imageUrls,
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

            if ($handleError($ebayUser->storeProduct($inventoryItem))) {
                return $portfolio;
            }

            if (Arr::get($offerExist, 'offers.0')) {
                $offer = Arr::get($offerExist, 'offers.0');

                $offerData = [
                    'sku' => Arr::get($inventoryItem, 'sku'),
                    'description' => Arr::get($inventoryItem, 'product.description'),
                    'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity', 1),
                    'currency' => $portfolio->shop->currency->code,
                    'use_channel_policies' => true
                ];

                if (self::sendsOurPrice($portfolio)) {
                    $offerData['price'] = $customerPrice;
                }

                $isLive = Arr::get($offer, 'status') === 'PUBLISHED' && filled(Arr::get($offer, 'categoryId'));

                if ($isLive) {
                    $categoryId = Arr::get($offer, 'categoryId');
                } else {
                    $offerData['category_id'] = $categoryId;
                }

                $updatedOffer = $ebayUser->updateOffer(Arr::get($offer, 'offerId'), $offerData);

                if ($handleError($updatedOffer)) {
                    return $portfolio;
                }
            } else {
                $offer = $ebayUser->storeOffer([
                    'sku' => Arr::get($inventoryItem, 'sku'),
                    'description' => Arr::get($inventoryItem, 'product.description'),
                    'quantity' => Arr::get($inventoryItem, 'availability.shipToLocationAvailability.quantity', 1),
                    'price' => $customerPrice,
                    'currency' => $portfolio->shop->currency->code,
                    'category_id' => $categoryId
                ]);
            }

            if ($handleError($offer)) {
                return $portfolio;
            }

            if (blank(Arr::get($offer, 'offerId')) && $handleError(['error' => 'eBay did not return an offer for this product, try uploading it again.'])) {
                return $portfolio;
            }

            if (Arr::get($customerSalesChannel->settings, 'upload_as_draft')) {
                $portfolio = UpdatePortfolio::run($portfolio, [
                    'platform_product_id' => Arr::get($offer, 'offerId'),
                    'upload_warning'      => null,
                    'errors_response'     => null,
                    'data'                => ['is_platform_draft' => true]
                ]);

                $portfolio->update([
                    'has_valid_platform_product_id' => true,
                    'exist_in_platform'             => true,
                    'platform_status'               => false
                ]);

                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);

                return $portfolio;
            }

            [$publishedOffer, $inventoryItem] = $this->publishFillingMissingAspects(
                $ebayUser,
                $product,
                $inventoryItem,
                $categoryAspects,
                $categoryId,
                Arr::get($offer, 'offerId')
            );

            if ($ebayUser->isFulfilmentPolicyError($publishedOffer) && $this->swapInUsableFulfilmentPolicy($ebayUser, Arr::get($offer, 'offerId'))) {
                $publishedOffer = $ebayUser->publishListing(Arr::get($offer, 'offerId'));
            }

            if ($handleError($publishedOffer)) {
                return $portfolio;
            }

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($offer, 'offerId'),
                'platform_product_variant_id' => Arr::get($publishedOffer, 'listingId'),
                'upload_warning' => null,
                'errors_response' => null,
                'data' => [
                    'is_platform_draft' => false,
                    'product' => ['aspects' => Arr::get($inventoryItem, 'product.aspects', [])]
                ]
            ]);

            CheckEbayPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            }

            return $portfolio;
        } catch (\Exception $e) {
            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($e->getMessage())
            ]);

            UpdatePlatformPortfolioLog::dispatch($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);

            return $portfolio;

        }
    }

    /**
     * Sellers delete or edit the postage policy the channel was set up with, and eBay then refuses every
     * listing that points at it. The channel's policy is kept while eBay still lists it as usable, otherwise
     * another usable one is taken, or a new one is created from the channel's postage settings.
     */
    private function swapInUsableFulfilmentPolicy(EbayUser $ebayUser, string $offerId): bool
    {
        $currentPolicyId = $ebayUser->fulfillment_policy_id;

        $usablePolicyId = $ebayUser->getUsableFulfilmentPolicyId($currentPolicyId)
            ?? Arr::get($ebayUser->createFulfilmentPolicy(Arr::get($ebayUser->settings, 'shipping', [])), 'fulfillmentPolicyId');

        if (blank($usablePolicyId) || $usablePolicyId === $currentPolicyId) {
            return false;
        }

        UpdateEbayUser::run($ebayUser, ['fulfillment_policy_id' => $usablePolicyId]);

        $updatedOffer = $ebayUser->refresh()->updateOffer($offerId, ['use_channel_policies' => true]);

        return !Arr::hasAny((array) $updatedOffer, ['error', 'errors']);
    }

    public static function sendsOurPrice(Portfolio $portfolio): bool
    {
        return !Arr::get($portfolio->customerSalesChannel->settings, 'do_not_update_prices')
            && Arr::get($portfolio->settings, 'pricing.type') !== 'not_follow';
    }

    /**
     * eBay names one item specific per refused publish, and the taxonomy does not always flag every one of
     * them as required, so the listing is retried with what eBay asked for until it publishes or asks for
     * something the product cannot answer.
     *
     * @param  array<string, mixed>  $inventoryItem
     * @param  array<string, mixed>  $categoryAspects
     * @return array{0: mixed, 1: array<string, mixed>}
     */
    private function publishFillingMissingAspects(EbayUser $ebayUser, Product $product, array $inventoryItem, $categoryAspects, $categoryId, $offerId): array
    {
        $publishedOffer = $ebayUser->publishListing($offerId);

        for ($attempt = 0; $attempt < self::MAX_MISSING_ASPECT_ATTEMPTS; $attempt++) {
            $missingAspects = $ebayUser->parseMissingAspects($publishedOffer);

            if (blank($missingAspects)) {
                break;
            }

            if ($ebayUser->unknownAspects($categoryAspects, $missingAspects)) {
                $categoryAspects = $ebayUser->getItemAspectsForCategory($categoryId);
            }

            $aspects = Arr::get($inventoryItem, 'product.aspects', []);
            $filledAspects = $ebayUser->fillMissingAspects($product, $categoryAspects, $missingAspects, $aspects, $ebayUser->parseStandardValueAspects($publishedOffer));

            if ($filledAspects === $aspects) {
                break;
            }

            data_set($inventoryItem, 'product.aspects', $filledAspects);

            $ebayUser->storeProduct($inventoryItem);

            $publishedOffer = $ebayUser->publishListing($offerId);
        }

        return [$publishedOffer, $inventoryItem];
    }
}
