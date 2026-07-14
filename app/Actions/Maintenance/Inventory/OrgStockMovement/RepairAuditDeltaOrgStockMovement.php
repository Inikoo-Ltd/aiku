<?php

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\CalculateRunningQuantityOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuditDeltaOrgStockMovement implements ShouldBeUnique
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $jobQueue = 'sales_slave_historic';

    public function getJobUniqueId(?int $orgStockId): string
    {
        return $orgStockId ?? 'empty';
    }


    public function handle(?int $orgStockId, Command $command): void
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
            $orgStock->orgStockMovements()->where('flow', OrgStockMovementFlowEnum::AUDIT)->with('location')->orderBy('date')->get() as $movement
        ) {
            $runningQuantity = $this->getStockQuantity($orgStock, $movement->location, $movement->date->subSecond());
            // auditedQuantity - Running Quantity on that location is the delta
            // Previous 20, audited 16
            // 16 - 20 = -4 <- Delta
            // Previous 20, audited 24
            // 24 - 20 = +4 <-delta
            $movement->update([
                'quantity'  => $movement->audited_quantity - $runningQuantity
            ]);
            $command->info("$movement->date $orgStock->slug {$movement->location->code} $movement->audited_quantity $movement->running_quantity_org_stock $movement->quantity  ");

        }

        $orgStock->refresh();
    }

    public string $commandSignature = 'repair:audit_delta_org_stock_movement {--s|org_stock_slug=} {--o|organisation=} {--a|async}';

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
                        RepairAuditDeltaOrgStockMovement::dispatch($orgStock->id);
                    } else {
                        $this->handle($orgStock->id, $command);
                    }
                }
            });

        return 0;
    }
}
