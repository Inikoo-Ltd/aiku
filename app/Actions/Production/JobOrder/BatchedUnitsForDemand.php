<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use Lorisleiva\Actions\Concerns\AsObject;

class BatchedUnitsForDemand
{
    use AsObject;

    /**
     * Demand is asked for in SKOs, the artisan works in units and only in whole batches,
     * so the job is raised at the next batch that covers what was asked for. Nobody packs
     * part of an outer, so the demand is whole SKOs before it is turned into units.
     */
    public function handle(float $demandInSkos, ?int $packedIn, ?int $batchSize): int
    {
        $packedIn = max(1, (int) $packedIn);
        $units    = (int) ceil(round($demandInSkos, 6)) * $packedIn;

        if ($batchSize > 0) {
            $units = (int) (ceil($units / $batchSize) * $batchSize);
        }

        return max($packedIn, $units);
    }

    /**
     * Units typed by hand, raised to the next whole outer. Where the batch is not itself whole
     * outers the two cannot both hold: the batch is what the recipe makes, so it is left alone.
     */
    public function wholeOuters(int $units, ?int $packedIn, ?int $batchSize): int
    {
        $packedIn = max(1, (int) $packedIn);

        if ($batchSize > 0 && $batchSize % $packedIn) {
            return $units;
        }

        return (int) ceil($units / $packedIn) * $packedIn;
    }

    /**
     * The order step, in SKOs: one batch of units rounded down to whole SKOs, at least one. The
     * batch is counted in units, never packs, so a batch the SKO does not divide leaves the
     * factory a few units over rather than raising the step to the order whole batches fill.
     */
    public function quantumInSkos(?int $packedIn, ?int $batchSize): int
    {
        return max(1, intdiv(max(0, (int) $batchSize), max(1, (int) $packedIn)));
    }
}
