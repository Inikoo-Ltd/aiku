<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Models\Production\JobOrderItem;
use App\Models\Production\RecipeStepRawMaterial;
use Lorisleiva\Actions\Concerns\AsObject;

class GetJobOrderItemMissingMixes
{
    use AsObject;

    /** @return array<int, array{code: string, needed: float, on_hand: float}> */
    public function handle(JobOrderItem $jobOrderItem): array
    {
        $usages = RecipeStepRawMaterial::query()
            ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'recipe_step_raw_materials.artefact_manufacture_task_id')
            ->join('raw_materials', 'raw_materials.id', 'recipe_step_raw_materials.raw_material_id')
            ->where('artefacts_manufacture_tasks.artefact_id', $jobOrderItem->artefact_id)
            ->whereNotNull('raw_materials.artefact_id')
            ->where('raw_materials.artefact_id', '!=', $jobOrderItem->artefact_id)
            ->with('rawMaterial.artefact', 'rawMaterial.orgStock')
            ->get(['recipe_step_raw_materials.*']);

        $missing = [];
        foreach ($usages as $usage) {
            $rawMaterial = $usage->rawMaterial;
            $needed      = round((float) $jobOrderItem->quantity * (float) $usage->quantity_per_unit, 3);
            $onHand      = (float) ($rawMaterial->orgStock?->quantity_in_locations ?? $rawMaterial->quantity_on_location ?? 0);
            if ($onHand >= $needed) {
                continue;
            }
            $missing[] = ['code' => $rawMaterial->artefact->code, 'needed' => $needed, 'on_hand' => $onHand];
        }

        return $missing;
    }
}
