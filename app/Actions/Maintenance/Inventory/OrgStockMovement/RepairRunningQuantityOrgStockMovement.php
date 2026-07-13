<?php

/*
 * Author Louis Perez
 * Created on 13-07-2026-13h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStockMovement\CalculateRunningQuantityOrgStockMovement;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairRunningQuantityOrgStockMovement
{
    use AsAction;

    public function handle(OrgStock $orgStock, Command $command)
    {
        foreach (
            $orgStock->orgStockMovements()->orderBy('date', 'asc')->get() as $movement
        ) {
            $command->info("Repairing: {$orgStock->slug} {$movement->date}");
            CalculateRunningQuantityOrgStockMovement::run($movement);
        }
    }

    public string $commandSignature = 'repair:running_quantity_org_stock_movement {--org_stock_slug=} {--organisation=}';

    public function asCommand(Command $command)
    {
        $orgStockSlug = $command->option('org_stock_slug');
        $organisationSlug = $command->option('organisation');
        $organisation = null;
        
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

        $orgStocks
            ->chunkById(250, function ($orgStockChunk) use ($command) {
                foreach($orgStockChunk as $orgStock) {
                    $this->handle($orgStock, $command);
                }
            });
    }
}
