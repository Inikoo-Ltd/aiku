<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\OrgStockMovementBatch;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records which batches a movement put on or took off its location.
 * Must run inside the transaction holding the location_org_stocks row lock.
 *
 * Stock coming in is recorded against the batches given; any rest has no batch recorded.
 * Stock going out takes the batches given first (trusted: the person had them in hand, so
 * stock with no batch recorded is identified as that batch when the books are short of it),
 * then stock with no batch recorded, as it predates batch tracking, then the earliest best-before.
 */
class AllocateOrgStockMovementBatches
{
    use AsAction;

    private const float EPSILON = 0.000001;

    /**
     * @param  array<int, array{batch_code_id: int, quantity: float|string}>  $batches
     */
    public function handle(OrgStockMovement $orgStockMovement, float $locationQuantityBefore, array $batches = []): void
    {
        OrgStockMovementBatch::where('org_stock_movement_id', $orgStockMovement->id)->delete();

        $quantity = (float) $orgStockMovement->quantity;
        if (abs($quantity) < self::EPSILON) {
            return;
        }

        $rows = $quantity > 0
            ? $this->stockIn($quantity, $batches)
            : $this->stockOut($orgStockMovement, -$quantity, $locationQuantityBefore, $batches);

        $this->insertRows($orgStockMovement, $rows);
    }

    /**
     * Sets a movement's batches outright, for when a movement is cut into several without the
     * stock on the shelf changing (a pick split one line per batch).
     *
     * @param  array<int, array{batch_code_id: int, quantity: float}>  $batches  quantities as positive amounts
     */
    public function replace(OrgStockMovement $orgStockMovement, array $batches): void
    {
        OrgStockMovementBatch::where('org_stock_movement_id', $orgStockMovement->id)->delete();

        $sign = (float) $orgStockMovement->quantity < 0 ? -1 : 1;
        $this->insertRows($orgStockMovement, array_map(fn (array $batch) => [(int) $batch['batch_code_id'], $sign * (float) $batch['quantity']], $batches));
    }

    /**
     * @param  array<int, array{0: int, 1: float}>  $rows
     */
    private function insertRows(OrgStockMovement $orgStockMovement, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        OrgStockMovementBatch::insert(array_map(fn (array $row) => [
            'group_id'              => $orgStockMovement->group_id,
            'organisation_id'       => $orgStockMovement->organisation_id,
            'org_stock_movement_id' => $orgStockMovement->id,
            'org_stock_id'          => $orgStockMovement->org_stock_id,
            'location_id'           => $orgStockMovement->location_id,
            'batch_code_id'         => $row[0],
            'quantity'              => round($row[1], 6),
            'created_at'            => $now,
            'updated_at'            => $now,
        ], $rows));
    }

    /**
     * @return array<int, array{0: int, 1: float}>
     */
    private function stockIn(float $quantity, array $batches): array
    {
        $rows = [];
        foreach ($batches as $batch) {
            $take = min((float) $batch['quantity'], $quantity);
            if ($take < self::EPSILON) {
                continue;
            }
            $rows[]   = [(int) $batch['batch_code_id'], $take];
            $quantity -= $take;
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: int, 1: float}>
     */
    private function stockOut(OrgStockMovement $orgStockMovement, float $need, float $locationQuantityBefore, array $batches): array
    {
        $balances = $this->balances($orgStockMovement);

        $withoutBatch = $locationQuantityBefore - array_sum(array_column($balances, 'quantity'));

        $rows = [];
        foreach ($batches as $batch) {
            $batchCodeId = (int) $batch['batch_code_id'];
            $take        = min((float) $batch['quantity'], $need);
            if ($take < self::EPSILON) {
                continue;
            }

            $onBooks    = max(0, $balances[$batchCodeId]['quantity'] ?? 0);
            $identified = min(max(0, $take - $onBooks), max(0, $withoutBatch));
            if ($identified >= self::EPSILON) {
                $rows[]       = [$batchCodeId, $identified];
                $withoutBatch -= $identified;
            }

            $rows[] = [$batchCodeId, -$take];
            if (isset($balances[$batchCodeId])) {
                $balances[$batchCodeId]['quantity'] += $identified - $take;
            }
            $need -= $take;
        }

        $need -= min($need, max(0, $withoutBatch));

        foreach ($balances as $batchCodeId => $balance) {
            if ($need < self::EPSILON) {
                break;
            }
            if ($balance['quantity'] < self::EPSILON) {
                continue;
            }
            $take   = min($balance['quantity'], $need);
            $rows[] = [$batchCodeId, -$take];
            $need   -= $take;
        }

        return $rows;
    }

    /**
     * Batches on the location, earliest best-before first, those without one last.
     *
     * @return array<int, array{quantity: float}>
     */
    private function balances(OrgStockMovement $orgStockMovement): array
    {
        return DB::table('org_stock_movement_batches')
            ->join('batch_codes', 'batch_codes.id', '=', 'org_stock_movement_batches.batch_code_id')
            ->where('org_stock_movement_batches.location_id', $orgStockMovement->location_id)
            ->where('org_stock_movement_batches.org_stock_id', $orgStockMovement->org_stock_id)
            ->groupBy('batch_codes.id', 'batch_codes.expiry_date')
            ->orderByRaw('batch_codes.expiry_date asc nulls last, batch_codes.id')
            ->selectRaw('batch_codes.id, sum(org_stock_movement_batches.quantity) as quantity')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => ['quantity' => (float) $row->quantity]])
            ->all();
    }

    /**
     * The batches a movement moved, as the list this action takes, to replay them elsewhere.
     *
     * @return array<int, array{batch_code_id: int, quantity: float}>
     */
    public function movedBatches(OrgStockMovement $orgStockMovement): array
    {
        $sign = (float) $orgStockMovement->quantity < 0 ? -1 : 1;

        return OrgStockMovementBatch::where('org_stock_movement_id', $orgStockMovement->id)
            ->whereRaw('quantity * ? > 0', [$sign])
            ->orderBy('id')
            ->get(['batch_code_id', 'quantity'])
            ->map(fn (OrgStockMovementBatch $row) => [
                'batch_code_id' => $row->batch_code_id,
                'quantity'      => abs((float) $row->quantity),
            ])
            ->all();
    }
}
