<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactManufactureTasks
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        $recipe = $artefact->manufactureTasks->map(fn (ManufactureTask $task) => [
            'id'                 => $task->id,
            'slug'               => $task->slug,
            'code'               => $task->code,
            'name'               => $task->name,
            'task_work_cost'     => $task->task_work_cost,
            'position'           => $task->pivot->position,
            'units_per_artefact' => $task->pivot->units_per_artefact,
        ])->values()->all();

        $taskOptions = ManufactureTask::where('production_id', $artefact->production_id)
            ->orderBy('code')
            ->get()
            ->map(fn (ManufactureTask $task) => [
                'id'   => $task->id,
                'code' => $task->code,
                'name' => $task->name,
            ])->values()->all();

        return [
            'artefact_id'  => $artefact->id,
            'recipe'       => $recipe,
            'task_options' => $taskOptions,
            'routes'       => [
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
            ],
        ];
    }
}
