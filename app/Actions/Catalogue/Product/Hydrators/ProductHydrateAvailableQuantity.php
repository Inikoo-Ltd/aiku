<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 May 2025 14:40:04 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateAvailableQuantity implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {

        if ($product->state == ProductStateEnum::DISCONTINUED) {
            $product->update(['available_quantity' => null]);
            return;
        }

        $availableQuantity = 0;

        $numberOrgStocksChecked = 0;
        foreach ($product->orgStocks as $orgStock) {
            $quantityInStock = $orgStock->quantity_in_locations;

            $productToOrgStockRatio = $orgStock->pivot->quantity;
            if (!$productToOrgStockRatio) {
                continue;
            }

            $availableQuantityFromThisOrgStock = floor($quantityInStock / $productToOrgStockRatio);


            if ($numberOrgStocksChecked == 0) {
                $availableQuantity = $availableQuantityFromThisOrgStock;
            } else {
                $availableQuantity = min($availableQuantityFromThisOrgStock, $numberOrgStocksChecked);
            }

            $numberOrgStocksChecked++;

        }



        $product->update(['available_quantity' => $availableQuantity]);


    }

    public string $commandSignature = 'aaa';

    public function asCommand()
    {
        $product = Product::find(235799);

        foreach (Product::all() as $product) {
            $this->handle($product);
        }

    }

}
