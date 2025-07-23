<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairOrgStockMissingLocationIds
{
    use WithActionUpdate;


    public function handle(OrgStock $orgStock): void
    {
        $locationOrgStock = $orgStock->locationOrgStocks->where('picking_priority', 1)->first();
        if ($locationOrgStock) {
            $orgStock->update([
                'picking_location_id' => $locationOrgStock->location_id,
                'picking_dropshipping_location_id' => $locationOrgStock->location_id,
            ]);
        }
    }


    public string $commandSignature = 'repair:org_stock_location_ids';

    public function asCommand(Command $command): void
    {
        $count = OrgStock::whereNull('picking_location_id')
            ->orWhereNull('picking_dropshipping_location_id')
            ->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        OrgStock::orderBy('id')
            ->whereNull('picking_location_id')
            ->orWhereNull('picking_dropshipping_location_id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
