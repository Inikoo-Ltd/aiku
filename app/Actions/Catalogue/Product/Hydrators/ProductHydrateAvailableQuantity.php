<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 May 2025 14:40:04 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
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
            UpdateProduct::run($product, [
                'available_quantity' => null,
                'status'             => ProductStatusEnum::DISCONTINUED,
            ]);


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
                $availableQuantity = min($availableQuantityFromThisOrgStock, $availableQuantity);
            }

            $numberOrgStocksChecked++;
        }

        if ($availableQuantity < 0) {
            $availableQuantity = 0;
        }


        $dataToUpdate = [
            'available_quantity' => $availableQuantity,
        ];


        if (in_array($product->status, [ProductStatusEnum::FOR_SALE, ProductStatusEnum::OUT_OF_STOCK])) {
            if ($availableQuantity == 0) {
                $status = ProductStatusEnum::OUT_OF_STOCK;
            } else {
                $status = ProductStatusEnum::FOR_SALE;
            }

            $dataToUpdate['status'] = $status;
        }


        UpdateProduct::run($product, $dataToUpdate);
    }

    public string $commandSignature = 'product:hydrate-available-quantity {id?}';

    public function asCommand(Command $command): void
    {

        if ($command->argument('id')) {
            $product = Product::findOrFail($command->argument('id'));
            $this->handle($product);
            return;
        }

        $chunkSize = 100; // Process 100 products at a time to save memory
        $count     = 0;

        // Get total count for progress bar
        $total = Product::count();

        if ($total === 0) {
            $command->info("No products found.");

            return;
        }

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        Product::chunk($chunkSize, function ($products) use (&$count, $progressBar) {
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
