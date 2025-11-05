<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Events\UploadProductToEbayProgressEvent;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentEbayProduct extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData): void
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;
        $product = $portfolio->item;

        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type'   => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        $ebayProductId = Arr::get($modelData, 'platform_product_id');

        $listing = $ebayUser->getOffers([
            'sku' => $ebayProductId
        ]);
        $offerId = Arr::get($listing, 'offers.0.offerId');
        $categoryId = Arr::get($listing, 'offers.0.categoryId');

        if (! $categoryId) {
            $categories = $ebayUser->getCategorySuggestions($product->family->name);
            $categoryId = Arr::get($categories, 'categorySuggestions.0.category.categoryId');

            if (! $categoryId) {
                $categories = $ebayUser->searchAvailableProducts($product->family->name);
                $categoryId = Arr::get($categories, 'itemSummaries.0.categories.0.categoryId');
            }
        }

        $ebayUser->updateOffer($offerId, [
            'category_id' => $categoryId,
            'quantity' => $product->available_quantity,
            'price' => $portfolio->customer_price,
            'currency' => $portfolio->shop->currency->code
        ]);

        if (! Arr::has($listing, 'offers.0.listing.listingId')) {
            $publishedOffer = $ebayUser->publishListing($offerId);
        } else {
            $publishedOffer = Arr::get($listing, 'offers.0.listing');
        }

        $portfolio->update([
            'platform_product_id' => Arr::get($listing, 'offers.0.offerId'),
            'platform_product_variant_id' => Arr::get($publishedOffer, 'listingId')
        ]);

        if (Arr::get($publishedOffer, 'listingId')) {
            UpdatePlatformPortfolioLog::run($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::OK
            ]);
        }

        if ($errorMessage = Arr::get($publishedOffer, 'errors')) {
            $displayError = $ebayUser->getDisplayErrors($errorMessage) ?? $errorMessage;

            UpdatePlatformPortfolioLog::run($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $displayError
            ]);

            UpdatePortfolio::make()->action($portfolio, [
                'upload_warning' => $displayError,
                'errors_response' => [
                    'message' => __('Your product is not in the listing yet, please publish it first in your ebay shop. Or you can use option: create new product')
                ]
            ]);
        }

        $portfolio->refresh();

        /** @var Portfolio $portfolio */
        $portfolio = CheckEbayPortfolio::run($portfolio);

        UploadProductToEbayProgressEvent::dispatch($ebayUser, $portfolio);
    }

    public function rules(): array
    {
        return [
            'platform_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($portfolio->customerSalesChannel->organisation, $request);
        $this->handle($portfolio, $this->validatedData);
    }

}
