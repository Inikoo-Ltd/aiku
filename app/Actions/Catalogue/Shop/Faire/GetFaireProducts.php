<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;

class GetFaireProducts extends OrgAction
{
    public string $commandSignature = 'faire:products';

    public function handle(Shop $shop): array
    {
        $products = $shop->getFaireProducts([
            'limit' => 250
        ]);

        $variantSkus = [];
        $products = [];

        foreach (Arr::get($products, 'products', []) as $product) {
            foreach ($product['variants'] as $variant) {
                $variantSkus[] = $variant['sku'];

                $awProduct = Product::where('shop_id', 41)
                    ->where('code', 'ILIKE', "%{$variant['sku']}%")->first();

                if($awProduct->code) {
                    $products[] = $awProduct->code;
                }
            }
        }


        dd(array_diff($variantSkus, $products));
    }

    public function asCommand(): void
    {
        $shop = Shop::where('type', ShopTypeEnum::FAIRE)
            ->where('state', ShopStateEnum::OPEN)
            ->first();

        $this->handle($shop);
    }
}
