<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\RawMaterial;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactManufactureTasks
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $recipe = $artefact->manufactureTasks->map(function (ManufactureTask $task) {
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
                'task_work_cost'     => $task->task_work_cost,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'raw_materials'      => $rawMaterials,
            ];
        })->values()->all();

        $taskOptions = ManufactureTask::where('production_id', $artefact->production_id)
            ->orderBy('code')
            ->get()
            ->map(fn (ManufactureTask $task) => [
                'id'   => $task->id,
                'code' => $task->code,
                'name' => $task->name,
            ])->values()->all();

        $rawMaterialOptions = RawMaterial::where('production_id', $artefact->production_id)
            ->orderBy('code')
            ->get()
            ->map(fn (RawMaterial $rawMaterial) => [
                'id'          => $rawMaterial->id,
                'code'        => $rawMaterial->code,
                'description' => $rawMaterial->description,
                'unit'        => $rawMaterial->unit,
            ])->values()->all();

        return [
            'artefact_id'         => $artefact->id,
            'recipe'              => $recipe,
            'task_options'        => $taskOptions,
            'raw_material_options' => $rawMaterialOptions,
            'routes'              => [
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
}
