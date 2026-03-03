<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Mar 2026 01:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use DB;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateOrgStocksDataFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(OrgStock $orgStock, Command $command): void
    {
        if (!$orgStock->is_single_trade_unit) {
            return;
        }

        $sources = explode(':', $orgStock->source_id);


        $auData = DB::connection('aurora')->table('Part Dimension')->where('Part SKU', $sources[1])->first();

        $units       = $auData->{'Part Units Per Package'};
        $oldQuantity = null;

        $tradeUnitsData = [];
        foreach ($orgStock->tradeUnits as $tradeUnit) {
            $oldQuantity                    = $tradeUnit->pivot->quantity;
            $tradeUnitsData[$tradeUnit->id] = [
                'quantity' => $units,
            ];
        }

        if (count($tradeUnitsData) == 0) {
            $command->error('No trade units found for ('.$orgStock->source_id.') '.$orgStock->state->value.' '.$orgStock->slug);

            return;
        }

        $diff = $units - $oldQuantity;

        if ($diff != 0) {
            $command->info('Updating  ('.$orgStock->source_id.') '.$orgStock->state->value.' '.$orgStock->slug.' '.trimDecimalZeros($oldQuantity).' -> '.$units);
        }

        SyncOrgStockTradeUnits::run($orgStock, $tradeUnitsData);
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
        return 'maintenance:update_org_stocks_data_from_aurora {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $this->setSource($organisation);
        OrgStock::where('organisation_id', $organisation->id)
            ->whereNotNull('source_id')
            ->chunk(100, function ($orgStocks) use ($command) {
                foreach ($orgStocks as $orgStock) {
                    $this->handle($orgStock, $command);
                }
            });

        return 0;
    }

}
