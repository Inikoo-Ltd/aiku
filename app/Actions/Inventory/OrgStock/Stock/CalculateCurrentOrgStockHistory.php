<?php

/*
 * author Louis Perez
 * created on 31-03-2026-15h-43m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Maintenance\Inventory\OrgStockMovement\RepairOrgStockMovements;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateCurrentOrgStockHistory implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'stock-current-history';

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }


    public function handle(OrgStock $orgStock, ?Command $command = null): void
    {
        StoreOrgStockCurrentLocationsStock::run($orgStock, $command);
    }

    public function getCommandSignature(): string
    {
        return 'calculate:org_stock_current {orgStock?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('orgStock')) {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
            $command->info("Processing: {$orgStock->code}");
            $this->handle($orgStock, $command);
            return 1;
        } else {
            $query = OrgStock::has('locations')
                ->orderBy('id');
            $count = $query->clone()->count();
        
            $bar   = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            
            $query
                ->clone()
                ->chunkById(1000, function ($chunkedOrgStocks) use ($command, &$bar) {
                    foreach ($chunkedOrgStocks as $orgStock) {

                        $this->handle($orgStock, $command);
                        
                        $bar->advance();
                    }
                });
                
            $bar->finish();
            return 1;
        }

        return 0;
    }

}
