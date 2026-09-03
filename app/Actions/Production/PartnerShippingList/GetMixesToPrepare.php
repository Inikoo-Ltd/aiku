<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMixesToPrepare
{
    use AsObject;

    /** @return array<int, array{artefact_id: int, code: string, name: string, unit: string, needed: float, on_hand: float, in_progress: float, shortfall: float, artisan: string|null, needed_for: array<int, string>}> */
    public function handle(Production $production): array
    {
        $openStates = [JobOrderStateEnum::IN_PROCESS, JobOrderStateEnum::SUBMITTED, JobOrderStateEnum::CONFIRMED];

        $openItems = JobOrderItem::query()
            ->whereHas('jobOrder', fn ($query) => $query->where('production_id', $production->id)->whereIn('state', $openStates))
            ->with('artefact')
            ->get();

        $intermediates = RawMaterial::query()
            ->where('production_id', $production->id)
            ->whereNotNull('artefact_id')
            ->with(['artefact.artefactFamily', 'orgStock'])
            ->get()
            ->keyBy('id');

        if ($intermediates->isEmpty()) {
            return [];
        }

        $consumption = RecipeStepRawMaterial::query()
            ->whereIn('raw_material_id', $intermediates->keys())
            ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'recipe_step_raw_materials.artefact_manufacture_task_id')
            ->get(['recipe_step_raw_materials.raw_material_id', 'recipe_step_raw_materials.quantity_per_unit', 'artefacts_manufacture_tasks.artefact_id'])
            ->groupBy('artefact_id');

        $inProgressByArtefact = $openItems->groupBy('artefact_id')->map(fn ($items) => (float) $items->sum('quantity'));

        $lines = [];
        foreach ($openItems as $item) {
            foreach ($consumption->get($item->artefact_id, collect()) as $usage) {
                $rawMaterial = $intermediates->get($usage->raw_material_id);
                if ($rawMaterial->artefact_id === $item->artefact_id) {
                    continue;
                }
                $lines[$rawMaterial->id] ??= ['needed' => 0.0, 'needed_for' => []];
                $lines[$rawMaterial->id]['needed'] += (float) $item->quantity * (float) $usage->quantity_per_unit;
                $lines[$rawMaterial->id]['needed_for'][$item->artefact->code] = true;
            }
        }

        $result = [];
        foreach ($lines as $rawMaterialId => $line) {
            $rawMaterial = $intermediates->get($rawMaterialId);
            $artefact    = $rawMaterial->artefact;
            $onHand      = (float) ($rawMaterial->orgStock?->quantity_in_locations ?? $rawMaterial->quantity_on_location ?? 0);
            $inProgress  = (float) $inProgressByArtefact->get($artefact->id, 0);

            $result[] = [
                'artefact_id' => $artefact->id,
                'code'        => $artefact->code,
                'name'        => $artefact->name,
                'unit'        => $rawMaterial->unit->value,
                'needed'      => round($line['needed'], 3),
                'on_hand'     => $onHand,
                'in_progress' => $inProgress,
                'shortfall'   => round(max(0, $line['needed'] - $onHand - $inProgress), 3),
                'artisan'     => $artefact->artisans()->first()?->contact_name ?? $artefact->artefactFamily?->artisans()->first()?->contact_name,
                'needed_for'  => array_keys($line['needed_for']),
            ];
        }

        usort($result, fn ($a, $b) => [$b['shortfall'], $a['code']] <=> [$a['shortfall'], $b['code']]);

        return $result;
    }
}
