<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairWrongProductOrgStock
{
    use WithActionUpdate;

    public function handle(Product $product, Command $command): void
    {
        $productData = [
            'product_code' => $product->code,
            'product_units' => $product->units,
            'org_stocks' => $product->orgStocks->pluck('pivot.quantity')->toArray(),
            'trade_units' => $product->tradeUnits->pluck('pivot.quantity')->toArray(),
        ];

        $command->info('Product Data:');
        $command->info(json_encode($productData, JSON_PRETTY_PRINT));
    }

    public string $commandSignature = 'repair:product_wrong_org_stocks';

    public function asCommand(Command $command): void
    {
        $query = Product::whereHas('masterProduct', function($query) {
            $query->where('master_shop_id', 2);
        })
        ->whereNull('source_id')
        ->with(['orgStocks', 'tradeUnits'])
        ->orderBy('id');

        $count = $query->count();
        $command->info("Processing {$count} products...");

        $processed = 0;
        $query->chunk(100, function (Collection $models) use ($command, &$processed) {
            foreach ($models as $model) {
                $this->handle($model, $command);
                $processed++;
            }
        });
        
        $command->info("Processed {$processed} products successfully.");
    }
}