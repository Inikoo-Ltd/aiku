<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 20:03:55 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetTradeUnitsTranslationsFromAurora extends OrgAction
{
    use AsAction;
    use WithOrganisationSource;

    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;


    /**
     * @throws \Exception
     */
    public function handle(TradeUnit $tradeUnit)
    {


        $code = $tradeUnit->code;

        $translationName = [];
        foreach ($this->getOrganisationSourceShop() as $lang => $organisationShopIds) {
            $translationName[$lang] = null;

            foreach ($organisationShopIds as $organisationId => $shopId) {

                $organisation = Organisation::where('id', $organisationId)->first();

                $this->setSource($organisation);

                $auroraProduct = DB::connection('aurora')->table('Product Dimension')->select('Product Name')->where('Product Store Key', $shopId)->where('Product Code', $code)->first();

                if ($auroraProduct) {
                    $translationName[$lang] = $auroraProduct->{'Product Name'};
                    break;
                }
                exit;
            }
        }
        print_r($translationName);


        return $tradeUnit;
    }




    public function getOrganisationSourceShop(): array
    {
        return [
            'es' => [
                3 => 1
            ],
            'fr' => [
                2 => 6,
                3 => 6,
            ],
            'de' => [
                2 => 4,
                3 => 7,
            ],
            'it' => [
                2 => 8,
            ],
            'pl' => [
                2 => 10,
            ],
            'sk' => [
                2 => 12,
            ],
            'pt' => [
                3 => 2,
            ],
            'cs' => [
                2 => 14,
            ],
            'hu' => [
                2 => 16,
            ],
            'nl' => [
                2 => 20,
            ],
            'ro' => [
                2 => 21,
            ],
            'sv' => [
                2 => 23,
            ],
            'hr' => [
                2 => 24,
            ],
            'bg' => [
                2 => 25,
            ]
        ];
    }


    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
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
        TradeUnit::where('id', 10844)->where('source_id', '!=', null)
            ->chunk($chunkSize, function ($tradeUnits) use (&$count, $bar) {
                foreach ($tradeUnits as $tradeUnit) {
                    $this->handle($tradeUnit);
                    $count++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
        $command->info("$count trade units processed");

        return 0;
    }
}
