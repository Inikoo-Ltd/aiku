<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactComplianceTypeEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactComplianceItem extends OrgAction
{
    public function handle(Artefact $artefact, array $modelData): ArtefactComplianceItem
    {
        $modelData['group_id']        = $artefact->group_id;
        $modelData['organisation_id'] = $artefact->organisation_id;

        return $artefact->complianceItems()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::enum(ArtefactComplianceTypeEnum::class)],
            'reference'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_required' => ['sometimes', 'boolean'],
            'valid_from'  => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Artefact $artefact, array $modelData): ArtefactComplianceItem
    {
        $this->asAction = true;
        $this->initialisation($artefact->organisation, $modelData);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): ArtefactComplianceItem
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
