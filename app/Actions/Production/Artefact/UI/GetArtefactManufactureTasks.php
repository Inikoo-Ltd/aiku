<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Models\Production\Artefact;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTask;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactManufactureTasks
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $bands = ManufacturePayBand::effectiveAt($artefact->production_id, now())
            ->whereNotNull('target_multiplier')
            ->get()
            ->unique('code')
            ->sortBy('target_multiplier')
            ->values();

        $recipe = $artefact->manufactureTasks->map(function (ManufactureTask $task) use ($bands) {
            $rawMaterials = $task->pivot->rawMaterials()->with('rawMaterial')->get()
                ->map(fn ($recipeStepRawMaterial) => [
                    'raw_material_id'    => $recipeStepRawMaterial->raw_material_id,
                    'code'               => $recipeStepRawMaterial->rawMaterial->code,
                    'description'        => $recipeStepRawMaterial->rawMaterial->description,
                    'unit'               => $recipeStepRawMaterial->rawMaterial->unit,
                    'quantity_per_unit'  => $recipeStepRawMaterial->quantity_per_unit,
                    'line_cost'          => round($recipeStepRawMaterial->quantity_per_unit * $recipeStepRawMaterial->rawMaterial->unit_cost, 4),
                ])->values()->all();

            return [
                'id'                 => $task->id,
                'step_id'            => $task->pivot->id,
                'slug'               => $task->slug,
                'code'               => $task->code,
                'name'               => $task->name,
                'task_standard_rate' => $task->standard_rate,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'standard_rate'      => $task->pivot->standard_rate,
                'targets'            => $this->targets($task->pivot->standard_rate ?? $task->standard_rate, $bands),
                'raw_materials'      => $rawMaterials,
            ];
        })->values()->all();

        return [
            'artefact_id'         => $artefact->id,
            'artefact_name'       => $artefact->name,
            'currency_code'       => $artefact->organisation->currency->code,
            'recipe'              => $recipe,
            'routes'              => [
                'task_options' => [
                    'name'       => 'grp.json.production.manufacture_tasks.index',
                    'parameters' => ['production' => $artefact->production_id],
                ],
                'raw_material_options' => [
                    'name'       => 'grp.json.production.raw_materials.index',
                    'parameters' => ['production' => $artefact->production_id],
                ],
                'attach' => [
                    'name'       => 'grp.models.artefact.manufacture-task.attach',
                    'parameters' => ['artefact' => $artefact->id],
                    'method'     => 'post',
                ],
                'detach' => [
                    'name'       => 'grp.models.artefact.manufacture-task.detach',
                    'parameters' => ['artefact' => $artefact->id],
                    'method'     => 'delete',
                ],
                'raw_material_attach' => [
                    'name'   => 'grp.models.recipe-step.raw-material.attach',
                    'method' => 'post',
                ],
                'raw_material_detach' => [
                    'name'   => 'grp.models.recipe-step.raw-material.detach',
                    'method' => 'delete',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, hourly_rate: float, units_per_hour: float}>
     */
    private function targets(float|string|null $standardRate, Collection $bands): array
    {
        if ($standardRate === null) {
            return [];
        }

        return $bands->map(fn (ManufacturePayBand $band) => [
            'code'           => $band->code,
            'name'           => $band->name,
            'hourly_rate'    => (float) $band->hourly_rate,
            'units_per_hour' => round((float) $standardRate * (float) $band->target_multiplier, 1),
        ])->all();
    }
}
