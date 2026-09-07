<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-08-28
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;

trait WithSearchInWebsiteAvailabilityChecklist
{
    /**
     * Mirrors the product rule behind is_in_website (HydrateIsInWebsite), the flag the
     * internal search filters on: a live webpage, and the product is a variant leader
     * or a for-sale non-minion variant. The rule is split so each item can pass or
     * fail independently: (leader || !minion) && (leader || for_sale) is the same
     * condition as leader || (!minion && for_sale).
     *
     * @return array{is_in_website: bool, checklist: array<int, array{label: string, passed: bool, detail: string|null}>}
     */
    public function getSearchInWebsiteAvailabilityChecklist(Product $product): array
    {
        $hasLiveWebpage    = (bool) $product->has_live_webpage;
        $isVisibleVariant  = $product->is_variant_leader || !$product->is_minion_variant;
        $isForSaleOrLeader = $product->is_variant_leader || $product->is_for_sale;

        return [
            'is_in_website' => (bool) $product->is_in_website,
            'checklist'     => [
                [
                    'label'  => __('Webpage is live'),
                    'passed' => $hasLiveWebpage,
                    'detail' => $hasLiveWebpage
                        ? null
                        : ($product->webpage_id ? __('Webpage does not have a live state') : __('Product has no webpage')),
                ],
                [
                    'label'  => __('Product is not a minion variant'),
                    'passed' => $isVisibleVariant,
                    'detail' => $isVisibleVariant ? null : __('Minion variants are represented in search by their variant leader'),
                ],
                [
                    'label'  => __('Product is for sale, or is a variant leader'),
                    'passed' => $isForSaleOrLeader,
                    'detail' => $isForSaleOrLeader ? null : __('Product is not marked as for sale'),
                ],
            ],
        ];
    }
}
