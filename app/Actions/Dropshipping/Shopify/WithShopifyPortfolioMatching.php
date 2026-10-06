<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Actions\Dropshipping\Shopify\Product\LinkShopifyPortfolio;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Str;

trait WithShopifyPortfolioMatching
{
    public function matchShopifyLineItemToPortfolio(
        CustomerSalesChannel $customerSalesChannel,
        ?string $platformProductId,
        ?string $platformProductVariantId,
        ?string $sku,
        bool $healPlatformIds = true
    ): ?Portfolio {
        $portfolio = $this->findPortfolioByPlatformId($customerSalesChannel, 'platform_product_variant_id', $platformProductVariantId);

        if (!$portfolio) {
            $portfolio = $this->findPortfolioByPlatformId($customerSalesChannel, 'platform_product_id', $platformProductId);

            if ($portfolio && $this->isAnotherVariantOfLinkedListing($portfolio, $platformProductVariantId)) {
                $sizeOrdered = $this->findPortfolioByProductCode($customerSalesChannel, $sku);

                if ($sizeOrdered && $sizeOrdered->id !== $portfolio->id) {
                    return $sizeOrdered;
                }
            }
        }

        if (!$portfolio) {
            $portfolio = $this->findPortfolioBySku($customerSalesChannel, $sku);
        }

        if ($portfolio && $healPlatformIds) {
            $this->healPortfolioPlatformIds($portfolio, $platformProductId, $platformProductVariantId);
        }

        return $portfolio;
    }

    private function findPortfolioByPlatformId(CustomerSalesChannel $customerSalesChannel, string $column, ?string $platformId): ?Portfolio
    {
        $candidates = $this->shopifyPlatformIdCandidates($platformId);

        if (!$candidates) {
            return null;
        }

        $portfolios = $customerSalesChannel->portfolios()
            ->whereIn($column, $candidates)
            ->when($column === 'platform_product_id', fn ($query) => $query->whereRaw("coalesce(settings->>'shopify_variant_adopted', 'false') <> 'true'"))
            ->limit(2)
            ->get();

        return $portfolios->count() === 1 ? $portfolios->first() : null;
    }

    /**
     * A merchant can turn one of our listings into a multi-size product, so the listing id alone
     * points at whichever size it was first linked to; when the line is a different variant of that
     * listing, a sku that is exactly our product code names the size actually ordered (HELP-3711).
     * That portfolio keeps its own links: the merchant's listing belongs to the size it was made for.
     */
    private function isAnotherVariantOfLinkedListing(Portfolio $portfolio, ?string $platformProductVariantId): bool
    {
        $candidates = $this->shopifyPlatformIdCandidates($platformProductVariantId);

        return filled($portfolio->platform_product_variant_id)
            && $candidates
            && !in_array($portfolio->platform_product_variant_id, $candidates, true);
    }

    private function findPortfolioByProductCode(CustomerSalesChannel $customerSalesChannel, ?string $sku): ?Portfolio
    {
        $sku = Str::lower(trim((string) $sku));

        if ($sku === '') {
            return null;
        }

        $portfolios = $customerSalesChannel->portfolios()
            ->where('status', true)
            ->whereRaw('lower(item_code) = ?', [$sku])
            ->limit(2)
            ->get();

        return $portfolios->count() === 1 ? $portfolios->first() : null;
    }

    /**
     * A portfolio can carry the sku of another product (shared stock, a recoded stock, a sku read
     * back from the listing), so the portfolio whose product code it is answers first.
     */
    private function findPortfolioBySku(CustomerSalesChannel $customerSalesChannel, ?string $sku): ?Portfolio
    {
        $sku = Str::lower(trim((string) $sku));

        if ($sku === '') {
            return null;
        }

        return $customerSalesChannel->portfolios()
            ->where('status', true)
            ->where(function ($query) use ($sku) {
                $query->whereRaw('lower(sku) = ?', [$sku])
                    ->orWhereRaw('lower(item_code) = ?', [$sku])
                    ->orWhereRaw('lower(platform_sku) = ?', [$sku]);
            })
            ->orderByRaw('(lower(item_code) = ?) desc nulls last', [$sku])
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function shopifyPlatformIdCandidates(?string $platformId): array
    {
        $platformId = trim((string) $platformId);

        if ($platformId === '') {
            return [];
        }

        $candidates = [$platformId];
        $legacyId   = Str::afterLast($platformId, '/');

        if ($legacyId !== $platformId && $legacyId !== '') {
            $candidates[] = $legacyId;
        }

        return $candidates;
    }

    private function healPortfolioPlatformIds(Portfolio $portfolio, ?string $platformProductId, ?string $platformProductVariantId): void
    {
        $healedProductId = $platformProductId && $portfolio->platform_product_id !== $platformProductId ? $platformProductId : null;
        $healedVariantId = $platformProductVariantId && $portfolio->platform_product_variant_id !== $platformProductVariantId ? $platformProductVariantId : null;

        if ($healedProductId || $healedVariantId) {
            LinkShopifyPortfolio::run($portfolio, $healedProductId, $healedVariantId);
        }
    }
}
