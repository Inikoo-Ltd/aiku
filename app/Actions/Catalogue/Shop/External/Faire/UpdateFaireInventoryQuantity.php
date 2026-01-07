<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class UpdateFaireInventoryQuantity extends OrgAction
{
    public string $commandSignature = 'faire:inventory {shop}';

    public function handle(Shop $shop, Product $product): array
    {
        return $shop->updateInventoryQuantity([
            'sku' => $product->code,
            'quantity' => $product->available_quantity
        ]);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();
        $product = Product::where('slug', $command->argument('product'))->first();

        $this->handle($shop, $product);
    }
}
