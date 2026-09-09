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
     * so the job is raised at the next batch that covers what was asked for.
     */
    public function handle(float $demandInSkos, ?int $packedIn, ?int $batchSize): int
    {
        $units = (int) ceil($demandInSkos * max(1, (int) $packedIn));

        if ($batchSize > 0) {
            $units = (int) (ceil($units / $batchSize) * $batchSize);
        }

        return max(1, $units);
    }

    /**
     * The smallest order, in SKOs, that whole batches fill exactly: below it the factory
     * always makes more units than the order takes and carries the remainder.
     */
    public function quantumInSkos(?int $packedIn, ?int $batchSize): int
    {
        $packedIn  = max(1, (int) $packedIn);
        $batchSize = (int) $batchSize;

        if ($batchSize < 1) {
            return 1;
        }

        $units = $batchSize / $this->greatestCommonDivisor($batchSize, $packedIn) * $packedIn;

        return (int) ($units / $packedIn);
    }

    private function greatestCommonDivisor(int $a, int $b): int
    {
        while ($b) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
