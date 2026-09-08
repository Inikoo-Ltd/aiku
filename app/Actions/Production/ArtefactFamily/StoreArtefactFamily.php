<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactFamily extends OrgAction
{
    private ?ArtefactDepartment $artefactDepartment = null;

    public function handle(ArtefactDepartment $artefactDepartment, array $modelData): ArtefactFamily
    {
        data_set($modelData, 'group_id', $artefactDepartment->group_id);
        data_set($modelData, 'organisation_id', $artefactDepartment->organisation_id);
        data_set($modelData, 'production_id', $artefactDepartment->production_id);

        /** @var ArtefactFamily $artefactFamily */
        $artefactFamily = $artefactDepartment->artefactFamilies()->create($modelData);

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
        $rules = [
            'code'                => [
                'required',
                new AlphaDashDot(),
                'max:64',
                new IUnique(
                    table: 'artefact_families',
                    extraConditions: [
                        ['column' => 'artefact_department_id', 'value' => $this->artefactDepartment?->id],
                    ]
                ),
            ],
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['sometimes', 'nullable', 'string', 'max:1024'],
            'org_stock_family_id' => ['sometimes', 'nullable', 'integer', 'exists:org_stock_families,id'],
        ];

        if (!$this->asAction) {
            $rules['artefact_department_id'] = [
                'required',
                Rule::exists('artefact_departments', 'id')->where('production_id', $this->production->id),
            ];
        }

        return $rules;
    }

    public function action(ArtefactDepartment $artefactDepartment, array $modelData): ArtefactFamily
    {
        $this->asAction           = true;
        $this->artefactDepartment = $artefactDepartment;
        $this->initialisationFromProduction($artefactDepartment->production, $modelData);

        return $this->handle($artefactDepartment, $this->validatedData);
    }

    /* The department is picked in the form, so it arrives in the payload and not in the route. */
    public function asController(Production $production, ActionRequest $request): ArtefactFamily
    {
        $this->artefactDepartment = ArtefactDepartment::where('production_id', $production->id)
            ->find($request->input('artefact_department_id'));

        $this->initialisationFromProduction($production, $request);

        return $this->handle($this->artefactDepartment, $this->validatedData);
    }

    public function htmlResponse(ArtefactFamily $artefactFamily): RedirectResponse
    {
        return Redirect::route('grp.org.productions.show.crafts.artefact_families.show', [
            $artefactFamily->organisation->slug,
            $artefactFamily->production->slug,
            $artefactFamily->slug,
        ]);
    }
}
