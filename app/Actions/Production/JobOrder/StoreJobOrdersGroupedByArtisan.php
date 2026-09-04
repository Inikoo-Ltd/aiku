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
     * @param  array<int, array{artefact: Artefact, quantity: float|int, after?: callable(JobOrder): void}>  $lines
     * @return array<int, JobOrder>
     */
    public function handle(Production $production, array $lines, ?int $employeeId = null): array
    {
        $byArtisan = [];
        foreach ($lines as $line) {
            $artefact  = $line['artefact'];
            $artisanId = $employeeId ?? $artefact->artisans()->first()?->id ?? $artefact->artefactFamily?->artisans()->first()?->id ?? 0;

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
                    'quantity'    => max(1, (int) ceil((float) $line['quantity'])),
                ]);
                if (isset($line['after'])) {
                    $line['after']($jobOrder);
                }
            }

            $jobOrders[] = $jobOrder;
        }

        return $jobOrders;
    }
}
