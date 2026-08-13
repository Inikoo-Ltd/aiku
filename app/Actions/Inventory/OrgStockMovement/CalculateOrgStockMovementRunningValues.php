<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 01:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stamps each movement with the total stock value right after it happened, in all three
 * valuation methods, by replaying the movement stream once per SKU. The stored
 * running_quantity_org_stock stays authoritative for quantity; this only prices it.
 */
class CalculateOrgStockMovementRunningValues
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $jobQueue = 'sales_slave_historic';

    public function getJobUniqueId(int $orgStockId): int
    {
        return $orgStockId;
    }

    public function asJob(int $orgStockId): void
    {
        $this->handle($orgStockId);
    }

    public function handle(int $orgStockId): void
    {
        $orgStock = OrgStock::find($orgStockId);
        if (!$orgStock) {
            return;
        }

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['id', 'type', 'quantity', 'cost_per_sku', 'org_amount', 'date', 'running_quantity_org_stock'])
            ->where('org_stock_id', $orgStock->id)
            ->where('class', '!=', OrgStockMovementClassEnum::HELPER)
            ->orderBy('date')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        $state      = ['onHand' => 0.0, 'wac' => null, 'layers' => []];
        $lppPerSku  = null;

        foreach ($movements as $movement) {
            $this->applyMovementToValuation($state, $movement, $orgStock);

            if ($movement->type == OrgStockMovementTypeEnum::PURCHASE && $movement->cost_per_sku > 0) {
                $lppPerSku = (float) $movement->cost_per_sku;
            }
            if ($lppPerSku === null) {
                $lppPerSku = $this->getLppPerSku($orgStock, Carbon::parse($movement->date));
            }

            $quantity   = (float) ($movement->running_quantity_org_stock ?? $state['onHand']);
            $wacPerSku  = $state['wac'];
            $fifoPerSku = $this->fifoPerSkuFromLayers($state['layers']);

            DB::table('org_stock_movements')->where('id', $movement->id)->update([
                'running_lpp_value'  => round($quantity * $lppPerSku, 2),
                'running_wac_value'  => $wacPerSku === null ? null : round($quantity * $wacPerSku, 2),
                'running_fifo_value' => $fifoPerSku === null ? null : round($quantity * $fifoPerSku, 2),
            ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:calculate_running_values {organisation} {--days= : Only SKUs with movements in the last n days} {--sync : Run inline instead of dispatching Horizon jobs}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        $orgStockIds = DB::table('org_stock_movements')
            ->where('organisation_id', $organisation->id)
            ->when($command->option('days'), fn ($query, $days) => $query->where('date', '>=', now()->subDays((int) $days)))
            ->distinct()
            ->pluck('org_stock_id');

        if (!$command->option('sync')) {
            foreach ($orgStockIds as $orgStockId) {
                self::dispatch($orgStockId);
            }
            $command->info(count($orgStockIds).' running-value jobs dispatched to Horizon queue '.$this->jobQueue);

            return 0;
        }

        $progressBar = $command->getOutput()->createProgressBar(count($orgStockIds));
        $progressBar->start();
        foreach ($orgStockIds as $orgStockId) {
            $this->handle($orgStockId);
            $progressBar->advance();
        }
        $progressBar->finish();
        $command->newLine();

        return 0;
    }
}
