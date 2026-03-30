<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 13:24:35 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgStockMovements implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'stock-history';

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock, ?Command $command = null): void
    {
        $locationsIds = $this->getHistoricLocationIds($orgStock);
        $command?->info(' >Locations: '.count($locationsIds));
        foreach ($locationsIds as $locationId) {
            RepairLocationOrgStockMovements::run($locationId, $orgStock->id, $command);
        }

        $orgStock->update([
            'movements_fixed' => true,
        ]);
    }

    public function getHistoricLocationIds(OrgStock $orgStock): array
    {
        return DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->distinct('location_id')->pluck('location_id')->toArray();
    }


    public function getCommandSignature(): string
    {
        return 'repair_org_stock_movements {orgStock?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('orgStock')) {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
            $this->handle($orgStock, $command);

            return 0;
        }

        /** @var OrgStock $orgStock */
        foreach (OrgStock::orderBy('id')->get() as $orgStock) {
            $command->info('Processing '.$orgStock->slug.' ('.$orgStock->id.')');
            RepairOrgStockMovements::dispatch($orgStock);
        }

        return 0;
    }

}
