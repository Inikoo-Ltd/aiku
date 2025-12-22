<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;

class GetFaireProducts extends OrgAction
{
    public function handle(Shop $shop): array
    {
        $products = $shop->getFaireProducts();

        foreach (Arr::get($products, 'products', []) as $product) {
            //
        }
    }
}
