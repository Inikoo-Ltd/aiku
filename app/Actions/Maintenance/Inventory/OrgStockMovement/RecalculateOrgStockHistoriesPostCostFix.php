<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories;
use App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateOrgStockHistoriesPostCostFix
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public const string LPP_AUDIT_CUTOFF = '2026-06-01';

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:recalculate_histories_post_costfix {organisation}';
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

        $command->info('Recalculating histories for '.count($orgStockIds).' org stocks');

        foreach ($orgStockIds as $orgStockId) {
            $orgStock = OrgStock::find($orgStockId);
            if (!$orgStock) {
                continue;
            }
            $this->recalculateOrgStock($orgStock, $command);
        }

        return 0;
    }

    protected function recalculateOrgStock(OrgStock $orgStock, Command $command): void
    {
        $command->line("Recalculating $orgStock->slug ($orgStock->id)");

        $wacStartDate = $orgStock->organisation->wac_calculations_start_date;
        if (!$wacStartDate) {
            $command->warn('No wac_calculations_start_date set, skipping');

            return;
        }

        $historyDates = DB::table('org_stock_histories')
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', Carbon::parse($wacStartDate)->toDateString())
            ->orderBy('date')
            ->pluck('date');

        $auditCutoff = Carbon::parse(self::LPP_AUDIT_CUTOFF);

        foreach ($historyDates as $historyDate) {
            $date = Carbon::parse($historyDate);
            if ($date->lt($auditCutoff)) {
                $this->updateValuationColumnsOnly($orgStock, $date);
            } else {
                CalculateOrgStockHistoricStockHistories::run($orgStock, $date);
            }
        }

        CalculateOrgStockCurrentStockHistories::run($orgStock->id);
    }

    /**
     * Pre audit-cutoff rows: LPP was submitted to auditors and must stay byte-identical,
     * so only the wac/fifo columns are rewritten.
     */
    protected function updateValuationColumnsOnly(OrgStock $orgStock, Carbon $date): void
    {
        $valuation  = $this->getValuationPerSku($orgStock, $date);
        $wacPerSku  = $valuation['wac'];
        $fifoPerSku = $valuation['fifo'];

        $exchangeRate = GetHistoricCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency, $date) ?? 1;

        DB::table('org_stock_histories')
            ->where('org_stock_id', $orgStock->id)
            ->where('date', $date->toDateString())
            ->update([
                'wac_per_sku'          => $wacPerSku,
                'fifo_per_sku'         => $fifoPerSku,
                'org_stock_wac_value'  => $wacPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.$wacPerSku.')'),
                'grp_stock_wac_value'  => $wacPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.($wacPerSku * $exchangeRate).')'),
                'org_stock_fifo_value' => $fifoPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.$fifoPerSku.')'),
                'grp_stock_fifo_value' => $fifoPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.($fifoPerSku * $exchangeRate).')'),
            ]);

        DB::table('location_org_stock_histories')
            ->where('org_stock_id', $orgStock->id)
            ->where('date', $date->toDateString())
            ->update([
                'org_stock_wac_value'  => $wacPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.$wacPerSku.')'),
                'grp_stock_wac_value'  => $wacPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.($wacPerSku * $exchangeRate).')'),
                'org_stock_fifo_value' => $fifoPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.$fifoPerSku.')'),
                'grp_stock_fifo_value' => $fifoPerSku === null ? null : DB::raw('greatest(0, quantity_in_locations * '.($fifoPerSku * $exchangeRate).')'),
            ]);
    }
}
