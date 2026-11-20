<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\Goods\TradeUnit;
use App\Models\Production\Artefact;
use App\Models\Production\RawMaterial;

/**
 * HELP-4852: a blend or intermediate is formulated as the share of each ingredient in a small sample (g or ml), and
 * one unit of its artefact is 1 kg of the blend. The recipe in Production asks for raw material units, so each
 * share becomes units per kg of blend through the raw material's kg per unit. Only a sample weighed in grams
 * converts on its own: ml needs a density aiku does not have (liquids kg or litres is still undecided), and so does
 * a raw material with no weight. Those lines wait for someone to enter their units by hand, and the blend is not an
 * ingredient of the finished product until they have.
 */
class FormulationPercentages
{
    public const array SAMPLE_UNITS = ['g', 'ml'];

    public const float TOTAL_TOLERANCE = 0.05;

    /**
     * Whether a stage is formulated in percentages: a blend or intermediate, never the finished product, which is
     * made per piece with its packaging.
     */
    public static function isFor(?ProductDevelopmentFormulation $formulation): bool
    {
        return (bool) $formulation?->type->hasOwnArtefact();
    }

    /**
     * Only a run made on the stage's own artefact, whose unit is 1 kg of the blend. A stage retyped as a blend while
     * its run was being made on the SKU's artefact (HELP-4838) carries on per piece there.
     */
    public static function isForSample(ProductDevelopmentSample $sample): bool
    {
        $formulation = $sample->formulation;

        return self::isFor($formulation) && $formulation->artefact_id && $sample->artefact_id === $formulation->artefact_id;
    }

    /**
     * The kg in one unit of the raw material: 1 for kilograms, the net weight of its trade units otherwise, null
     * when it is in litres or nothing says what it weighs.
     */
    public static function kgPerUnit(RawMaterial $rawMaterial): ?float
    {
        if ($rawMaterial->unit === RawMaterialUnitEnum::KILOGRAM) {
            return 1.0;
        }
        if ($rawMaterial->unit === RawMaterialUnitEnum::LITER) {
            return null;
        }

        $tradeUnits = $rawMaterial->orgStock
            ? $rawMaterial->orgStock->tradeUnits->map(fn (TradeUnit $tradeUnit) => [$tradeUnit->net_weight, (float) $tradeUnit->pivot->quantity])
            : collect($rawMaterial->tradeUnit ? [[$rawMaterial->tradeUnit->net_weight, 1.0]] : []);

        if ($tradeUnits->isEmpty() || $tradeUnits->contains(fn (array $tradeUnit) => !$tradeUnit[0] || $tradeUnit[1] <= 0)) {
            return null;
        }

        return $tradeUnits->sum(fn (array $tradeUnit) => $tradeUnit[0] * $tradeUnit[1]) / 1000;
    }

    /**
     * Units of the raw material in 1 kg of blend, or null when it cannot be worked out without a density.
     */
    public static function unitsPerKg(float $percent, string $sampleUnit, ?float $kgPerUnit): ?float
    {
        if ($sampleUnit !== 'g' || !$kgPerUnit) {
            return null;
        }

        $unitsPerKg = round($percent / 100 / $kgPerUnit, 8);

        return $unitsPerKg > 0 ? $unitsPerKg : null;
    }

    /**
     * The artefact a blend's recipe in Production lives on now: the stage's own artefact, or a new one made after the
     * run's artefact was cancelled, else the one the run was made on.
     */
    public static function recipeArtefact(ProductDevelopmentSample $sample): ?Artefact
    {
        $stageArtefact = $sample->formulation?->artefact;

        return $stageArtefact && $stageArtefact->state !== ArtefactStateEnum::CANCELLED ? $stageArtefact : $sample->workingArtefact();
    }

    /**
     * Raw materials with how much of each goes in, as shares of their weight. A raw material with no weight has no
     * share: whoever records the run enters it.
     *
     * @param iterable<array{raw_material: RawMaterial, quantity: float}> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sharesOf(iterable $rows): array
    {
        $rows  = collect($rows)->map(fn (array $row) => [...$row, 'kg' => ($kgPerUnit = self::kgPerUnit($row['raw_material'])) ? $row['quantity'] * $kgPerUnit : null]);
        $total = $rows->sum('kg');

        return $rows->map(fn (array $row) => [
            'raw_material_id' => $row['raw_material']->id,
            'code'            => $row['raw_material']->code,
            'description'     => $row['raw_material']->description,
            'unit'            => $row['raw_material']->unit->value,
            'percent'         => $row['kg'] !== null && $total > 0 ? round($row['kg'] / $total * 100, 4) : null,
        ])->values()->all();
    }

    /**
     * @param array<string, mixed>|null $recipe a trial run's recipe snapshot
     *
     * @return array<int, array<string, mixed>> the formulation lines still waiting for their units
     */
    public static function pendingLines(?array $recipe): array
    {
        return collect($recipe['formulation']['lines'] ?? [])->filter(fn (array $line) => $line['units_per_kg'] === null)->values()->all();
    }

    public static function hasPendingUnits(ProductDevelopmentSample $sample): bool
    {
        return count(self::pendingLines($sample->recipe)) > 0;
    }
}
