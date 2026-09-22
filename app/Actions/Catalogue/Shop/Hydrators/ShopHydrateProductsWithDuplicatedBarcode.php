<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithDuplicatedBarcodeProducts;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateProductsWithDuplicatedBarcode implements ShouldBeUnique
{
    use AsAction;
    use WithDuplicatedBarcodeProducts;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $shop->stats()->update([
            'number_products_with_duplicated_barcode' => $this->duplicatedBarcodeProducts($shop)->count(),
        ]);
    }
}
