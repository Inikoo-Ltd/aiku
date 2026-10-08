<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Inventory\OrgStockMovement\AllocateOrgStockMovementBatches;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A pick the picker gave no batch for takes its stock earliest best-before first, which can
 * span batches. The pick is then cut into one line per batch, each carrying its batch, so the
 * delivery note, the invoice and the partner's goods in see every batch that left the shelf.
 * Stock with no batch recorded stays on a line of its own with no batch.
 */
class SplitPickingByBatch
{
    use AsAction;

    private const float EPSILON = 0.000001;

    public function handle(Picking $picking): void
    {
        DB::transaction(function () use ($picking) {
            $picking = Picking::lockForUpdate()->find($picking->id);
            if (!$picking || $picking->batch_code_id || !$picking->orgStockMovement) {
                return;
            }

            $lines = $this->linesByBatch($picking);
            if ($lines === []) {
                return;
            }

            if (count($lines) === 1) {
                $picking->update(['batch_code_id' => $lines[0]['batch_code_id']]);

                return;
            }

            $pickings = [$picking];
            foreach (array_slice($lines, 1) as $line) {
                $pickings[] = SplitPicking::make()->handle($picking->refresh(), $line['quantity']);
            }

            LocationOrgStock::where('location_id', $picking->location_id)->where('org_stock_id', $picking->org_stock_id)->lockForUpdate()->value('id');

            foreach ($lines as $index => $line) {
                $linePicking = $pickings[$index]->refresh();
                $linePicking->update(['batch_code_id' => $line['batch_code_id']]);
                AllocateOrgStockMovementBatches::make()->replace(
                    $linePicking->orgStockMovement,
                    $line['batch_code_id'] ? [$line] : []
                );
            }
        });
    }

    /**
     * @return array<int, array{batch_code_id: int|null, quantity: float}>
     */
    private function linesByBatch(Picking $picking): array
    {
        $lines = [];
        foreach (AllocateOrgStockMovementBatches::make()->movedBatches($picking->orgStockMovement) as $batch) {
            $lines[$batch['batch_code_id']] = [
                'batch_code_id' => $batch['batch_code_id'],
                'quantity'      => ($lines[$batch['batch_code_id']]['quantity'] ?? 0) + $batch['quantity'],
            ];
        }

        if ($lines === []) {
            return [];
        }

        $withoutBatch = round((float) $picking->quantity - array_sum(array_column($lines, 'quantity')), 6);
        if ($withoutBatch > self::EPSILON) {
            $lines[] = ['batch_code_id' => null, 'quantity' => $withoutBatch];
        }

        return array_values($lines);
    }
}
