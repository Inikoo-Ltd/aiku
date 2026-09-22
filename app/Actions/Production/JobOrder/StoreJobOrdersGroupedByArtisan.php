<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreJobOrdersGroupedByArtisan
{
    use AsObject;

    /**
     * @param  array<int, array{artefact: Artefact, quantity: float|int, batch_code?: string|null, expiry_date?: string|null, after?: callable(JobOrder): void}>  $lines  quantity in SKOs, as the boards ask for it
     * @return array<int, JobOrder>
     */
    public function handle(Production $production, array $lines, ?int $employeeId = null): array
    {
        $byArtisan = [];
        foreach ($lines as $line) {
            $artefact  = $line['artefact'];
            $artisanId = $employeeId ?? $artefact->artisans()->first()?->id ?? $artefact->artefactDepartment?->artisans()->first()?->id ?? 0;

            $byArtisan[$artisanId][] = $line;
        }

        $jobOrders = [];
        foreach ($byArtisan as $artisanId => $artisanLines) {
            $jobOrder = StoreJobOrder::make()->action($production, [
                'date'        => now(),
                'employee_id' => $artisanId ?: null,
            ]);

            foreach ($artisanLines as $line) {
                StoreJobOrderItem::make()->action($jobOrder, [
                    'artefact_id' => $line['artefact']->id,
                    'quantity'    => BatchedUnitsForDemand::run(
                        (float) $line['quantity'],
                        $line['artefact']->orgStock?->packed_in,
                        $line['artefact']->recommended_batch_size
                    ),
                    'data'        => $this->getRunData($jobOrder, $line),
                ]);
                if (isset($line['after'])) {
                    $line['after']($jobOrder);
                }
            }

            $jobOrders[] = $jobOrder;
        }

        return $jobOrders;
    }

    /**
     * What this making of the artefact is called and how long it keeps. A batch code typed while the
     * run was prepared wins; otherwise the job order's own reference names the batch, which is the
     * only name guaranteed to be unique. Both travel with the item to the shelf when it is received.
     *
     * @param  array{artefact: Artefact, quantity: float|int, batch_code?: string|null, expiry_date?: string|null}  $line
     * @return array<string, string|float|null>
     */
    private function getRunData(JobOrder $jobOrder, array $line): array
    {
        return [
            'demand_skos' => (float) $line['quantity'],
            'batch_code'  => ($line['batch_code'] ?? null) ?: $jobOrder->reference.'-'.$line['artefact']->code,
            'expiry_date' => ($line['expiry_date'] ?? null) ?: null,
        ];
    }
}
