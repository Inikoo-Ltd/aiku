<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Which information every label of this SKO must show. Only the compliance manager decides it, and a
 * label missing any of it cannot be published.
 */
class UpdateOrgStockLabelMandatoryInformation extends OrgAction
{
    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        $orgStock->update([
            'label_mandatory_information' => array_values(array_unique($modelData['label_mandatory_information'] ?? [])),
        ]);

        return $orgStock;
    }

    public function rules(): array
    {
        return [
            'label_mandatory_information'   => ['present', 'array'],
            'label_mandatory_information.*' => ['string', Rule::enum(ArtefactLabelInformationEnum::class)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(GroupPermissionsEnum::COMPLIANCE->value);
    }

    public function action(OrgStock $orgStock, array $modelData): OrgStock
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
