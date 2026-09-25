<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

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

        return $customerSalesChannel->portfolios()
            ->whereIn($column, $candidates)
            ->when($column === 'platform_product_id', fn ($query) => $query->whereRaw("coalesce(settings->>'shopify_variant_adopted', 'false') <> 'true'"))
            ->first();
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
                    ->orWhereRaw('lower(item_code) = ?', [$sku]);
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
        $healedIds = [];

        if ($platformProductId && $portfolio->platform_product_id !== $platformProductId) {
            $healedIds['platform_product_id'] = $platformProductId;
        }

        if ($platformProductVariantId && $portfolio->platform_product_variant_id !== $platformProductVariantId) {
            $healedIds['platform_product_variant_id'] = $platformProductVariantId;
        }

        if ($healedIds) {
            $portfolio->update($healedIds);
        }
    }
}
