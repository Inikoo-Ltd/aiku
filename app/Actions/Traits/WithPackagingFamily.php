<?php

/*
 * Author: Andi Ferdiawan
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Traits;

use App\Models\Billables\Packaging;
use Illuminate\Support\Collection;

trait WithPackagingFamily
{
    /**
     * The label a customer sees for a whole family: the longest name shared by every size in it,
     * so "Pink Poly Bubble Envelope C/0 (150 x 210mm)" and its siblings collapse into
     * "Pink Poly Bubble Envelope".
     *
     * @param Collection<int, Packaging> $packagings
     */
    protected function packagingFamilyLabel(Collection $packagings): string
    {
        $names  = $packagings->pluck('name')->all();
        $prefix = array_shift($names);

        foreach ($names as $name) {
            while ($prefix !== '' && !str_starts_with($name, $prefix)) {
                $prefix = substr($prefix, 0, -1);
            }
        }

        $prefix = trim($prefix, " -–(");

        return strlen($prefix) >= 3 ? $prefix : $packagings->first()->name;
    }

    protected function packagingSizesLabel(Collection $packagings): ?string
    {
        return $packagings->count() > 1
            ? __('Various sizes')
            : $this->packagingDimensionsLabel($packagings->first());
    }

    protected function packagingDimensionsLabel(Packaging $packaging): ?string
    {
        if (!$packaging->width || !$packaging->height || !$packaging->depth) {
            return null;
        }

        return "{$packaging->width} × {$packaging->height} × {$packaging->depth} mm";
    }

    /**
     * The size that represents its family on an order. The customer picks a family and the
     * warehouse picks the size that fits, so the order is anchored to the cheapest one.
     *
     * @param Collection<int, Packaging> $packagings
     */
    protected function representativePackaging(Collection $packagings): Packaging
    {
        return $packagings->sortBy(fn (Packaging $packaging) => (float) $packaging->price)->first();
    }
}
