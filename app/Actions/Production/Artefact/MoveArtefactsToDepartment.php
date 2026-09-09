<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\ArtefactDepartment\Hydrators\ArtefactDepartmentHydrateArtefacts;
use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveArtefactsToDepartment extends OrgAction
{
    public function handle(Production $production, array $modelData): int
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', $modelData['artefacts'])
            ->get();

        $touchedDepartments = $artefacts->pluck('artefact_department_id')->push($modelData['artefact_department_id'])->filter()->unique();
        $touchedFamilies    = $artefacts->pluck('artefact_family_id')->filter()->unique();

        Artefact::whereIn('id', $artefacts->pluck('id'))->update(['artefact_department_id' => $modelData['artefact_department_id']]);

        /* A family belongs to one department, so it cannot follow an artefact into another one. */
        Artefact::whereIn('id', $artefacts->pluck('id'))
            ->whereNotNull('artefact_family_id')
            ->whereNotIn('artefact_family_id', ArtefactFamily::where('artefact_department_id', $modelData['artefact_department_id'])->select('id'))
            ->update(['artefact_family_id' => null]);

        ArtefactDepartment::whereIn('id', $touchedDepartments)->each(fn (ArtefactDepartment $department) => ArtefactDepartmentHydrateArtefacts::run($department));
        ArtefactFamily::whereIn('id', $touchedFamilies)->each(fn (ArtefactFamily $family) => ArtefactFamilyHydrateArtefacts::run($family));

        return $artefacts->count();
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
            'artefacts'          => ['required', 'array', 'min:1'],
            'artefacts.*'        => ['integer'],
            'artefact_department_id' => ['present', 'nullable', Rule::exists('artefact_departments', 'id')->where('production_id', $this->production->id)],
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
