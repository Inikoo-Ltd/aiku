<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class UpdateFaireInventoryQuantity extends OrgAction
{
    public string $commandSignature = 'faire:inventory {shop} {product?}';

    public function handle(Shop $shop, Collection $products, ?Command $command): array
    {
        $inventories = [];

        foreach ($products as $product) {
            $inventories[] = [
                'product_variant_id' => $product->marketplace_id,
                'on_hand_quantity' => $product->available_quantity
            ];
        }

        $faireProduct = $shop->updateInventoryQuantity($inventories);

        $command?->argument('product-inventory updated: '.$faireProduct['name']);

        return $faireProduct;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if($command->argument('product')) {
            $products = Product::where('slug', $command->argument('product'))->get();
        } else {
            $products = $shop->products;
        }

        $this->handle($shop, $products, $command);
    }
}
