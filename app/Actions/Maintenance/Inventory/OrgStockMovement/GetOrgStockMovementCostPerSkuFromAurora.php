<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 21:03:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class GetOrgStockMovementCostPerSkuFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;


    public function getCommandSignature(): string
    {
        return 'org_stock_movement:get_cost_per_sku_from_aurora {organisation}';
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $organisation       = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $auroraData = DB::connection('aurora')
            ->table('ITF POTF Costing Done Bridge')->select(['ITF POTF Costing Done ITF Key'])
            ->get();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $progressBar = $command->getOutput()->createProgressBar(count($auroraData));
        $progressBar->setFormat('aiku_eta');
        $progressBar->start();

        foreach ($auroraData as $auroraDatum) {
            $orgStockMovement = OrgStockMovement::where('source_id', $organisation->id.':'.$auroraDatum->{'ITF POTF Costing Done ITF Key'})->first();
            if (!$orgStockMovement) {
                // $command->error('No org stock movement found for ('.$organisation->id.':'.$auroraDatum->{'ITF POTF Costing Done ITF Key'});
            } else {
                $auroraITFData = DB::connection('aurora')
                    ->table('Inventory Transaction Fact')
                    ->select('Inventory Transaction Amount', 'Inventory Transaction Quantity')
                    ->where('Inventory Transaction Key', $auroraDatum->{'ITF POTF Costing Done ITF Key'})->get();

                if ($auroraITFData && $auroraITFData->sum('Inventory Transaction Quantity') != 0) {
                    $costPerSKU = $auroraITFData->sum('Inventory Transaction Amount') / $auroraITFData->sum('Inventory Transaction Quantity');
                    if ($costPerSKU < 0) {
                        $costPerSKU = 0;
                    }
                    if ($costPerSKU > 0) {
                        $orgStockMovement->update(['cost_per_sku' => $costPerSKU]);
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $command->newLine();

        return 0;
    }

}
