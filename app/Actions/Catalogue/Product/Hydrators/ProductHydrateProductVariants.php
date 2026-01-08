<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 16:28:07 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateProductVariants implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $productID): string
    {
        return $productID ?? 'empty';
    }

    public function handle(int|null $productID): void
    {
        if (!$productID) {
            return;
        }
        $product = Product::find($productID);
        if (!$product) {
            return;
        }

        $stats = [
            'number_product_variants' => $product->productVariants()->count(),
        ];

        $product->stats()->update($stats);
    }

}
