<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWixProduct extends OrgAction
{
    use AsAction;
    use WithPortfolioErrorResponse;

    public string $jobQueue = 'dropshipping-long';

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var WixUser $wixUser */
        $wixUser = $portfolio->customerSalesChannel?->user;

        if (!$wixUser instanceof WixUser || !$portfolio->platform_product_id) {
            return $portfolio;
        }

        $response = $wixUser->updateProduct($portfolio->platform_product_id, [
            'name'        => $portfolio->customer_product_name ?: $portfolio->item_name,
            'description' => $portfolio->customer_description ?: '',
            'sku'         => $portfolio->sku,
            'priceData'   => [
                'price' => (float) $portfolio->customer_price
            ],
        ]);

        if ($message = Arr::get($response, 'message')) {
            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($message)
            ]);

            return $portfolio;
        }

        UpdatePortfolio::run($portfolio, ['errors_response' => null]);

        return $portfolio;
    }
}
