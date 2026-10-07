<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 21:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The one writer of the Shopify product and variant a portfolio is linked to (INI-028).
 *
 * The ids must be read from the channel's own Shopify store (a reply to the channel's client, one of
 * its orders or its catalogue), which is what makes the variant exist and belong to that store. What
 * can be told without asking Shopify is refused: a channel that is not a Shopify one, an id that is not
 * a Shopify product or variant id, and a variant another portfolio of the channel is already linked to.
 * That last one counts closed portfolios too, because order lines are matched by variant id whatever
 * the status of the portfolio.
 *
 * A null id is left as it is: a product id alone is written while the variant is still to be created
 * or adopted. Unlinking writes nulls and holds no invariant, so it stays with its callers.
 */
class LinkShopifyPortfolio
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $modelData  other portfolio fields written with the link
     * @return array{0: bool, 1: string|null}  whether it was linked, and why not
     */
    public function handle(Portfolio $portfolio, ?string $productId, ?string $variantId = null, array $modelData = []): array
    {
        $refusal = self::refusal($portfolio->customerSalesChannel, $productId, $variantId, $portfolio);

        if ($refusal !== null) {
            return [false, $refusal];
        }

        UpdatePortfolio::run($portfolio, array_merge($modelData, array_filter([
            'platform_product_id'         => $productId,
            'platform_product_variant_id' => $variantId,
        ])));

        return [true, null];
    }

    public static function refusal(?CustomerSalesChannel $customerSalesChannel, ?string $productId, ?string $variantId, ?Portfolio $portfolio = null): ?string
    {
        if (!$customerSalesChannel?->user instanceof ShopifyUser) {
            return 'This product is not in a Shopify channel, so it can not be linked to a Shopify listing';
        }

        if ($productId === null && $variantId === null) {
            return 'No Shopify listing was given to link this product to';
        }

        if ($productId !== null && !CheckIfShopifyProductIDIsValid::run($productId)) {
            return 'This is not a Shopify product id: '.$productId;
        }

        if ($variantId !== null && !preg_match('/^gid:\/\/shopify\/ProductVariant\/\d+$/', $variantId)) {
            return 'This is not a Shopify variant id: '.$variantId;
        }

        if ($variantId !== null && $variantId !== $portfolio?->platform_product_variant_id) {
            $holder = self::variantHolder($customerSalesChannel, $variantId, $portfolio);

            if ($holder) {
                return 'This Shopify variant is already linked to '.$holder->item_code.' in this channel';
            }
        }

        return null;
    }

    public static function variantHolder(CustomerSalesChannel $customerSalesChannel, string $variantId, ?Portfolio $except = null): ?Portfolio
    {
        return Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereIn('platform_product_variant_id', array_unique([$variantId, Str::afterLast($variantId, '/')]))
            ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
            ->orderBy('id')
            ->first(['id', 'item_code']);
    }
}
