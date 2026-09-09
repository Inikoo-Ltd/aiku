<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class AttachArtisan extends OrgAction
{
    private Artefact|ArtefactDepartment $model;

    public function handle(Artefact|ArtefactDepartment $model, array $modelData): Artefact|ArtefactDepartment
    {
        $position = ((int) $model->artisans()->max('position')) + 1;
        $model->artisans()->syncWithoutDetaching([$modelData['employee_id'] => ['position' => $position]]);

        return $model;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')
                    ->where('organisation_id', $this->organisation->id)
                    ->where('state', EmployeeStateEnum::WORKING->value),
            ],
        ];
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

    public function action(Artefact|ArtefactDepartment $model, array $modelData): Artefact|ArtefactDepartment
    {
        $this->asAction = true;
        $this->model    = $model;
        $this->initialisation($model->organisation, $modelData);

        return $this->handle($model, $this->validatedData);
    }

    public function inArtefact(Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->model = $artefact;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function inArtefactDepartment(ArtefactDepartment $artefactDepartment, ActionRequest $request): ArtefactDepartment
    {
        $this->model = $artefactDepartment;
        $this->initialisationFromProduction($artefactDepartment->production, $request);

        return $this->handle($artefactDepartment, $this->validatedData);
    }
}
