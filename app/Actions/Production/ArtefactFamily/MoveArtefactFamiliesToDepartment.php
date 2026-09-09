<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Actions\Production\ArtefactDepartment\Hydrators\ArtefactDepartmentHydrateArtefacts;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveArtefactFamiliesToDepartment extends OrgAction
{
    public function handle(Production $production, array $modelData): int
    {
        $families = ArtefactFamily::where('production_id', $production->id)
            ->whereIn('id', $modelData['families'])
            ->get();

        $departmentId       = $modelData['artefact_department_id'];
        $touchedDepartments = $families->pluck('artefact_department_id')->push($departmentId)->filter()->unique();

        /* Codes are unique inside a department, so a colliding family stays where it is. */
        $moved = $families->reject(function (ArtefactFamily $family) use ($departmentId) {
            return $family->artefact_department_id == $departmentId
                || ArtefactFamily::where('artefact_department_id', $departmentId)->where('code', $family->code)->exists();
        });

        ArtefactFamily::whereIn('id', $moved->pluck('id'))->update(['artefact_department_id' => $departmentId]);
        Artefact::whereIn('artefact_family_id', $moved->pluck('id'))->update(['artefact_department_id' => $departmentId]);

        ArtefactDepartment::whereIn('id', $touchedDepartments)->each(fn (ArtefactDepartment $department) => ArtefactDepartmentHydrateArtefacts::run($department));

        return $moved->count();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function rules(): array
    {
        return [
            'families'               => ['required', 'array', 'min:1'],
            'families.*'             => ['integer'],
            'artefact_department_id' => ['required', Rule::exists('artefact_departments', 'id')->where('production_id', $this->production->id)],
        ];
    }

    public function action(Production $production, array $modelData): int
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): int
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
