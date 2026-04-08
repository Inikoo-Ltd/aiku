<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 20:18:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\GroupStockHistory;

use App\Models\Inventory\GroupStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateMissingGroupStockHistories
{
    use asAction;



    public function getCommandSignature(): string
    {
        return 'repair:create_missing_group_stock_histories';
    }

    public function asCommand(Command $command): void
    {

        $command->info('Creating missing group stock histories');
        foreach (OrganisationStockHistory::all() as $organisationStockHistory) {

            $groupStockHistory = GroupStockHistory::firstOrCreate(
                [
                    'group_id' => $organisationStockHistory->organisation->group_id,
                    'date'     => $organisationStockHistory->date
                ],
                [
                    'is_week'  => $organisationStockHistory->date->isFriday(),
                    'is_month' => $organisationStockHistory->date->isLastOfMonth(),
                    'is_year'  => $organisationStockHistory->date->isEndOfYear(),
                ]
            );

            $organisationStockHistory->update([
                'group_stock_history_id' => $groupStockHistory->id
            ]);


        }

    }

}
