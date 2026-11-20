<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 27 Jul 2026 16:48:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

use App\Actions\CRM\ShoppingAssistant\GetEbayListingProblems;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Helpers\PlatformResponseFormatter;
use App\Models\Dropshipping\Portfolio;
use Sentry;
use Throwable;

trait WithPortfolioErrorResponse
{
    public const string NO_SKU_MESSAGE = 'This product is not in our warehouse yet, so it has no SKU to send to the store. It can be uploaded once it is in stock.';

    /**
     * eBay's "system error, try later" says nothing about the product, so it must not overwrite the refusal eBay
     * gave the last time (PRE-127: a selling limit hidden by a 25002 that came a few seconds after it). A warning
     * is cleared on every good upload, so the one kept is from the same run of failures.
     */
    protected function isTemporaryErrorOverRealRefusal(Portfolio $portfolio, mixed $newWarning): bool
    {
        if (blank($portfolio->upload_warning) || !is_string($newWarning)) {
            return false;
        }

        return GetEbayListingProblems::refusalOf($newWarning, null)[0] === 'ebay_temporary'
            && GetEbayListingProblems::refusalOf($portfolio->upload_warning, null)[0] !== 'ebay_temporary';
    }

    /**
     * @return array{message: string}|null
     */
    protected function portfolioErrorResponse(mixed $response): ?array
    {
        $message = $this->portfolioErrorMessage($response);

        return $message ? ['message' => $message] : null;
    }

    protected function portfolioErrorMessage(mixed $response): ?string
    {
        return PlatformResponseFormatter::make()->message($response);
    }

    /**
     * The sku is taken when the portfolio is made, so a product that had no SKO then gets one here once it has.
     */
    protected function portfolioHasSkuOrRecordWhyNot(Portfolio $portfolio): bool
    {
        if (!blank($portfolio->sku)) {
            return true;
        }

        $sku = $portfolio->item ? StorePortfolio::make()->getSKU($portfolio->item) : null;

        if ($sku) {
            UpdatePortfolio::run($portfolio, ['sku' => $sku]);

            return true;
        }

        UpdatePortfolio::run($portfolio, [
            'errors_response' => ['message' => self::NO_SKU_MESSAGE],
        ]);

        return false;
    }

    protected function recordPortfolioUploadFailure(Portfolio $portfolio, Throwable $e): void
    {
        Sentry::captureException($e);

        UpdatePortfolio::run($portfolio, [
            'errors_response' => $this->portfolioErrorResponse($e->getMessage()) ?? ['message' => class_basename($e)],
        ]);
    }
}
