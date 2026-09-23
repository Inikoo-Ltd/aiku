<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactComplianceTypeEnum;
use App\Models\Production\ArtefactComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateArtefactComplianceItem extends OrgAction
{
    use WithArtefactComplianceItemAuthorisation;

    public function handle(ArtefactComplianceItem $artefactComplianceItem, array $modelData): ArtefactComplianceItem
    {
        $artefactComplianceItem->update($modelData);

        return $artefactComplianceItem;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes', Rule::enum(ArtefactComplianceTypeEnum::class)],
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

        return $this->canEditComplianceItems($request);
    }

    public function action(ArtefactComplianceItem $artefactComplianceItem, array $modelData): ArtefactComplianceItem
    {
        $this->asAction = true;
        $this->initialisation($artefactComplianceItem->organisation, $modelData);

        return $this->handle($artefactComplianceItem, $this->validatedData);
    }

    public function asController(ArtefactComplianceItem $artefactComplianceItem, ActionRequest $request): ArtefactComplianceItem
    {
        $this->initialisationFromComplianceItem($artefactComplianceItem, $request);

        return $this->handle($artefactComplianceItem, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
