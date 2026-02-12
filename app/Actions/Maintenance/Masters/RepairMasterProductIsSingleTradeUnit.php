<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterProductIsSingleTradeUnit extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        ModelHydrateSingleTradeUnits::run($masterAsset);
        return $masterAsset;
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_product_is_single_trade_unit';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {

        $command->info('Fix master product is single trade unit');

        $chunkSize = 100;
        $count     = 0;

        $totalCount = MasterAsset::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        MasterAsset::chunk(
            $chunkSize,
            function ($masterAssets) use (&$count, $bar, $command) {
                foreach ($masterAssets as $masterAsset) {
                    try {
                        $this->handle($masterAsset);
                    } catch (Exception $e) {
                        $command->error("Error processing master asset $masterAsset->id: {$e->getMessage()}");
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
