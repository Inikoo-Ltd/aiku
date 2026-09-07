<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * Until 20 Aug 2026 picking movements recorded org_amount as
 * quantity * org_stocks.value_in_locations, and value_in_locations is the total value of
 * everything in the locations, not the cost of one SKU. These rows carry no usable
 * information: they were never a per-unit cost, so nothing can be derived from them.
 *
 * This command only neutralizes the corruption: it zeroes org_amount/grp_amount on every
 * affected picking movement. org_stock_movement:backfill_picked_cost then re-derives all of
 * them on a single consistent pre-movement FIFO basis. Zero means "no cost", and the margin
 * UI already treats a zero cost as missing.
 *
 * Only picking movements are touched. Every other type sets org_amount explicitly from a
 * per-SKU cost already and was never affected.
 */
class RepairPickedOrgStockMovementCost
{
    use AsAction;

    private const PICKING_TYPES = [
        OrgStockMovementTypeEnum::PICKED->value,
        OrgStockMovementTypeEnum::CANCEL_PICKED->value,
        OrgStockMovementTypeEnum::RETURN_PICKED->value,
        OrgStockMovementTypeEnum::CANCEL_RETURN_PICKED->value,
    ];

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:repair_picked_cost {--from=2026-07-01} {--chunk=2000} {--dry-run}';
    }

    public function handle(string $from, int $chunk, bool $dryRun): array
    {
        $zeroed = 0;
        $lastId = 0;

        do {
            $ids = DB::table('org_stock_movements')
                ->whereIn('type', self::PICKING_TYPES)
                ->whereNull('source_id')
                ->where('date', '>=', $from)
                ->where('org_amount', '!=', 0)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $lastId = $ids->last();
            $zeroed += $ids->count();

            if (!$dryRun) {
                DB::table('org_stock_movements')
                    ->whereIn('id', $ids)
                    ->update([
                        'org_amount' => 0,
                        'grp_amount' => 0,
                    ]);
            }
        } while (true);

        return ['zeroed' => $zeroed];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool) $command->option('dry-run');

        $result = $this->handle(
            (string) $command->option('from'),
            (int) $command->option('chunk'),
            $dryRun
        );

        $command->info(($dryRun ? '[dry run] ' : '')."Zeroed {$result['zeroed']} picking movements with corrupt cost");

        return 0;
    }
}
