<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Production\ArtefactFamily;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateArtefactFamily extends OrgAction
{
    use WithActionUpdate;

    private ArtefactFamily $artefactFamily;

    public function handle(ArtefactFamily $artefactFamily, array $modelData): ArtefactFamily
    {
        return $this->update($artefactFamily, $modelData, ['data']);
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
                    table: 'artefact_families',
                    extraConditions: [
                        ['column' => 'production_id', 'value' => $this->production->id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->artefactFamily->id],
                    ]
                ),
            ],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'maker_employee_id' => ['sometimes', 'nullable', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function action(ArtefactFamily $artefactFamily, array $modelData): ArtefactFamily
    {
        $this->asAction       = true;
        $this->artefactFamily = $artefactFamily;
        $this->initialisationFromProduction($artefactFamily->production, $modelData);

        return $this->handle($artefactFamily, $this->validatedData);
    }

    public function asController(ArtefactFamily $artefactFamily, ActionRequest $request): ArtefactFamily
    {
        $this->artefactFamily = $artefactFamily;
        $this->initialisationFromProduction($artefactFamily->production, $request);

        return $this->handle($artefactFamily, $this->validatedData);
    }
}
