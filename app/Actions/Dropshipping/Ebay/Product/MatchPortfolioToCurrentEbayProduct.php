<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\OrgAction;
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

        $ebayProductId = Arr::get($modelData, 'platform_product_id');

        $listing = $ebayUser->getOffers([
            'sku' => $ebayProductId
        ]);
        $offerId = Arr::get($listing, 'offers.0.offerId');
        $categoryId = Arr::get($listing, 'offers.0.categoryId');

        $ebayUser->updateOffer($offerId, [
            'categoryId' => $categoryId,
            'availableQuantity' => $portfolio->item->available_quantity,
            'pricingSummary' => [
                'price' => [
                    'currency' => $portfolio->shop->currency->code,
                    'value' => $portfolio->customer_price
                ]
            ],
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
