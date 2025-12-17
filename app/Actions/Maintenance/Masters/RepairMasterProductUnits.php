<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterProductUnits extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        if ($masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return $masterAsset;
        }

        $masterAsset = ModelHydrateSingleTradeUnits::run($masterAsset);

        if ($masterAsset->is_single_trade_unit) {
            $tradeUnitData = $masterAsset->tradeUnits->first();
            $packedIn      = $tradeUnitData->pivot->quantity;

            UpdateMasterAsset::make()->action($masterAsset, [
                'units' => $packedIn
            ]);
        }


        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_units {masterAsset?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {

        if ($command->argument('masterAsset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('masterAsset'))->firstOrFail();
            $command->info("Fixing units value from trade units for $masterAsset->code");
            $this->handle($masterAsset);
            return 0;
        }

        $command->info('Fix units value from trade units');

        $chunkSize = 100;
        $count     = 0;

        $totalCount = MasterAsset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        MasterAsset::chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, $bar, $command) {
                foreach ($masterAssets as $asset) {
                    try {
                        $this->handle($asset);
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

        return 0;
    }
}
