<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 17:55:46 Central European Summer Time, Plane Vienna-Malaga
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Asset\UpdateAsset;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchTradeUnitsToMasterAssets extends OrgAction
{
    use AsAction;


    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        $masterShop = $masterAsset->masterShop;

        if (!$masterShop) {
            return $masterAsset;
        }

        $tradeUnit = TradeUnit::where('group_id', $masterShop->group_id)->whereRaw('LOWER(code) = LOWER(?)', [$masterAsset->code])->first();

        if(!$tradeUnit){
            return $masterAsset;
        }

        if ($masterAsset->type == MasterAssetTypeEnum::PRODUCT) {
            $masterAsset->tradeUnits()->syncWithoutDetaching([$tradeUnit->id]);
        }


        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'trade_units:match_to_master';
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
