<?php
/** @noinspection PhpUnused */

/*
 * Author Louis Perez
 * Created on 13-07-2026-13h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStockMovement\CalculateRunningQuantityOrgStockMovement;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairRunningQuantityOrgStockMovement implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales_slave_historic';

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }


    public function handle(OrgStock $orgStock, Command $command): void
    {
        /** @var OrgStockMovement $movement */
        foreach (
            $orgStock->orgStockMovements()->orderBy('date')->get() as $movement
        ) {
            $command->info("Repairing: $orgStock->slug $movement->date");
            CalculateRunningQuantityOrgStockMovement::run($movement->id);
        }
    }

    public string $commandSignature = 'repair:running_quantity_org_stock_movement {--org_stock_slug=} {--organisation=} {--async}';

    public function asCommand(Command $command): int
    {
        $orgStockSlug     = $command->option('org_stock_slug');
        $organisationSlug = $command->option('organisation');
        $organisation     = null;

        if ($organisationSlug) {
            $organisation = Organisation::where('slug', $organisationSlug)->first();
        }

        $orgStocks = OrgStock::query();

        if ($orgStockSlug) {
            $orgStocks->where('slug', $orgStockSlug);
        }

        if ($organisation) {
            $orgStocks->where('organisation_id', $organisation->id);
        }

        $async = (bool)$command->option('async');

        $orgStocks
            ->chunkById(250, function ($orgStockChunk) use ($command, $async) {
                foreach ($orgStockChunk as $orgStock) {
                    if ($async) {
                        $this->dispatch($orgStock, $command);
                    } else {
                        $this->handle($orgStock, $command);
                    }
                }
            });

        return 0;
    }
}
