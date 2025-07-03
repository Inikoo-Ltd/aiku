<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */



/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateTradeUnitsDataFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(): void
    {
        $tadeUnits = DB::table('trade_units')->get()->pluck('id');

        foreach ($tadeUnits as $tradeUnit) {
            $tradeUnit = TradeUnit::where('id', $tradeUnit)->first();


        }
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
        return 'maintenance:update_trade_units_data_from_aurora';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
