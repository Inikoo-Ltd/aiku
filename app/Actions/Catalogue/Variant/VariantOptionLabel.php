<?php

/*
 * Author: Louis Perez Napitupulu
 * Created: Wed, 07 Oct 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Variant;

use Lorisleiva\Actions\Concerns\AsObject;

class VariantOptionLabel
{
    use AsObject;

    /**
     * The option a product stands for in its variant, e.g. "Size: XL" or "Plug: UK Plug · Bulb: Yes".
     *
     * @param array<string, mixed>|null $variantData
     */
    public function handle(?array $variantData, int $productId): ?string
    {
        $entry = data_get($variantData, "products.$productId");
        if (!$entry) {
            return null;
        }

        $label = collect(data_get($variantData, 'variants', []))
            ->map(fn (array $axis) => ($value = data_get($entry, $axis['label'] ?? '')) ? ($axis['label'].': '.$value) : null)
            ->filter()
            ->implode(' · ');

        return $label ?: null;
    }
}
