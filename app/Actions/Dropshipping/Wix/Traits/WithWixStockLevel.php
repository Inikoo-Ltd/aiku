<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Traits;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;

trait WithWixStockLevel
{
    /**
     * The quantity to advertise on Wix: what is actually available, zeroed when the product is
     * off sale, and capped when the channel limits how much it will advertise.
     */
    public function wixStockLevel(Portfolio $portfolio): int
    {
        $item = $portfolio->item;

        if (!$item instanceof Product) {
            return 0;
        }

        if (!$item->isSellableThroughSalesChannels()) {
            return 0;
        }

        $availableQuantity = (int) ($item->available_quantity ?? 0);

        $maxToAdvertise = (int) ($portfolio->customerSalesChannel?->max_quantity_advertise ?? 0);

        if ($maxToAdvertise > 0) {
            $availableQuantity = min($availableQuantity, $maxToAdvertise);
        }

        return max($availableQuantity, 0);
    }
}
