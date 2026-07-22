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
use App\Actions\Maintenance\Inventory\OrgStockMovement\Traits\CanRepairOrgStockMovements;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairLocationOrgStockPurchasesPostMigration implements ShouldBeUnique
{
    use AsAction;
    use CanRepairOrgStockMovements;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }


    public function handle(?int $orgStockId, ?Command $command = null, bool $dryRun = false): void
    {
        if (!$orgStockId) {
            return;
        }
        $orgStock = OrgStock::find($orgStockId);

        if (!$orgStock) {
            return;
        }


        $purchases = OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNotIn('class', [OrgStockMovementClassEnum::GARBAGE->value, OrgStockMovementClassEnum::INFO->value])
            ->where('date', '>', '2026-07-10 03:00:00')
            ->orderBy('date')
            ->get();

        /** @var OrgStockMovement $purchase */
        foreach ($purchases as $purchase) {
            $location = $purchase->location;
            if ($location) {
                $this->fixForAuditsInPairs($location, $orgStock, $command, $dryRun);
                $this->fixForPurchaseAndAssociatePairs($location, $orgStock, $command, $dryRun);
                $this->fixForPostPurchaseAssociates($location, $orgStock, $command, $dryRun);
            }
        }


        $this->fixForPrePurchaseAssociates($orgStock, $command, $dryRun);

        if (!$dryRun) {
            $orgStock->refresh();

            foreach ($orgStock->locations as $location) {
                $locationOrgStock = $orgStock->locationOrgStocks()->where('location_id', $location->id)->first();
                $stockQuantity    = GetLocationOrgStockQuantity::run($orgStock, $location);

                UpdateLocationOrgStock::run(
                    $locationOrgStock,
                    [
                        'quantity' => $stockQuantity
                    ]
                );
            }

            $orgStock->refresh();

            OrgStockHydrateQuantityInLocations::run($orgStock->id);
            $orgStock->refresh();
        }


        $command?->line('Org Stock '.$orgStock->slug.' '.$orgStock->quantity_in_locations);
    }

    public string $commandSignature = 'repair:purchases {--s|org_stock_slug=} {--o|organisation=} {--a|async} {--D|dry-run}';

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

        $orgStocks->whereHas('orgStockMovements', function ($query) {
            $query->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
                ->where('date', '>', '2026-07-10 03:00:00');
        });

        $async  = (bool)$command->option('async');
        $dryRun = (bool)$command->option('dry-run');

        $orgStocks
            ->chunkById(250, function ($orgStockChunk) use ($command, $async, $dryRun) {
                foreach ($orgStockChunk as $orgStock) {
                    $command->info("Processing org stock: $orgStock->slug");
                    if ($async && !$dryRun) {
                        RepairLocationOrgStockPurchasesPostMigration::dispatch($orgStock->id, null, $dryRun);
                    } else {
                        $this->handle($orgStock->id, $command, $dryRun);
                    }
                }
            });

        return 0;
    }
}
