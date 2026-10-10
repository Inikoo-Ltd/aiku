<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 11:30:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An upload that timed out may have been created by the store anyway. This only looks: when the
 * store has exactly one product with the portfolio's sku, and no other portfolio of the channel
 * holds it, the portfolio is matched to it. Nothing is created in the store.
 */
class LinkTimedOutWooUpload
{
    use AsAction;

    public string $jobQueue = 'woo';

    public function handle(Portfolio $portfolio): Portfolio
    {
        $wooCommerceUser = $portfolio->customerSalesChannel?->user;

        if (!$wooCommerceUser instanceof WooCommerceUser || $portfolio->platform_product_id !== null || blank($portfolio->sku)) {
            return $portfolio;
        }

        $listed = collect($wooCommerceUser->getWooCommerceProducts(['sku' => $portfolio->sku]) ?? [])
            ->filter(fn ($product) => is_array($product) && Arr::get($product, 'id') && strcasecmp((string) Arr::get($product, 'sku'), (string) $portfolio->sku) === 0);

        if ($listed->count() !== 1) {
            return $portfolio;
        }

        $wooProductId = (string) Arr::get($listed->first(), 'id');

        $heldByAnotherPortfolio = Portfolio::where('customer_sales_channel_id', $portfolio->customer_sales_channel_id)
            ->where('platform_product_id', $wooProductId)
            ->exists();

        if ($heldByAnotherPortfolio) {
            return $portfolio;
        }

        MatchPortfolioToCurrentWooProduct::run($portfolio, ['platform_product_id' => $wooProductId]);

        return $portfolio->refresh();
    }
}
