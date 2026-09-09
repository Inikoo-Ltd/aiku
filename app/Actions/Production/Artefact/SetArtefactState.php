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
use App\Http\Resources\Production\ArtefactResource;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactState extends OrgAction
{
    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        $artefact->update(['state' => $modelData['state']]);

        if ($artefact->artefactFamily) {
            ArtefactFamilyHydrateArtefacts::run($artefact->artefactFamily);
        }

        if ($artefact->artefactDepartment) {
            ArtefactDepartmentHydrateArtefacts::run($artefact->artefactDepartment);
        }

        return $artefact;
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
            'state' => ['required', new Enum(ArtefactStateEnum::class)],
        ];
    }

    public function action(Artefact $artefact, ArtefactStateEnum $state): Artefact
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefact->production, ['state' => $state->value]);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function jsonResponse(Artefact $artefact): ArtefactResource
    {
        return new ArtefactResource($artefact);
    }
}
