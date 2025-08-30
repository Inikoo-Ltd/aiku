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
use App\Models\Catalogue\Asset;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchAssetsToMaster extends OrgAction
{
    use AsAction;


    public function handle(Asset $asset): Asset
    {
        $masterShop = $asset->shop->masterShop;

        if (!$masterShop) {
            return $asset;
        }

        $masterAsset = MasterAsset::where('master_shop_id', $masterShop->id)->whereRaw('LOWER(code) = LOWER(?)', [$asset->code])
            ->where('type', $asset->type->value)
            ->first();


        UpdateAsset::make()->action(
            $asset,
            [
                'master_asset_id' => $masterAsset?->id,
            ]
        );

        if ($asset->type == AssetTypeEnum::PRODUCT) {
            UpdateProduct::make()->action($asset->product, [
                'master_product_id' => $masterAsset?->id,
            ]);
        }


        return $asset;
    }

    public function getCommandSignature(): string
    {
        return 'products:match_to_master';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Matching asset to masters');

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        $totalCount = Asset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        Asset::chunk(
            $chunkSize,
            function ($assets) use (&$count, &$matchedCount, $bar, $command) {
                foreach ($assets as $asset) {
                    try {
                        $hadMaster = (bool)$asset->master_asset_id;
                        $this->handle($asset);
                        $hasNowMaster = (bool)$asset->master_asset_id;

                        if (!$hadMaster && $hasNowMaster) {
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
        $command->info("$count assets processed, $matchedCount newly matched to master assets");

        return 0;
    }
}
