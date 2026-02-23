<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class UpdateFaireProductInventoryQuantity extends OrgAction
{
    public function handle(Product $product): void
    {
        if (!$product->marketplace_id) {
            return;
        }

        $shop = $product->shop;

        $availableQuantity = $product->available_quantity * $product->units;

        $inventories = [
            [
                'product_variant_id' => $product->marketplace_id,
                'on_hand_quantity'   => $availableQuantity
            ]
        ];

        $shop->updateInventoryQuantity($inventories);
    }

    public string $commandSignature = 'faire:inventory{model}';

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('model'))->first();
        if ($shop) {
            $command->info("Updating inventory for shop $shop->name");
            foreach ($shop->products as $product) {
                $this->handle($product);
            }

            return 0;
        }


        $product = Product::where('slug', $command->argument('model'))->firstOrFail();
        $command->info("Updating inventory for product $product->name");
        $this->handle($product);

        return 0;
    }


}
