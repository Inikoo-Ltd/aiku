<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\Hydrators;

use App\Models\Production\RecipeStepRawMaterial;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\ArtefactFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ArtefactFamilyHydrateArtefacts
{
    use AsAction;

    public function handle(ArtefactFamily $artefactFamily): void
    {
        $counts = $artefactFamily->artefacts()
            ->groupBy('state')
            ->selectRaw('state, count(*) as number')
            ->pluck('number', 'state');

        $artefactFamily->update([
            'number_artefacts'                    => $counts->sum(),
            'state'                               => $this->state($counts->toArray()),
            'number_artefacts_without_recipe'     => $artefactFamily->artefacts()->whereNotIn('artefacts.id', RecipeStepRawMaterial::query()->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'recipe_step_raw_materials.artefact_manufacture_task_id')->select('artefacts_manufacture_tasks.artefact_id'))->count(),
            'number_artefacts_without_batch_size' => $artefactFamily->artefacts()->whereNull('recommended_batch_size')->count(),
        ]);
    }

    /**
     * A family is worth the factory's attention while any artefact in it still is, so it takes
     * the liveliest state of its artefacts. An empty family has nothing to judge and stays in process.
     *
     * @param array<string, int> $counts
     */
    public function state(array $counts): ArtefactStateEnum
    {
        foreach ([ArtefactStateEnum::ACTIVE, ArtefactStateEnum::IN_PROCESS, ArtefactStateEnum::DORMANT] as $state) {
            if (($counts[$state->value] ?? 0) > 0) {
                return $state;
            }
        }

        return ($counts[ArtefactStateEnum::DISCONTINUED->value] ?? 0) > 0
            ? ArtefactStateEnum::DISCONTINUED
            : ArtefactStateEnum::IN_PROCESS;
    }
}
