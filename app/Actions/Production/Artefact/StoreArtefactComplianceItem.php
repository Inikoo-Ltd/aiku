<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactComplianceTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactComplianceItem extends OrgAction
{
    use WithArtefactComplianceItemAuthorisation;

    public function handle(OrgStock $orgStock, array $modelData): ArtefactComplianceItem
    {
        $modelData['group_id']        = $orgStock->group_id;
        $modelData['organisation_id'] = $orgStock->organisation_id;
        $modelData['artefact_id']     = Artefact::where('org_stock_id', $orgStock->id)->value('id');

        return $orgStock->complianceItems()->create($modelData);
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

        return $this->canEditComplianceItems($request);
    }

    public function action(OrgStock $orgStock, array $modelData): ArtefactComplianceItem
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): ArtefactComplianceItem
    {
        $this->initialisationFromProduction($artefact->production, $request);
        abort_unless($artefact->orgStock, 422, __('This artefact has no SKO, so it cannot carry compliance items yet.'));

        return $this->handle($artefact->orgStock, $this->validatedData);
    }

    public function inOrgStock(OrgStock $orgStock, ActionRequest $request): ArtefactComplianceItem
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
