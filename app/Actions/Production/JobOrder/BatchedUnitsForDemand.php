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
     * What the whole mixes a line needs make: a mix is made whole and all of it goes into pieces, so a
     * line asking less leaves pieces with nowhere to go. The mix that makes the fewest pieces decides.
     * Suggested is the most whole packs those mixes make, only when it is more than asked for and,
     * with a recommended batch, whole batches too, so the batch note never asks to undo it (HELP-4936).
     *
     * @param  array<int, array{kg_per_piece: float, mix_kg: float}>  $mixes
     * @return array{mix_kg: float, pieces_per_mix: int, suggested_quantity: int|null}|null
     */
    public function wholeMixes(int $quantity, ?int $packedIn, array $mixes, ?int $batchSize = null): ?array
    {
        $mixes = array_values(array_filter($mixes, fn (array $mix) => $mix['kg_per_piece'] > 0 && $mix['mix_kg'] > 0));
        if (!$mixes || $quantity < 1) {
            return null;
        }

        $packedIn  = max(1, (int) $packedIn);
        $smallest  = collect($mixes)->sortBy(fn (array $mix) => $mix['mix_kg'] / $mix['kg_per_piece'])->first();
        $suggested = collect($mixes)->map(function (array $mix) use ($quantity) {
            $mixesNeeded = (int) ceil(round($quantity * $mix['kg_per_piece'] / $mix['mix_kg'], 6));

            return (int) floor(round($mixesNeeded * $mix['mix_kg'] / $mix['kg_per_piece'], 6));
        })->min();
        $suggested = intdiv($suggested, $packedIn) * $packedIn;
        $offBatch  = $batchSize > 0 && $suggested % $batchSize;

        return [
            'mix_kg'             => $smallest['mix_kg'],
            'pieces_per_mix'     => (int) floor(round($smallest['mix_kg'] / $smallest['kg_per_piece'], 6)),
            'suggested_quantity' => $suggested > $quantity && !$offBatch ? $suggested : null,
        ];
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
