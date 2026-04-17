<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairMasterProductUnits extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        $seedShopID = null;
        if ($masterAsset->masterShop->slug == 'aw') {
            $seedShopID = 18;
        }

        if (!$seedShopID) {
            return $masterAsset;
            //abort(419, 'Seed shop not found');
        }


        if ($masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return $masterAsset;
        }

        $masterAsset = ModelHydrateSingleTradeUnits::run($masterAsset);

        $units = null;
        $unit  = null;


        $seedProduct = $masterAsset->products()->where('shop_id', $seedShopID)->first();

        if ($masterAsset->is_single_trade_unit) {
            $tradeUnitData = $masterAsset->tradeUnits->first();
            $units         = $tradeUnitData->pivot->quantity;

            if ($seedProduct && $seedProduct->units != $units) {
                print "\n$seedProduct->code units $seedProduct->units do not match trade units $units ";
                $units = null;
            }
        } elseif ($seedProduct) {
            $units = $seedProduct->units;
        }
        $unit = $seedProduct?->unit;

        $dataToUpdate = [];
        if ($units) {
            $dataToUpdate['units'] = $units;
        }
        if ($unit) {
            $dataToUpdate['unit'] = $unit;
        }

        if ($units || $unit) {
            $masterAsset->update($dataToUpdate);
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

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat('aiku_eta');
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
                    //$bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
