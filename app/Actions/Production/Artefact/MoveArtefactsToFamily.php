<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
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

class MoveArtefactsToFamily extends OrgAction
{
    public function handle(Production $production, array $modelData): int
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', $modelData['artefacts'])
            ->get();

        $family = $modelData['artefact_family_id'] ? ArtefactFamily::find($modelData['artefact_family_id']) : null;

        $touchedFamilies    = $artefacts->pluck('artefact_family_id')->push($family?->id)->filter()->unique();
        $touchedDepartments = $artefacts->pluck('artefact_department_id')->push($family?->artefact_department_id)->filter()->unique();

        /* A family lives in exactly one department, so the artefacts follow it there. */
        $update = ['artefact_family_id' => $family?->id];
        if ($family) {
            $update['artefact_department_id'] = $family->artefact_department_id;
        }

        Artefact::whereIn('id', $artefacts->pluck('id'))->update($update);

        ArtefactFamily::whereIn('id', $touchedFamilies)->each(fn (ArtefactFamily $family) => ArtefactFamilyHydrateArtefacts::run($family));
        ArtefactDepartment::whereIn('id', $touchedDepartments)->each(fn (ArtefactDepartment $department) => ArtefactDepartmentHydrateArtefacts::run($department));

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
            'artefact_family_id' => ['present', 'nullable', Rule::exists('artefact_families', 'id')->where('production_id', $this->production->id)],
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
