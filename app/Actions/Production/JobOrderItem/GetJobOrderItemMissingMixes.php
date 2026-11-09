<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Models\Production\JobOrderItem;
use App\Models\Production\RecipeStepRawMaterial;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetJobOrderItemMissingMixes
{
    use AsObject;

    /** @return array<int, array{code: string, needed: float, on_hand: float}> */
    public function handle(JobOrderItem $jobOrderItem): array
    {
        return $this->forJobOrderItems(collect([$jobOrderItem]))[$jobOrderItem->id];
    }

    /**
     * The same for many lines in one query.
     *
     * @param Collection<int, JobOrderItem> $jobOrderItems
     * @return array<int, array<int, array{code: string, needed: float, on_hand: float}>> by job order item id
     */
    public function forJobOrderItems(Collection $jobOrderItems): array
    {
        if ($jobOrderItems->isEmpty()) {
            return [];
        }

        $usagesByArtefact = RecipeStepRawMaterial::query()
            ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'recipe_step_raw_materials.artefact_manufacture_task_id')
            ->join('raw_materials', 'raw_materials.id', 'recipe_step_raw_materials.raw_material_id')
            ->whereIn('artefacts_manufacture_tasks.artefact_id', $jobOrderItems->pluck('artefact_id')->unique()->values())
            ->whereNotNull('raw_materials.artefact_id')
            ->whereColumn('raw_materials.artefact_id', '!=', 'artefacts_manufacture_tasks.artefact_id')
            ->with('rawMaterial.artefact', 'rawMaterial.orgStock')
            ->get(['recipe_step_raw_materials.*', 'artefacts_manufacture_tasks.artefact_id as usage_artefact_id'])
            ->groupBy('usage_artefact_id');

        $missingByJobOrderItem = [];
        foreach ($jobOrderItems as $jobOrderItem) {
            $missing = [];
            foreach ($usagesByArtefact->get($jobOrderItem->artefact_id, []) as $usage) {
                $rawMaterial = $usage->rawMaterial;
                if (!$rawMaterial) {
                    continue;
                }
                $needed = round((float) $jobOrderItem->quantity * (float) $usage->quantity_per_unit, 3);
                $onHand = (float) ($rawMaterial->orgStock?->quantity_in_locations ?? $rawMaterial->quantity_on_location ?? 0);
                if ($onHand >= $needed) {
                    continue;
                }
                $missing[] = ['code' => $rawMaterial->artefact?->code ?? '', 'needed' => $needed, 'on_hand' => $onHand];
            }
            $missingByJobOrderItem[$jobOrderItem->id] = $missing;
        }

        return $missingByJobOrderItem;
    }
}
