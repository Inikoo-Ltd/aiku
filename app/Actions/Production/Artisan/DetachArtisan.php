<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DetachArtisan extends OrgAction
{
    public function handle(Artefact|ArtefactFamily $model, Employee $employee): Artefact|ArtefactFamily
    {
        $model->artisans()->detach($employee->id);

        return $model;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Artefact|ArtefactFamily $model, Employee $employee): Artefact|ArtefactFamily
    {
        $this->asAction = true;
        $this->initialisation($model->organisation, []);

        return $this->handle($model, $employee);
    }

    public function inArtefact(Artefact $artefact, Employee $employee, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $employee);
    }

    public function inArtefactFamily(ArtefactFamily $artefactFamily, Employee $employee, ActionRequest $request): ArtefactFamily
    {
        $this->initialisationFromProduction($artefactFamily->production, $request);

        return $this->handle($artefactFamily, $employee);
    }
}
