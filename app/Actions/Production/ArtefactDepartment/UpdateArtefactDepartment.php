<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Production\ArtefactDepartment;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateArtefactDepartment extends OrgAction
{
    use WithActionUpdate;

    private ArtefactDepartment $artefactDepartment;

    public function handle(ArtefactDepartment $artefactDepartment, array $modelData): ArtefactDepartment
    {
        return $this->update($artefactDepartment, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'sometimes',
                'required',
                new AlphaDashDot(),
                'max:64',
                new IUnique(
                    table: 'artefact_departments',
                    extraConditions: [
                        ['column' => 'production_id', 'value' => $this->production->id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->artefactDepartment->id],
                    ]
                ),
            ],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function action(ArtefactDepartment $artefactDepartment, array $modelData): ArtefactDepartment
    {
        $this->asAction       = true;
        $this->artefactDepartment = $artefactDepartment;
        $this->initialisationFromProduction($artefactDepartment->production, $modelData);

        return $this->handle($artefactDepartment, $this->validatedData);
    }

    public function asController(ArtefactDepartment $artefactDepartment, ActionRequest $request): ArtefactDepartment
    {
        $this->artefactDepartment = $artefactDepartment;
        $this->initialisationFromProduction($artefactDepartment->production, $request);

        return $this->handle($artefactDepartment, $this->validatedData);
    }
}
