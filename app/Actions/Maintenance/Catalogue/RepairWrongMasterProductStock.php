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
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairWrongMasterProductStock
{
    use WithActionUpdate;

    public function handle(MasterAsset $masterAsset, Command $command): void
    {
        $productData = [
            'product_code' => $masterAsset->code,
            'product_units' => $masterAsset->units,
            'org_stocks' => $masterAsset->stocks->pluck('pivot.quantity')->toArray(),
            'trade_units' => $masterAsset->tradeUnits->pluck('pivot.quantity')->toArray(),
        ];

        $command->info('Master Product Data:');
        $command->info(json_encode($productData, JSON_PRETTY_PRINT));
    }

    public string $commandSignature = 'repair:master_product_wrong_stocks';

    public function asCommand(Command $command): void
    {
        $query = MasterAsset::where('master_shop_id', 2)
        ->whereNull('source_id')
        ->with(['stocks', 'tradeUnits'])
        ->orderBy('id');

        $count = $query->count();
        $command->info("Processing {$count} master products...");

        $processed = 0;
        $query->chunk(100, function (Collection $models) use ($command, &$processed) {
            foreach ($models as $model) {
                $this->handle($model, $command);
                $processed++;
            }
        });
        
        $command->info("Processed {$processed} master products successfully.");
    }
}