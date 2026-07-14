<?php

/** @noinspection PhpUnused */

/*
 * Author Louis Perez
 * Created on 13-07-2026-13h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
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

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }


    public function handle(?int $orgStockId, ?Command $command = null): void
    {
        if (!$orgStockId) {
            return;
        }
        $orgStock = OrgStock::find($orgStockId);

        if (!$orgStock) {
            return;
        }

        /** @var OrgStockMovement $movement */
        foreach (
            $orgStock->orgStockMovements()->orderBy('date')->get() as $movement
        ) {
            $movement = CalculateRunningQuantityOrgStockMovement::run($movement->id);
            $command?->info("$movement->date $orgStock->slug {$movement->location?->code} $movement->running_quantity $movement->running_quantity_org_stock  ");
        }

        foreach (
            $orgStock->locations as $location
        ) {
            $locationOrgStock = $orgStock->locationOrgStocks()->where('location_id', $location->id)->first();
            $stockQuantity    = GetLocationOrgStockQuantity::run($orgStock, $location);
            UpdateLocationOrgStock::run(
                $locationOrgStock,
                [
                    'quantity' => $stockQuantity
                ]
            );
            $command?->info("$location->code $stockQuantity");
        }

        $orgStock->refresh();
        $command?->line('Org Stock '.$orgStock->slug.' '.$orgStock->quantity_in_locations);
    }

    public string $commandSignature = 'repair:running_quantity_org_stock_movement {--s|org_stock_slug=} {--o|organisation=} {--a|async}';

    public function asCommand(Command $command): int
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

        $async = (bool)$command->option('async');

        $orgStocks
            ->chunkById(250, function ($orgStockChunk) use ($command, $async) {
                foreach ($orgStockChunk as $orgStock) {
                    if ($async) {
                        RepairRunningQuantityOrgStockMovement::dispatch($orgStock->id);
                    } else {
                        $this->handle($orgStock->id, $command);
                    }
                }
            });

        return 0;
    }
}
