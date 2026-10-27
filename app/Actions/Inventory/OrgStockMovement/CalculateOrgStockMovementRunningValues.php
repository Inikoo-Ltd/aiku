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
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;
use App\Actions\Traits\AsUniqueUntilProcessingAction;

/**
 * Stamps each movement with the total stock value right after it happened, in all three
 * valuation methods, by replaying the movement stream once per SKU. The stored
 * running_quantity_org_stock stays authoritative for quantity; this only prices it.
 */
class CalculateOrgStockMovementRunningValues implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;
    use CalculatesOrgStockHistories {
        getLppPerSku as lppPerSkuFromPurchases;
    }

    private const int WRITE_CHUNK_SIZE = 1000;

    /** @var array<string, float> */
    private array $lppPerSkuByDay = [];

    public string $jobQueue = 'sales_slave_historic';

    public function getJobUniqueId(int $orgStockId): int
    {
        return $orgStockId;
    }

    public function asJob(int $orgStockId): void
    {
        $this->handle($orgStockId);
    }

    /**
     * Runs for the same org stock are serialised, so one that overlaps another waits, then
     * replays the movements as they are once the earlier run is done, and its values win.
     * A session lock rather than a transaction, so each chunk commits and no movement row
     * stays locked against pickings while the rest of the stock is written.
     */
    public function handle(int $orgStockId): void
    {
        DB::selectOne("select pg_advisory_lock(hashtext('org_stock_movement_running_values'), ?)", [$orgStockId], false);
        try {
            $this->calculate($orgStockId);
        } finally {
            DB::selectOne("select pg_advisory_unlock(hashtext('org_stock_movement_running_values'), ?)", [$orgStockId], false);
        }
    }

    /**
     * Movements without purchases price themselves from the same day lookup again and again.
     */
    public function getLppPerSku(OrgStock $orgStock, Carbon $date): float
    {
        return $this->lppPerSkuByDay[$date->toDateString()] ??= $this->lppPerSkuFromPurchases($orgStock, $date);
    }

    private function calculate(int $orgStockId): void
    {
        $this->lppPerSkuByDay = [];

        $orgStock = OrgStock::find($orgStockId);
        if (!$orgStock) {
            return;
        }

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['id', 'type', 'reason', 'class', 'location_id', 'quantity', 'audited_quantity', 'cost_per_sku', 'org_amount', 'date', 'source_id'])
            ->where('org_stock_id', $orgStock->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        $wacStartDate = $orgStock->organisation->wac_calculations_start_date
            ? Carbon::parse($orgStock->organisation->wac_calculations_start_date)->startOfDay()
            : null;

        $state = $wacStartDate
            ? $this->initValuationState($orgStock, $wacStartDate)
            : ['onHand' => 0.0, 'wac' => null, 'layers' => []];

        $lppPerSku          = null;
        $locationQuantities = [];
        $runningValues      = [];

        foreach ($movements as $movement) {
            $inValuationWindow = $wacStartDate === null || !Carbon::parse($movement->date)->lt($wacStartDate);

            /*
             * Same rule as GetLocationOrgStockQuantity: an audit reseeds the location's level and
             * ordinary movements accumulate on top of it. running_quantity_org_stock is null on
             * most Aurora fetched movements, so the level has to be replayed rather than read.
             */
            if ($movement->location_id) {
                if ($movement->class == OrgStockMovementClassEnum::HELPER) {
                    $locationQuantities[$movement->location_id] = (float) ($movement->audited_quantity ?? 0);
                } elseif ($movement->class == OrgStockMovementClassEnum::MOVEMENT) {
                    $locationQuantities[$movement->location_id] = ($locationQuantities[$movement->location_id] ?? 0) + (float) $movement->quantity;
                }
            }

            if ($wacStartDate !== null && $inValuationWindow) {
                $this->applyMovementToValuation($state, $movement, $orgStock);
            }

            if ($movement->type == OrgStockMovementTypeEnum::PURCHASE && $movement->cost_per_sku > 0) {
                $lppPerSku = (float) $movement->cost_per_sku;
            }
            if ($lppPerSku === null) {
                $lppPerSku = $this->getLppPerSku($orgStock, Carbon::parse($movement->date));
            }

            $quantity   = array_sum($locationQuantities);
            $wacPerSku  = $inValuationWindow && $wacStartDate !== null ? $state['wac'] : null;
            $fifoPerSku = $inValuationWindow && $wacStartDate !== null ? $this->fifoPerSkuFromLayers($state['layers']) : null;

            $runningValues[] = [
                $movement->id,
                round($quantity * $lppPerSku, 2),
                $wacPerSku === null ? null : round($quantity * $wacPerSku, 2),
                $fifoPerSku === null ? null : round($quantity * $fifoPerSku, 2),
            ];
        }

        foreach (array_chunk($runningValues, self::WRITE_CHUNK_SIZE) as $chunk) {
            $this->writeRunningValues($chunk);
        }
    }

    /**
     * @param  array<int, array{0: int, 1: float, 2: ?float, 3: ?float}>  $rows
     */
    private function writeRunningValues(array $rows): void
    {
        $values = implode(',', array_fill(0, count($rows), '(?::bigint, ?::numeric, ?::numeric, ?::numeric)'));

        DB::update(
            "update org_stock_movements as m
                set running_lpp_value = v.lpp, running_wac_value = v.wac, running_fifo_value = v.fifo
               from (values $values) as v(id, lpp, wac, fifo)
              where m.id = v.id
                and (m.running_lpp_value, m.running_wac_value, m.running_fifo_value) is distinct from (v.lpp, v.wac, v.fifo)",
            array_merge(...$rows)
        );
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
