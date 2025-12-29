<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetFaireProducts extends OrgAction
{
    public string $commandSignature = 'faire:products {shop}';

    public function handle(Shop $shop): void
    {
        $products = $shop->getFaireProducts([
            'limit' => 250
        ]);

        foreach (Arr::get($products, 'products', []) as $product) {
            foreach ($product['variants'] as $variant) {
                StoreProduct::make()->action($shop, [
                    'code' => $variant['sku'],
                    'name' => $product['name'] - $variant['name'],
                    'description' => $product['description'],
                    'rrp' => $variant['retail_price_cents'],
                    'price' => $variant['wholesale_price_cents'],
                    'external_id' => $variant['id'],
                    'is_main' => true
                ]);
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)->where('engine', ShopEngineEnum::FAIRE)
            ->where('state', ShopStateEnum::OPEN)
            ->where('slug', $command->argument('shop'))
            ->first();

        $this->handle($shop);
    }
}
