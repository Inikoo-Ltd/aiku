<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactShowcase
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        return [
            'code'  => $artefact->code,
            'name'  => $artefact->name,
            'state' => $artefact->state,
            'trade_unit' => $artefact->tradeUnit ? [
                'id'   => $artefact->tradeUnit->id,
                'code' => $artefact->tradeUnit->code,
                'name' => $artefact->tradeUnit->name,
            ] : null,
            'org_stock' => $artefact->orgStock ? [
                'id'                     => $artefact->orgStock->id,
                'code'                   => $artefact->orgStock->code,
                'quantity_in_locations'  => $artefact->orgStock->quantity_in_locations,
            ] : null,
            'manufacture_tasks' => $artefact->manufactureTasks->map(fn ($task) => [
                'id'                 => $task->id,
                'code'               => $task->code,
                'name'               => $task->name,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'task_work_cost'     => $task->task_work_cost,
            ]),
        ];
    }
}
