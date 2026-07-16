<?php

namespace App\Actions\Maintenance\Inventory\LocationOrgStock;

use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLocationOrgStockDefaults implements ShouldBeUnique
{
    use AsAction;
    
    public string $jobQueue = 'sales_slave_historic';

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }

    
    public function handle(LocationOrgStock $locationOrgStock, ?Command $command = null)
    {
        $locationOrgStock->update([
            'default_wholesale_picking_location'        => true,
            'default_dropshipping_picking_location'     => true,
        ]);

        $command?->info("{$locationOrgStock->orgStock->slug}: {$locationOrgStock->location->slug} Set as Default Wholesale && Dropshipping Picking");
    }

    public string $commandSignature = 'repair:location_org_stock_defaults {--o|organisation=} {--a|async}';

    public function asCommand(Command $command): int
    {
        $organisationSlug = $command->option('organisation');
        $organisation     = null;

        if ($organisationSlug) {
            $organisation = Organisation::where('slug', $organisationSlug)->first();
        }

        $locationOrgStocks = LocationOrgStock::query();

        if ($organisation) {
            $locationOrgStocks->where('organisation_id', $organisation->id);
        }

        $async = (bool)$command->option('async');

        $locationOrgStocks
            ->where('picking_priority', 1)
            ->chunkById(250, function ($loops) use ($command, $async) {
                foreach ($loops as $locationOrgStock) {
                    if ($async) {
                        RepairLocationOrgStockDefaults::dispatch($locationOrgStock);
                    } else {
                        $this->handle($locationOrgStock, $command);
                    }
                }
            });

        return 0;
    }
}
