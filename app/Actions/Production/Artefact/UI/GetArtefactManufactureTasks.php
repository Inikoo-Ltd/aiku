<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Production\Artefact\SetArtefactStepTarget;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ArtefactStepTarget;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureStepTier;
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

        $timezone = $artefact->organisation->timezone?->name ?? config('app.timezone');
        $history  = ArtefactStepTarget::where('artefact_id', $artefact->id)
            ->with('user:id,contact_name')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ArtefactStepTarget $target) => ArtefactManufactureTask::key($target->manufacture_task_id, $target->label));

        $tierHistory = ManufactureStepTier::query()
            ->where(fn ($query) => $query->where('artefact_id', $artefact->id)->when($artefact->artefact_family_id, fn ($query) => $query->orWhere('artefact_family_id', $artefact->artefact_family_id)))
            ->with('user:id,contact_name')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('manufacture_task_id');

        $recipe = $artefact->manufactureTasks->map(function (ManufactureTask $task) use ($artefact, $bands, $history, $timezone, $tierHistory) {
            $stepTiers  = $tierHistory[$task->id] ?? collect();
            $ownTiers   = $stepTiers->firstWhere('artefact_id', $artefact->id)?->thresholds;
            $familyTier = $stepTiers->firstWhere('artefact_family_id', '!=', null)?->thresholds;
            $target     = $task->pivot->standard_rate ?? $task->standard_rate;
            $target     = $target === null ? null : (float) $target;
            $source     = ManufactureStepTier::isAboveTarget($ownTiers, $target) ? 'artefact' : (ManufactureStepTier::isAboveTarget($familyTier, $target) ? 'family' : null);

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
                'label'              => $task->pivot->label,
                'step_name'          => ArtefactManufactureTask::stepName($task->name, $task->pivot->label),
                'waits_for'          => $task->pivot->waits_for,
                'task_standard_rate' => $task->standard_rate,
                'position'           => $task->pivot->position,
                'units_per_artefact' => $task->pivot->units_per_artefact,
                'is_recorded_in_kg'  => (bool) $task->is_recorded_in_kg,
                'mix_batch_kg'       => $task->pivot->mix_batch_kg,
                'mix_shelf_life_days' => $task->pivot->mix_shelf_life_days,
                'blocks_later_steps' => $task->pivot->blocks_later_steps ?? true,
                'standard_rate'      => $task->pivot->standard_rate,
                'targets'            => $this->targets($task->pivot->standard_rate ?? $task->standard_rate, $bands, $source == 'artefact' ? $ownTiers : ($source == 'family' ? $familyTier : [])),
                'tiers'              => [
                    'source'   => $source,
                    'target'   => $target,
                    'artefact' => $ownTiers,
                    'family'   => $familyTier,
                    'history'  => $stepTiers->take(10)->map(fn (ManufactureStepTier $tier) => [
                        'applies_to'          => $tier->artefact_id ? 'artefact' : 'family',
                        'thresholds'          => $tier->thresholds,
                        'previous_thresholds' => $tier->previous_thresholds,
                        'set_by'              => $tier->user?->contact_name,
                        'set_at'              => $tier->created_at?->toIso8601String(),
                    ])->values()->all(),
                ],
                'target_history'     => ($history[ArtefactManufactureTask::key($task->id, $task->pivot->label)] ?? collect())->map(fn (ArtefactStepTarget $target) => [
                    'standard_rate'  => $target->standard_rate === null ? null : (float) $target->standard_rate,
                    'effective_from' => $target->effective_from?->copy()->setTimezone($timezone)->toDateString(),
                    'set_by'         => $target->user?->contact_name,
                    'set_at'         => $target->created_at?->toIso8601String(),
                    'sessions'       => $target->data['sessions'] ?? 0,
                ])->values()->all(),
                'raw_materials'      => $rawMaterials,
            ];
        })->values()->all();

        return [
            'artefact_id'         => $artefact->id,
            'artefact_name'       => $artefact->name,
            'currency_code'       => $artefact->organisation->currency->code,
            'recipe'              => $recipe,
            'mix_batch_sizes_kg'  => $artefact->production->mixBatchSizesKg(),
            'first_open_day'      => SetArtefactStepTarget::firstOpenDay($artefact),
            'family_code'         => $artefact->artefactFamily?->code,
            'can_edit_tiers'      => (bool) request()->user()?->authTo(["org-supervisor.{$artefact->organisation_id}", "accounting.{$artefact->organisation_id}.edit"]),
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
                    'name'       => 'grp.models.artefact.recipe-step.detach',
                    'parameters' => ['artefact' => $artefact->id],
                    'method'     => 'delete',
                ],
                'tiers' => [
                    'name'       => 'grp.models.artefact.manufacture-task.tiers',
                    'parameters' => ['artefact' => $artefact->id],
                    'method'     => 'post',
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
     * @param  array<string, float>  $editedThresholds
     * @return array<int, array{code: string, name: string, hourly_rate: float, units_per_hour: float, calculated_units_per_hour: float, is_edited: bool}>
     */
    private function targets(float|string|null $standardRate, Collection $bands, array $editedThresholds): array
    {
        if ($standardRate === null) {
            return [];
        }

        return $bands->map(fn (ManufacturePayBand $band) => [
            'code'           => $band->code,
            'name'           => $band->name,
            'hourly_rate'    => (float) $band->hourly_rate,
            'units_per_hour'            => round(isset($editedThresholds[$band->code]) ? (float) $editedThresholds[$band->code] : (float) $standardRate * (float) $band->target_multiplier, 1),
            'calculated_units_per_hour' => round((float) $standardRate * (float) $band->target_multiplier, 1),
            'is_edited'                 => isset($editedThresholds[$band->code]),
        ])->all();
    }
}
