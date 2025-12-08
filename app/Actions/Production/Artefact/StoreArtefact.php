<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 11:38:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\Hydrators\ArtefactHydrateUniversalSearch;
use App\Actions\Production\Production\Hydrators\ProductionHydrateArtefacts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateArtefacts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateArtefacts;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefact extends OrgAction
{
    public function handle(Production $production, array $modelData): Artefact
    {

        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'group_id', $production->group_id);

        /** @var Artefact $artefact */
        $artefact = $production->artefacts()->create($modelData);
        $artefact->stats()->create();
        GroupHydrateArtefacts::dispatch($artefact->group);
        OrganisationHydrateArtefacts::dispatch($artefact->organisation);
        ProductionHydrateArtefacts::dispatch($artefact->production);
        ArtefactHydrateUniversalSearch::dispatch($artefact);

        return $artefact;
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
            'code' => [
                'required',
                'max:64',
                new AlphaDashDot,
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'artefacts',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ]
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', Rule::enum(ArtefactStateEnum::class)],
            'source_id' => ['sometimes', 'nullable', 'string'],
            'created_at' => ['sometimes', 'nullable', 'date'],

        ];
    }

    public function action(Production $production, array $modelData, int $hydratorsDelay = 0): Artefact
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($production->organisation, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(Artefact $artefact): RedirectResponse
    {
        $production = $artefact->production;
        $organisation = $artefact->organisation;

        return Redirect::route('grp.org.productions.show.crafts.artefacts.index', [
            $organisation, $production,
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
