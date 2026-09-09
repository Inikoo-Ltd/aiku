<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\ArtefactDepartment\Hydrators\ArtefactDepartmentHydrateArtefacts;
use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactsState extends OrgAction
{
    public function handle(Production $production, array $modelData): int
    {
        $state = ArtefactStateEnum::from($modelData['state']);

        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', $modelData['artefacts'])
            ->where('state', '!=', $state)
            ->get();

        Artefact::whereIn('id', $artefacts->pluck('id'))
            ->update(['state' => $state]);

        /* A family and a department take their own state from the artefacts under them. */
        ArtefactFamily::whereIn('id', $artefacts->pluck('artefact_family_id')->filter()->unique())
            ->each(fn (ArtefactFamily $family) => ArtefactFamilyHydrateArtefacts::run($family));
        ArtefactDepartment::whereIn('id', $artefacts->pluck('artefact_department_id')->filter()->unique())
            ->each(fn (ArtefactDepartment $department) => ArtefactDepartmentHydrateArtefacts::run($department));

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
            'artefacts'   => ['required', 'array', 'min:1'],
            'artefacts.*' => ['integer'],
            'state'       => ['required', Rule::in([ArtefactStateEnum::ACTIVE->value, ArtefactStateEnum::DISCONTINUED->value])],
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
