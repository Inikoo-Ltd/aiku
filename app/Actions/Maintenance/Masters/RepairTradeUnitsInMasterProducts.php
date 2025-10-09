<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 10:24:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\OrgAction;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairTradeUnitsInMasterProducts extends OrgAction
{
    use AsAction;


    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        if ($masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return $masterAsset;
        }

        $stocks = $masterAsset->stocks;


        $tradeUnits = [];
        foreach ($stocks as $stock) {
            $quantity = $stock->pivot->quantity;

            $tradeUnitsInStock = $stock->tradeUnits;

            foreach ($tradeUnitsInStock as $tradeUnitInStock) {
                $tradeUnits[$tradeUnitInStock->id] = [
                    'quantity' => $tradeUnitInStock->pivot->quantity * $quantity,
                ];
            }
        }



        $masterAsset->tradeUnits()->sync($tradeUnits);

        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_trade_units';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Matching trade units to masters');

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        $totalCount = MasterAsset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        MasterAsset::chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, &$matchedCount, $bar, $command) {
                foreach ($masterAssets as $asset) {
                    try {
                        $hasTradeUnits = (bool)$asset->tradeUnits->count() == 0;
                        $this->handle($asset);
                        $hasNowTradeUnits = (bool)$asset->tradeUnits->count() >= 1;

                        if (!$hasTradeUnits && $hasNowTradeUnits) {
                            $matchedCount++;
                        }
                    } catch (Exception $e) {
                        $command->error("Error processing asset $asset->id: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count master assets processed, $matchedCount newly matched to trade units");

        return 0;
    }
}
