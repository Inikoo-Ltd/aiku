<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateOrgStockHistoriesPostCostFix
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $jobQueue = 'sales_slave_historic';

    /**
     * LPP before this date was submitted to auditors and must stay byte-identical,
     * so those rows get their wac/fifo columns rewritten and nothing else.
     */
    public const string LPP_AUDIT_CUTOFF = '2026-06-01';

    private array $exchangeRates = [];

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
        if (!$orgStock || !$orgStock->organisation->wac_calculations_start_date) {
            return;
        }

        $wacStartDate = Carbon::parse($orgStock->organisation->wac_calculations_start_date);
        $fromDate     = $this->getRecalculationFloor($orgStock, $wacStartDate);

        $this->recalculateOrgStock($orgStock, $wacStartDate, $fromDate);

        CalculateOrgStockCurrentStockHistories::run($orgStock->id);
    }

    /**
     * A history row can only change if a repaired movement lands on or before its date, so the
     * walk starts at the SKU's first repaired movement. The shortcut only holds while every
     * date in range resolves its last purchase price backwards: without a priced purchase before
     * the valuation start, getLppPerSku falls forward onto a later (possibly repaired) purchase
     * and seeds earlier rows with it, so those SKUs are walked in full.
     */
    private function getRecalculationFloor(OrgStock $orgStock, Carbon $wacStartDate): Carbon
    {
        $hasPricedPurchaseBeforeStart = OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->where('cost_per_sku', '>', 0)
            ->where('date', '<=', $wacStartDate->format('Y-m-d H:i:s.u'))
            ->exists();

        if (!$hasPricedPurchaseBeforeStart) {
            return $wacStartDate;
        }

        $firstRepaired = OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('cost_status', OrgStockMovementCostStatusEnum::DELIVERY->value)
            ->min('date');

        if (!$firstRepaired) {
            return $wacStartDate;
        }

        return Carbon::parse($firstRepaired)->max($wacStartDate);
    }

    /**
     * The repair changed costs only, never quantities, so stored quantities stay authoritative and
     * only the cost-derived columns are rewritten. Movements are consumed once, in date order,
     * alongside the history rows rather than replayed per date.
     */
    private function recalculateOrgStock(OrgStock $orgStock, Carbon $wacStartDate, Carbon $fromDate): void
    {
        $state = $this->initValuationState($orgStock, $wacStartDate);

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['type', 'quantity', 'cost_per_sku', 'org_amount', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->copy()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->orderBy('date')
            ->get()->all();

        $histories = DB::connection('aiku_no_sticky')->table('org_stock_histories')
            ->select(['id', 'date', 'quantity_in_locations', 'org_stock_lpp_value', 'grp_stock_lpp_value'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        $auditCutoff   = Carbon::parse(self::LPP_AUDIT_CUTOFF);
        $movementIndex = 0;

        foreach ($histories as $history) {
            $date     = Carbon::parse($history->date);
            $endOfDay = $date->copy()->endOfDay();

            while ($movementIndex < count($movements) && Carbon::parse($movements[$movementIndex]->date)->lte($endOfDay)) {
                $this->applyMovementToValuation($state, $movements[$movementIndex], $orgStock);
                $movementIndex++;
            }

            if ($date->lt($fromDate)) {
                continue;
            }

            $wacPerSku    = $state['wac'] ?? $this->getLppPerSku($orgStock, $date);
            $fifoPerSku   = $this->fifoPerSkuFromLayers($state['layers']) ?? $this->getLppPerSku($orgStock, $date);
            $exchangeRate = $this->getExchangeRate($orgStock, $history);
            $quantity     = $history->quantity_in_locations;

            $updateData = [
                'wac_per_sku'          => $wacPerSku,
                'org_stock_wac_value'  => $quantity * $wacPerSku,
                'grp_stock_wac_value'  => $quantity * $wacPerSku * $exchangeRate,
                'fifo_per_sku'         => $fifoPerSku,
                'org_stock_fifo_value' => $quantity * $fifoPerSku,
                'grp_stock_fifo_value' => $quantity * $fifoPerSku * $exchangeRate,
            ];

            $locationUpdateData = [
                'org_stock_wac_value'  => DB::raw("quantity_in_locations * $wacPerSku"),
                'grp_stock_wac_value'  => DB::raw("quantity_in_locations * $wacPerSku * $exchangeRate"),
                'org_stock_fifo_value' => DB::raw("quantity_in_locations * $fifoPerSku"),
                'grp_stock_fifo_value' => DB::raw("quantity_in_locations * $fifoPerSku * $exchangeRate"),
            ];

            if ($date->gte($auditCutoff)) {
                $lppPerSku                            = $this->getLppPerSku($orgStock, $date);
                $updateData['lpp_per_sku']            = $lppPerSku;
                $updateData['org_stock_lpp_value']    = $quantity * $lppPerSku;
                $updateData['grp_stock_lpp_value']    = $quantity * $lppPerSku * $exchangeRate;
                $locationUpdateData['org_stock_lpp_value'] = DB::raw("quantity_in_locations * $lppPerSku");
                $locationUpdateData['grp_stock_lpp_value'] = DB::raw("quantity_in_locations * $lppPerSku * $exchangeRate");
            }

            DB::table('org_stock_histories')->where('id', $history->id)->update($updateData);
            DB::table('location_org_stock_histories')->where('org_stock_history_id', $history->id)->update($locationUpdateData);
        }
    }

    private function getExchangeRate(OrgStock $orgStock, object $history): float
    {
        if ($history->org_stock_lpp_value > 0) {
            return $history->grp_stock_lpp_value / $history->org_stock_lpp_value;
        }

        if (!isset($this->exchangeRates[$history->date])) {
            $this->exchangeRates[$history->date] = GetHistoricCurrencyExchange::run(
                $orgStock->organisation->currency,
                $orgStock->group->currency,
                Carbon::parse($history->date)
            ) ?? 1;
        }

        return $this->exchangeRates[$history->date];
    }

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:recalculate_histories_post_costfix {organisation} {--sync : Run inline instead of dispatching Horizon jobs}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        $orgStockIds = DB::table('org_stock_movements')
            ->where('organisation_id', $organisation->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->where('cost_status', OrgStockMovementCostStatusEnum::DELIVERY->value)
            ->distinct()
            ->pluck('org_stock_id');

        if (!$command->option('sync')) {
            foreach ($orgStockIds as $orgStockId) {
                self::dispatch($orgStockId);
            }
            $command->info(count($orgStockIds).' recalculation jobs dispatched to Horizon queue '.$this->jobQueue);
            $command->info('Run org_stock_movement:recalculate_histories_post_costfix_rollup '.$organisation->slug.' once the queue drains');

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

        RollUpOrgStockHistoriesPostCostFix::run($organisation);

        return 0;
    }
}
