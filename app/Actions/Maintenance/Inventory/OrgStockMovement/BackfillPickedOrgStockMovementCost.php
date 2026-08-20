<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fills org_amount on historic picking movements that were written before cost capture
 * existed (empty org_amount, before July 2026). One chronological FIFO walk per org stock,
 * starting at the organisation's wac_calculations_start_date: at every picking movement
 * with no cost, the per-SKU cost is what the FIFO layers held at that moment, priced from
 * the purchase movements already imported. Movements before the WAC start date have no
 * valuation basis and are left blank on purpose: a wrong-but-plausible cost is worse than
 * none.
 *
 * Only fills empty values, never overwrites: RepairPickedOrgStockMovementCost owns the
 * July-August rows that hold a wrong non-zero cost.
 */
class BackfillPickedOrgStockMovementCost
{
    use AsAction;
    use CalculatesOrgStockHistories;

    private const PICKING_TYPES = [
        OrgStockMovementTypeEnum::PICKED->value,
        OrgStockMovementTypeEnum::CANCEL_PICKED->value,
        OrgStockMovementTypeEnum::RETURN_PICKED->value,
        OrgStockMovementTypeEnum::CANCEL_RETURN_PICKED->value,
    ];

    /** @var array<string, float> */
    private array $monthlyRates = [];

    /**
     * --before defaults to 'now' (Carbon::parse understands it) so the window overlaps the
     * repair window on purpose: repair zeroes the corrupt rows, backfill refills them, and
     * rows the fixed live code wrote correctly are non-zero already, so they're never touched.
     */
    public function getCommandSignature(): string
    {
        return 'org_stock_movement:backfill_picked_cost {organisation? : organisation slug, all if omitted} {--before=now} {--dry-run} {--from-org-stock=0}';
    }

    /**
     * @return array{filled: int, skipped_no_cost: int}
     */
    public function handle(OrgStock $orgStock, string $before, bool $dryRun): array
    {
        $wacStartDate = $orgStock->organisation->wac_calculations_start_date;
        if (!$wacStartDate) {
            return ['filled' => 0, 'skipped_no_cost' => 0];
        }
        $wacStartDate = Carbon::parse($wacStartDate);

        $state = $this->initValuationState($orgStock, $wacStartDate);

        $filled  = 0;
        $skipped = 0;

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['id', 'type', 'quantity', 'cost_per_sku', 'org_amount', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->copy()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->orderBy('date')
            ->orderBy('id')
            ->cursor();

        $lastPurchaseCost = null;

        foreach ($movements as $movement) {
            if ($movement->type === OrgStockMovementTypeEnum::PURCHASE && $movement->quantity > 0) {
                if ((float) $movement->cost_per_sku > 0) {
                    $lastPurchaseCost = (float) $movement->cost_per_sku;
                } elseif ((float) $movement->org_amount > 0) {
                    $lastPurchaseCost = (float) $movement->org_amount / (float) $movement->quantity;
                }
            }

            $needsCost = in_array($movement->type->value, self::PICKING_TYPES)
                && !((float) $movement->org_amount)
                && $movement->date->lt(Carbon::parse($before));

            if ($needsCost) {
                $costPerSku = $this->fifoPerSkuFromLayers($state['layers']) ?? $lastPurchaseCost ?? $this->getLppPerSku($orgStock, Carbon::parse($movement->date));

                if ($costPerSku > 0) {
                    $filled++;
                    if (!$dryRun) {
                        $rate = $this->rateForMonth($orgStock, $movement->date);
                        DB::table('org_stock_movements')->where('id', $movement->id)->update([
                            'org_amount' => round($movement->quantity * $costPerSku, 3),
                            'grp_amount' => round($movement->quantity * $costPerSku * $rate, 3),
                        ]);
                    }
                } else {
                    $skipped++;
                }
            }

            $this->applyMovementToValuation($state, $movement, $orgStock);
        }

        return ['filled' => $filled, 'skipped_no_cost' => $skipped];
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
        $dryRun = (bool) $command->option('dry-run');
        $before = (string) $command->option('before');
        $fromOrgStock = (int) $command->option('from-org-stock');

        $organisationSlug = $command->argument('organisation');
        $organisationId   = null;
        if ($organisationSlug) {
            $organisationId = DB::table('organisations')->where('slug', $organisationSlug)->value('id');
            if (!$organisationId) {
                $command->error("Organisation not found: {$organisationSlug}");

                return 1;
            }
        }

        $orgStockIds = DB::table('org_stock_movements')
            ->whereIn('type', self::PICKING_TYPES)
            ->where('date', '<', $before)
            ->where(function ($q) {
                $q->whereNull('org_amount')->orWhere('org_amount', 0);
            })
            ->when($organisationId, function ($q, $organisationId) {
                $q->where('organisation_id', $organisationId);
            })
            ->distinct()
            ->orderBy('org_stock_id')
            ->pluck('org_stock_id');

        $command->info(($dryRun ? '[dry run] ' : '')."{$orgStockIds->count()} org stocks with unfilled picking costs before $before");

        $totals    = ['filled' => 0, 'skipped_no_cost' => 0];
        $bar       = $command->getOutput()->createProgressBar($orgStockIds->count());
        $processed = 0;

        foreach ($orgStockIds as $orgStockId) {
            if ($orgStockId <= $fromOrgStock) {
                continue;
            }

            $orgStock = OrgStock::find($orgStockId);
            if (!$orgStock) {
                continue;
            }
            $result = $this->handle($orgStock, $before, $dryRun);
            $totals['filled']          += $result['filled'];
            $totals['skipped_no_cost'] += $result['skipped_no_cost'];
            $bar->advance();

            $processed++;
            if ($processed % 500 === 0) {
                $command->newLine();
                $command->info("resume marker: --from-org-stock={$orgStockId}");
            }
        }
        $bar->finish();
        $command->newLine();

        $command->info(($dryRun ? '[dry run] ' : '')."Filled {$totals['filled']} picking movements, {$totals['skipped_no_cost']} left blank with no cost basis");

        return 0;
    }
}
