<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RecipeStepRawMaterial;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * A redesign of a product the factory already makes: an experiment with a copy of the launched artefact's recipe,
 * batch size, shelf life and family, developed in the Development room while the original is made, ordered and
 * sold as before. It ends merged back into the original, launched as its own product, or cancelled.
 */
class BranchArtefact extends OrgAction
{
    private Artefact $artefact;

    public function handle(Artefact $artefact, ?User $user): Artefact
    {
        return DB::transaction(function () use ($artefact, $user) {
            $branch = StoreArtefact::make()->action($artefact->production, array_filter([
                'code'                   => $this->branchCode($artefact),
                'name'                   => $artefact->name ?: $artefact->code,
                'state'                  => ArtefactStateEnum::IN_PROCESS->value,
                'recommended_batch_size' => $artefact->recommended_batch_size,
                'shelf_life_days'        => $artefact->shelf_life_days,
                'artefact_department_id' => $artefact->artefact_department_id,
                'artefact_family_id'     => $artefact->artefact_family_id,
            ], fn ($value) => $value !== null));

            $branch->update([
                'branched_from_id' => $artefact->id,
                'data'             => array_merge($branch->data ?? [], [
                    'branch' => [
                        'user_id' => $user?->id,
                        'name'    => $user ? ($user->contact_name ?: $user->username) : null,
                        'at'      => now()->toIso8601String(),
                    ],
                ]),
            ]);

            self::copyRecipe($artefact, $branch);

            return $branch;
        });
    }

    /**
     * The recipe as SetArtefactsRecipe takes it, plus each step's mix batch, which it does not set.
     *
     * @return array<int, array{manufacture_task_id: int, position: int, units_per_artefact: string, standard_rate: string|null, blocks_later_steps: bool, mix_batch_kg: string|null, raw_materials: array<int, array{raw_material_id: int, quantity_per_unit: string}>}>
     */
    public static function recipeOf(Artefact $artefact): array
    {
        return $artefact->manufactureTasks()->get()
            ->map(fn (ManufactureTask $manufactureTask) => [
                'manufacture_task_id' => $manufactureTask->id,
                'position'            => $manufactureTask->pivot->position,
                'units_per_artefact'  => $manufactureTask->pivot->units_per_artefact,
                'standard_rate'       => $manufactureTask->pivot->standard_rate,
                'blocks_later_steps'  => (bool) $manufactureTask->pivot->blocks_later_steps,
                'mix_batch_kg'        => $manufactureTask->pivot->mix_batch_kg,
                'raw_materials'       => RecipeStepRawMaterial::where('artefact_manufacture_task_id', $manufactureTask->pivot->id)
                    ->orderBy('id')
                    ->get(['raw_material_id', 'quantity_per_unit'])
                    ->map(fn (RecipeStepRawMaterial $rawMaterial) => [
                        'raw_material_id'   => $rawMaterial->raw_material_id,
                        'quantity_per_unit' => $rawMaterial->quantity_per_unit,
                    ])
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Gives $to exactly the recipe of $from, through SetArtefactsRecipe so open job orders of $to follow it the way
     * they follow any recipe edit.
     */
    public static function copyRecipe(Artefact $from, Artefact $to): void
    {
        $steps = self::recipeOf($from);
        if (!$steps) {
            return;
        }

        SetArtefactsRecipe::make()->action($to->production, ['artefacts' => [$to->id], 'steps' => $steps]);

        foreach ($steps as $step) {
            ArtefactManufactureTask::where('artefact_id', $to->id)
                ->where('manufacture_task_id', $step['manufacture_task_id'])
                ->update(['mix_batch_kg' => $step['mix_batch_kg']]);
        }
    }

    private function branchCode(Artefact $artefact): string
    {
        $number = 1;
        do {
            $code = $artefact->code.'-B'.$number++;
        } while (Artefact::withTrashed()->where('organisation_id', $artefact->organisation_id)->whereRaw('lower(code) = lower(?)', [$code])->exists());

        return $code;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artefact->production_id !== $this->production->id) {
            $validator->errors()->add('branch', __('This artefact is not made in this factory'));

            return;
        }

        if (!in_array($this->artefact->state, ArtefactStateEnum::launched(), true)) {
            $validator->errors()->add('branch', __(':code is not launched: change it in the Development room as it is', ['code' => $this->artefact->code]));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_rd.{$this->production->id}.edit",
        ]);
    }

    public function action(Artefact $artefact, ?User $user = null): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, []);

        return $this->handle($artefact, $user);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $request->user());
    }

    public function htmlResponse(Artefact $branch): RedirectResponse
    {
        return Redirect::route('grp.org.productions.show.crafts.artefacts.show', [
            $branch->organisation->slug, $branch->production->slug, $branch->slug,
        ]);
    }
}
