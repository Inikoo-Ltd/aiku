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
            if (!$productToOrgStockRatio || $productToOrgStockRatio == 0) {
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

    public string $commandSignature = 'product:hydrate-available-quantity';

    public function asCommand(\Illuminate\Console\Command $command): void
    {



        $chunkSize = 100; // Process 100 products at a time to save memory
        $count = 0;

        // Get total count for progress bar
        $total = \App\Models\Catalogue\Product::count();

        if ($total === 0) {
            $command->info("No products found.");
            return;
        }

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        \App\Models\Catalogue\Product::chunk($chunkSize, function ($products) use (&$count, $progressBar) {
            foreach ($products as $product) {
                $this->handle($product);
                $count++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
        $command->info("Updated available quantity for $count products.");
    }

}
