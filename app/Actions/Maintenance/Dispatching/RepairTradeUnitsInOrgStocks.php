<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 10:24:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\OrgAction;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairTradeUnitsInOrgStocks extends OrgAction
{
    use AsAction;


    public function handle(OrgStock $orgStock): OrgStock
    {
        $stock = $orgStock->stock;

        $tradeUnits = [];
        $tradeUnitsInStock = $stock->tradeUnits;
        foreach ($tradeUnitsInStock as $tradeUnitInStock) {
            $tradeUnits[$tradeUnitInStock->id] = [
                'quantity' => $tradeUnitInStock->pivot->quantity,
            ];
        }
        
        $orgStock->tradeUnits()->sync($tradeUnits);

        return $orgStock;
    }

    public function getCommandSignature(): string
    {
        return 'repair:org_stocks_trade_units';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Matching trade units to org stocks');

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        $totalCount = OrgStock::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        OrgStock::chunk(
            $chunkSize,
            function ($orgStocks) use (&$count, &$matchedCount, $bar, $command) {
                foreach ($orgStocks as $orgStock) {
                    try {
                        $hasTradeUnits = (bool)$orgStock->tradeUnits->count() == 0;
                        $this->handle($orgStock);
                        $hasNowTradeUnits = (bool)$orgStock->tradeUnits->count() >= 1;

                        if (!$hasTradeUnits && $hasNowTradeUnits) {
                            $matchedCount++;
                        }
                    } catch (Exception $e) {
                        $command->error("Error processing org stock $orgStock->id: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count org stocks processed, $matchedCount newly matched to trade units");

        return 0;
    }
}
