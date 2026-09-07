<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Traits;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;

trait WithWixShippingWeight
{
    /**
     * Wix takes a bare number and reads it in whatever weight unit the store is set to, which it
     * exposes no API for. Kilograms is its default, so that is what we send unless the channel
     * says otherwise via settings.wix.weight_unit.
     *
     * Our gross_weight is the outer weight including packing, in grams.
     */
    public function wixShippingWeight(Portfolio $portfolio): ?float
    {
        $item = $portfolio->item;

        if (!$item instanceof Product) {
            return null;
        }

        $grams = (float) ($item->gross_weight ?? 0);

        if ($grams <= 0) {
            return null;
        }

        $unit = Arr::get($portfolio->customerSalesChannel?->settings ?? [], 'wix.weight_unit', 'kg');

        return match (strtolower((string) $unit)) {
            'lb', 'lbs' => round($grams * 0.00220462, 3),
            'g'         => round($grams, 3),
            'oz'        => round($grams * 0.035274, 3),
            default     => round($grams / 1000, 3),
        };
    }
}
