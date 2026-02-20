<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;

class UpdateFaireProductInventoryQuantity extends OrgAction
{
    public function handle(Product $product): array
    {
        $shop = $product->shop;

        $inventories = [
            [
                'product_variant_id' => $product->marketplace_id,
                'on_hand_quantity' => $product->available_quantity
            ]
        ];

        return $shop->updateInventoryQuantity($inventories);
    }
}
