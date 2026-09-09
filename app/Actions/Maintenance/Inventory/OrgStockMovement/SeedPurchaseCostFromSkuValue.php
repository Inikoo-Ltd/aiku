<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gives back a cost basis to org stocks whose purchase movements all arrived unpriced.
 *
 * FIFO and the last purchase price read purchase movements and nothing else, so a stock whose
 * every purchase carries no cost values at nothing, and each picking off it is written with
 * org_amount zero and shows no margin. The stock still holds sku_value, the official per-SKU
 * cost from back when the basis was there, and that is the only evidence left of what the
 * goods cost. It is seeded onto the unpriced purchases as provisional, so FIFO has layers
 * again and it is visible that the figure came from here rather than from a delivery.
 *
 * A stock with one priced purchase is left alone: it has a basis and repricing the rest from a
 * hydrated average would flatten real ones. A stock with no purchase movement at all cannot be
 * reached, there is nothing to price, and inventing a receipt for it would be worse than a gap.
 */
class SeedPurchaseCostFromSkuValue
{
    use AsAction;

    /** @var array<string, float> */
    private array $monthlyRates = [];

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:seed_purchase_cost_from_sku_value {organisation? : organisation slug, all if omitted} {--dry-run}';
    }

    public function handle(OrgStock $orgStock, bool $dryRun): int
    {
        $skuValue = (float)$orgStock->sku_value;

        $movements = OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->where('quantity', '>', 0)
            ->get(['id', 'quantity', 'date']);

        foreach ($movements as $movement) {
            if ($dryRun) {
                continue;
            }

            $orgAmount = round((float)$movement->quantity * $skuValue, 3);

            DB::table('org_stock_movements')->where('id', $movement->id)->update([
                'cost_per_sku' => $skuValue,
                'org_amount'   => $orgAmount,
                'grp_amount'   => round($orgAmount * $this->rateForMonth($orgStock, $movement->date), 3),
                'cost_status'  => OrgStockMovementCostStatusEnum::PROVISIONAL->value,
            ]);
        }

        return $movements->count();
    }

    private function rateForMonth(OrgStock $orgStock, Carbon $date): float
    {
        $key = $orgStock->organisation_id.':'.$date->format('Y-m');

        return $this->monthlyRates[$key] ??= GetHistoricCurrencyExchange::run(
            $orgStock->organisation->currency,
            $orgStock->group->currency,
            $date
        ) ?? 1.0;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool)$command->option('dry-run');

        $organisationId = null;
        if ($organisationSlug = $command->argument('organisation')) {
            $organisationId = DB::table('organisations')->where('slug', $organisationSlug)->value('id');
            if (!$organisationId) {
                $command->error("Organisation not found: {$organisationSlug}");

                return 1;
            }
        }

        $orgStocks = OrgStock::whereNull('deleted_at')
            ->where('sku_value', '>', 0)
            ->when($organisationId, fn ($query) => $query->where('organisation_id', $organisationId))
            ->whereHas('orgStockMovements', function ($query) {
                $query->where('type', OrgStockMovementTypeEnum::PURCHASE->value)->where('quantity', '>', 0);
            })
            ->whereDoesntHave('orgStockMovements', function ($query) {
                $query->where('type', OrgStockMovementTypeEnum::PURCHASE->value)->where('cost_per_sku', '>', 0);
            })
            ->get();

        $seeded = 0;
        foreach ($orgStocks as $orgStock) {
            $seeded += $this->handle($orgStock, $dryRun);
        }

        $command->info(($dryRun ? '[dry run] ' : '')."Seeded {$seeded} purchase movements on {$orgStocks->count()} org stocks");

        return 0;
    }
}
