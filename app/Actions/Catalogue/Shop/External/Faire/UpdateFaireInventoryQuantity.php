<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;

class UpdateFaireInventoryQuantity extends OrgAction
{
    public function handle(Shop $shop, Product $product): array
    {
        // TODO: Implement updateInventoryQuantity method.
        return $shop->updateInventoryQuantity($product->external_id);
    }
}
