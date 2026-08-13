<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Restores corrupted purchase org_amounts straight from Aurora's Inventory Transaction Fact,
 * which is the source of truth; the refetch corruption inflated Aiku's copy while Aurora kept
 * the real figure. Covers movements the delivery-line repair could not match, including ones
 * whose movement units differ from the delivery line's unit of measure.
 */
class RepairOrgStockMovementAmountFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:repair_amount_from_aurora {organisation} {--dry-run : Report what would change without writing}';
    }

    public static function shouldRepair(object $movement, ?object $auroraTransaction): bool
    {
        if (!$auroraTransaction) {
            return false;
        }
        if (abs((float) $movement->quantity - (float) $auroraTransaction->quantity) > 0.01) {
            return false;
        }

        return abs((float) $movement->org_amount - (float) $auroraTransaction->amount) > 0.005;
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $organisation       = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);

        $movements = DB::table('org_stock_movements')
            ->where('organisation_id', $organisation->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->where('date', '>=', RepairOrgStockMovementCostFromStockDeliveryItems::CORRUPTION_WINDOW_START)
            ->where(function ($query) {
                $query->whereNull('cost_status')
                    ->orWhere('cost_status', OrgStockMovementCostStatusEnum::PROVISIONAL->value);
            })
            ->where('quantity', '>', 0)
            ->whereNotNull('source_id')
            ->get(['id', 'org_stock_id', 'source_id', 'date', 'quantity', 'org_amount', 'cost_per_sku']);

        $auroraKeys = $movements->map(fn ($movement) => (int) explode(':', $movement->source_id)[1])->all();
        $auroraTransactions = collect();
        foreach (array_chunk($auroraKeys, 5000) as $chunk) {
            $auroraTransactions = $auroraTransactions->concat(
                DB::connection('aurora')
                    ->table('Inventory Transaction Fact')
                    ->whereIn('Inventory Transaction Key', $chunk)
                    ->get(['Inventory Transaction Key as key', 'Inventory Transaction Quantity as quantity', 'Inventory Transaction Amount as amount'])
            );
        }
        $auroraTransactions = $auroraTransactions->keyBy('key');

        $toRepair = $movements->filter(
            fn ($movement) => static::shouldRepair($movement, $auroraTransactions->get((int) explode(':', $movement->source_id)[1]))
        );

        $command->table(
            ['movement', 'org_stock', 'date', 'qty', 'aiku amount', 'aurora amount'],
            $toRepair->map(fn ($movement) => [
                $movement->id,
                $movement->org_stock_id,
                substr($movement->date, 0, 10),
                $movement->quantity,
                $movement->org_amount,
                $auroraTransactions->get((int) explode(':', $movement->source_id)[1])->amount,
            ])->all()
        );
        $command->info(sprintf(
            '%s: %d of %d post-window purchase movements diverge from Aurora (aiku sum %s -> aurora sum %s)',
            $organisation->slug,
            count($toRepair),
            count($movements),
            number_format($toRepair->sum('org_amount'), 2),
            number_format($toRepair->sum(fn ($movement) => (float) $auroraTransactions->get((int) explode(':', $movement->source_id)[1])->amount), 2)
        ));

        if ($command->option('dry-run')) {
            $command->info('Dry run: no changes written');

            return 0;
        }

        if ($toRepair->isEmpty()) {
            return 0;
        }

        DB::statement('create table if not exists org_stock_movements_pre_costfix (like org_stock_movements)');
        DB::statement(
            'insert into org_stock_movements_pre_costfix
             select m.* from org_stock_movements m
             where m.id in ('.$toRepair->pluck('id')->implode(',').')
             and not exists (select 1 from org_stock_movements_pre_costfix s where s.id = m.id)'
        );

        foreach ($toRepair as $movement) {
            $auroraAmount = (float) $auroraTransactions->get((int) explode(':', $movement->source_id)[1])->amount;
            DB::table('org_stock_movements')->where('id', $movement->id)->update([
                'org_amount'   => $auroraAmount,
                'cost_per_sku' => $auroraAmount > 0 ? round($auroraAmount / $movement->quantity, 6) : null,
                'cost_status'  => OrgStockMovementCostStatusEnum::PROVISIONAL->value,
            ]);
        }

        foreach ($toRepair->pluck('org_stock_id')->unique() as $orgStockId) {
            RecalculateOrgStockHistoriesPostCostFix::dispatch($orgStockId, fullWalk: true);
        }

        $command->info('Repaired '.count($toRepair).' movements, dispatched '.$toRepair->pluck('org_stock_id')->unique()->count().' history recalculation jobs');
        $command->info('Run org_stock_movement:recalculate_histories_post_costfix_rollup '.$organisation->slug.' once the queue drains, then hydrate:org_stocks_value_in_locations');

        return 0;
    }
}
