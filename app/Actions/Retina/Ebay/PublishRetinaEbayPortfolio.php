<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Aug 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\Product\CheckEbayPortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishRetinaEbayPortfolio extends RetinaAction
{
    use AsAction;
    use WithPortfolioErrorResponse;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;

        $publishedOffer = $ebayUser->publishListing($portfolio->platform_product_id);

        $errorMessage = Arr::get($publishedOffer, 'errors.0.message') ?? Arr::get($publishedOffer, 'error');
        if ($errorMessage) {
            $displayError = $ebayUser->getDisplayErrors($errorMessage) ?? $errorMessage;

            return UpdatePortfolio::run($portfolio, [
                'upload_warning'  => $this->portfolioErrorMessage($displayError),
                'errors_response' => $this->portfolioErrorResponse($displayError) ?? ['message' => $displayError]
            ]);
        }

        $portfolio = UpdatePortfolio::run($portfolio, [
            'platform_product_variant_id' => Arr::get($publishedOffer, 'listingId'),
            'upload_warning'              => null,
            'errors_response'             => null,
            'data'                        => ['is_platform_draft' => false]
        ]);

        return CheckEbayPortfolio::run($portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($portfolio);
    }
}
