<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 14:55:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnused */


namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLocationOrgStockQuantity implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }


    public function handle(?int $orgStockId, ?Command $command=null): void
    {
        if (!$orgStockId) {
            return;
        }
        $orgStock = OrgStock::find($orgStockId);

        if (!$orgStock) {
            return;
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

        OrgStockHydrateQuantityInLocations::run($orgStock->id);
        $orgStock->refresh();


        $command?->line('Org Stock '.$orgStock->slug.' '.$orgStock->quantity_in_locations);
    }

    public string $commandSignature = 'repair:location_org_stock_quantity {--s|org_stock_slug=} {--o|organisation=} {--a|async}';

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
                        RepairLocationOrgStockQuantity::dispatch($orgStock->id);
                    } else {
                        $this->handle($orgStock->id, $command);
                    }
                }
            });

        return 0;
    }
}
