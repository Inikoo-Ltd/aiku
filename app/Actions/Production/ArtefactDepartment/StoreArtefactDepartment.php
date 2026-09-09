<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactDepartment extends OrgAction
{
    public function handle(Production $production, array $modelData): ArtefactDepartment
    {
        data_set($modelData, 'group_id', $production->group_id);
        data_set($modelData, 'organisation_id', $production->organisation_id);

        /** @var ArtefactDepartment $artefactDepartment */
        $artefactDepartment = $production->artefactDepartments()->create($modelData);

        return $artefactDepartment;
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
            'code'        => [
                'required',
                new AlphaDashDot(),
                'max:64',
                new IUnique(
                    table: 'artefact_departments',
                    extraConditions: [
                        ['column' => 'production_id', 'value' => $this->production->id],
                    ]
                ),
            ],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function action(Production $production, array $modelData): ArtefactDepartment
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ArtefactDepartment
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(ArtefactDepartment $artefactDepartment): RedirectResponse
    {
        return Redirect::route('grp.org.productions.show.crafts.artefact_departments.show', [
            $artefactDepartment->organisation->slug,
            $artefactDepartment->production->slug,
            $artefactDepartment->slug,
        ]);
    }
}
