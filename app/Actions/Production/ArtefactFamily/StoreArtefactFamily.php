<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactFamily extends OrgAction
{
    public function handle(Production $production, array $modelData): ArtefactFamily
    {
        data_set($modelData, 'group_id', $production->group_id);
        data_set($modelData, 'organisation_id', $production->organisation_id);

        /** @var ArtefactFamily $artefactFamily */
        $artefactFamily = $production->artefactFamilies()->create($modelData);

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
            'code'        => [
                'required',
                new AlphaDashDot(),
                'max:64',
                new IUnique(
                    table: 'artefact_families',
                    extraConditions: [
                        ['column' => 'production_id', 'value' => $this->production->id],
                    ]
                ),
            ],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function action(Production $production, array $modelData): ArtefactFamily
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ArtefactFamily
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
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
