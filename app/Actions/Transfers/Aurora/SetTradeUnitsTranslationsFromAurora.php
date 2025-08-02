<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 20:03:55 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Traits\WithOrganisationSourceShop;
use App\Models\Goods\TradeUnit;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetTradeUnitsTranslationsFromAurora extends OrgAction
{
    use AsAction;
    use WithOrganisationSource;
    use WithOrganisationSourceShop;


    /**
     * @throws \Exception
     */
    public function handle(TradeUnit $tradeUnit): TradeUnit
    {
        $code = $tradeUnit->code;

        $translationName = [];
        foreach ($this->getOrganisationSourceShop() as $lang => $organisationShopIds) {
            $translationName[$lang] = null;

            foreach ($organisationShopIds as $organisationId => $shopId) {
                $auroraProduct = DB::connection('aurora_'.$organisationId)
                    ->table('Product Dimension')
                    ->select('Product Name')
                    ->where('Product Store Key', $shopId)
                    ->whereRaw('LOWER(`Product Code`) = LOWER(?)', [$code])
                    ->first();
                if ($auroraProduct) {
                    $translationName[$lang] = $auroraProduct->{'Product Name'};
                    break;
                }
            }
        }

        $tradeUnit->setTranslations('name_i8n', $translationName);
        $tradeUnit->save();

        return $tradeUnit;
    }


    public function getCommandSignature(): string
    {
        return 'set:trade_units_translations_from_aurora';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Setting trade units translations from Aurora');

        $chunkSize = 100;
        $count     = 0;

        // Create a progress bar
        $bar = $command->getOutput()->createProgressBar(TradeUnit::count());
        $bar->start();

        // Process trade units in chunks to avoid memory issues
        TradeUnit::chunk(
            $chunkSize,
            function ($tradeUnits) use (&$count, $bar, $command) {
                foreach ($tradeUnits as $tradeUnit) {
                    try {
                        $this->handle($tradeUnit);
                    } catch (Exception $e) {
                        $command->error($e->getMessage());
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count trade units processed");

        return 0;
    }
}
