<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Actions\Production\ArtefactDepartment\Hydrators\ArtefactDepartmentHydrateArtefacts;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Production\Artefact;
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
        $previousDepartment = $artefactFamily->artefactDepartment;

        $artefactFamily = $this->update($artefactFamily, $modelData, ['data']);

        /* The artefacts follow their family: a family only ever sits in one department. */
        if ($artefactFamily->wasChanged('artefact_department_id')) {
            Artefact::where('artefact_family_id', $artefactFamily->id)
                ->update(['artefact_department_id' => $artefactFamily->artefact_department_id]);

            $artefactFamily->unsetRelation('artefactDepartment');
            foreach (array_filter([$previousDepartment, $artefactFamily->artefactDepartment]) as $department) {
                ArtefactDepartmentHydrateArtefacts::run($department);
            }
        }

        return $artefactFamily;
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
            'code'                   => [
                'sometimes',
                'required',
                new AlphaDashDot(),
                'max:64',
                new IUnique(
                    table: 'artefact_families',
                    extraConditions: [
                        ['column' => 'artefact_department_id', 'value' => $this->artefactFamily->artefact_department_id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->artefactFamily->id],
                    ]
                ),
            ],
            'name'                   => ['sometimes', 'required', 'string', 'max:255'],
            'description'            => ['sometimes', 'nullable', 'string', 'max:1024'],
            'artefact_department_id' => [
                'sometimes',
                'required',
                Rule::exists('artefact_departments', 'id')->where('production_id', $this->production->id),
            ],
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
