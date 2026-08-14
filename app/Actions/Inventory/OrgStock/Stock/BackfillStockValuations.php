<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class BackfillStockValuations
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $jobQueue = 'sales_slave_historic';

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
        $this->backfillOrgStock($orgStock, Carbon::parse($orgStock->organisation->wac_calculations_start_date));
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:backfill_valuations {organisation : Organisation slug} {--sync : Run inline instead of dispatching Horizon jobs}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        if (!$organisation->wac_calculations_start_date) {
            $command->error("Organisation $organisation->slug has no wac_calculations_start_date set");

            return 1;
        }

        $wacStartDate = Carbon::parse($organisation->wac_calculations_start_date);

        $orgStockIds = DB::table('org_stock_histories')
            ->join('org_stocks', 'org_stocks.id', '=', 'org_stock_histories.org_stock_id')
            ->where('org_stock_histories.organisation_id', $organisation->id)
            ->where('org_stock_histories.date', '>=', $wacStartDate->format('Y-m-d'))
            ->groupBy('org_stock_histories.org_stock_id')
            ->orderByRaw('max(org_stock_histories.org_stock_lpp_value) DESC NULLS LAST')
            ->pluck('org_stock_histories.org_stock_id');

        if (!$command->option('sync')) {
            foreach ($orgStockIds as $orgStockId) {
                self::dispatch($orgStockId);
            }
            $command->info(count($orgStockIds).' backfill jobs dispatched to Horizon; run org_stock:backfill_valuations_rollup '.$organisation->slug.' once the queue drains');

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

        $command->info('Rolling up organisation and group stock histories');
        RollUpStockValuations::run($organisation);

        return 0;
    }

    private function backfillOrgStock(OrgStock $orgStock, Carbon $wacStartDate): void
    {
        $state = $this->initValuationState($orgStock, $wacStartDate);

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['type', 'quantity', 'cost_per_sku', 'org_amount', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->copy()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->orderBy('date')
            ->orderBy('id')
            ->get()->all();

        $histories = DB::connection('aiku_no_sticky')->table('org_stock_histories')
            ->select(['id', 'date', 'quantity_in_locations', 'org_stock_lpp_value', 'grp_stock_lpp_value'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $movementIndex = 0;
        foreach ($histories as $history) {
            $endOfDay = Carbon::parse($history->date)->endOfDay();

            while ($movementIndex < count($movements) && Carbon::parse($movements[$movementIndex]->date)->lte($endOfDay)) {
                $this->applyMovementToValuation($state, $movements[$movementIndex], $orgStock);
                $movementIndex++;
            }

            $effectiveWac  = $state['wac'] ?? $this->getLppPerSku($orgStock, Carbon::parse($history->date));
            $effectiveFifo = $this->fifoPerSkuFromLayers($state['layers']) ?? $this->getLppPerSku($orgStock, Carbon::parse($history->date));
            $exchangeRate  = $this->getExchangeRate($orgStock, $history);

            DB::table('org_stock_histories')->where('id', $history->id)->update([
                'wac_per_sku'          => $effectiveWac,
                'org_stock_wac_value'  => $history->quantity_in_locations * $effectiveWac,
                'grp_stock_wac_value'  => $history->quantity_in_locations * $effectiveWac * $exchangeRate,
                'fifo_per_sku'         => $effectiveFifo,
                'org_stock_fifo_value' => $history->quantity_in_locations * $effectiveFifo,
                'grp_stock_fifo_value' => $history->quantity_in_locations * $effectiveFifo * $exchangeRate,
            ]);

            DB::table('location_org_stock_histories')
                ->where('org_stock_history_id', $history->id)
                ->update([
                    'org_stock_wac_value'  => DB::raw("quantity_in_locations * $effectiveWac"),
                    'grp_stock_wac_value'  => DB::raw("quantity_in_locations * $effectiveWac * $exchangeRate"),
                    'org_stock_fifo_value' => DB::raw("quantity_in_locations * $effectiveFifo"),
                    'grp_stock_fifo_value' => DB::raw("quantity_in_locations * $effectiveFifo * $exchangeRate"),
                ]);
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
            );
        }

        return $this->exchangeRates[$history->date];
    }

}
