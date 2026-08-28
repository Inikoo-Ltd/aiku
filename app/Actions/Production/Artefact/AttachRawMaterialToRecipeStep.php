<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RecipeStepRawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachRawMaterialToRecipeStep extends OrgAction
{
    public function handle(ArtefactManufactureTask $recipeStep, array $modelData): RecipeStepRawMaterial
    {
        return RecipeStepRawMaterial::updateOrCreate(
            [
                'artefact_manufacture_task_id' => $recipeStep->id,
                'raw_material_id'              => $modelData['raw_material_id'],
            ],
            [
                'quantity_per_unit' => $modelData['quantity_per_unit'],
                'group_id'          => $recipeStep->artefact->group_id,
                'organisation_id'   => $recipeStep->artefact->organisation_id,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'raw_material_id'    => [
                'required',
                Rule::exists('raw_materials', 'id')->where('organisation_id', $this->recipeStep->artefact->organisation_id),
            ],
            'quantity_per_unit'  => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    private ArtefactManufactureTask $recipeStep;

    public function action(ArtefactManufactureTask $recipeStep, array $modelData): RecipeStepRawMaterial
    {
        $this->asAction   = true;
        $this->recipeStep = $recipeStep;
        $this->initialisation($recipeStep->artefact->organisation, $modelData);

        return $this->handle($recipeStep, $this->validatedData);
    }

    public function asController(ArtefactManufactureTask $recipeStep, ActionRequest $request): RecipeStepRawMaterial
    {
        $this->recipeStep = $recipeStep;
        $this->initialisationFromProduction($recipeStep->artefact->production, $request);

        return $this->handle($recipeStep, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
