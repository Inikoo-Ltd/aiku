<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DetachRawMaterialFromRecipeStep extends OrgAction
{
    public function handle(ArtefactManufactureTask $recipeStep, RawMaterial $rawMaterial): void
    {
        $recipeStep->rawMaterials()->where('raw_material_id', $rawMaterial->id)->delete();
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

    public function action(ArtefactManufactureTask $recipeStep, RawMaterial $rawMaterial): void
    {
        $this->asAction = true;
        $this->initialisation($recipeStep->artefact->organisation, []);

        $this->handle($recipeStep, $rawMaterial);
    }

    public function asController(ArtefactManufactureTask $recipeStep, RawMaterial $rawMaterial, ActionRequest $request): void
    {
        $this->initialisationFromProduction($recipeStep->artefact->production, $request);

        $this->handle($recipeStep, $rawMaterial);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
