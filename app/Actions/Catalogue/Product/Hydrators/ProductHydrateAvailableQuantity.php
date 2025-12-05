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
use App\Models\Catalogue\Shop;
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

    public function handle(Product $product): Product
    {
        if ($product->state == ProductStateEnum::DISCONTINUED) {
            return UpdateProduct::run($product, [
                'available_quantity' => null,
                'status'             => ProductStatusEnum::DISCONTINUED,
            ]);
        }


        $currentQuantity   = $product->available_quantity;
        $availableQuantity = 0;

        $numberOrgStocksChecked                 = 0;
        $numberOrgStocksHasNeverBeenInWarehouse = 0;
        foreach ($product->orgStocks as $orgStock) {
            if (!$orgStock->has_been_in_warehouse) {
                $numberOrgStocksHasNeverBeenInWarehouse++;
            }

            if ($orgStock->is_on_demand) {
                $quantityInStock = 10000;
            } else {
                $quantityInStock = $orgStock->quantity_available;
            }


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

        if ($currentQuantity == 0 && $availableQuantity > 0) {
            $dataToUpdate['back_in_stock_since'] = now();
        }

        if (in_array($product->status, [
            ProductStatusEnum::FOR_SALE,
            ProductStatusEnum::OUT_OF_STOCK,
            ProductStatusEnum::COMING_SOON
        ])) {
            if ($availableQuantity == 0) {
                $status = ProductStatusEnum::OUT_OF_STOCK;

                if ($numberOrgStocksChecked == 0 || $numberOrgStocksHasNeverBeenInWarehouse > 0) {
                    $status = ProductStatusEnum::COMING_SOON;
                }


                $dataToUpdate['out_of_stock_since'] = now();
            } else {
                $status = ProductStatusEnum::FOR_SALE;
            }
            $dataToUpdate['status'] = $status;
        }

        if (!$product->is_for_sale) {
            $dataToUpdate['status'] = ProductStatusEnum::NOT_FOR_SALE;
        }

        return UpdateProduct::run($product, $dataToUpdate);
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

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        // Get total count for progress bar
        $total = Product::whereIn('shop_id', $aikuShops)->count();

        if ($total === 0) {
            $command->info("No products found.");

            return;
        }

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        Product::whereIn('shop_id', $aikuShops)->chunk($chunkSize, function ($products) use (&$count, $progressBar) {
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
