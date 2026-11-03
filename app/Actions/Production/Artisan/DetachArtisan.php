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
use App\Models\Production\ManufactureTask;
use App\Models\Production\WorkArea;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DetachArtisan extends OrgAction
{
    public function handle(Artefact|WorkArea|ManufactureTask $model, Employee $employee): Artefact|WorkArea|ManufactureTask
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

    public function action(Artefact|WorkArea|ManufactureTask $model, Employee $employee): Artefact|WorkArea|ManufactureTask
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

    public function inManufactureTask(ManufactureTask $manufactureTask, Employee $employee, ActionRequest $request): ManufactureTask
    {
        $this->initialisationFromProduction($manufactureTask->production, $request);

        return $this->handle($manufactureTask, $employee);
    }
}
