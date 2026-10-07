<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Goods\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Which information every label of this master SKO must show, in every organisation stocking it.
 * Only the compliance manager decides it, and a label missing any of it cannot be published.
 */
class UpdateStockLabelMandatoryInformation extends OrgAction
{
    public function handle(Stock $stock, array $modelData): Stock
    {
        $stock->update([
            'label_mandatory_information' => array_values(array_unique($modelData['label_mandatory_information'] ?? [])),
        ]);

        return $stock;
    }

    public function rules(): array
    {
        return [
            'label_mandatory_information'   => ['present', 'array'],
            'label_mandatory_information.*' => ArtefactLabelInformationEnum::sourceRule(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(GroupPermissionsEnum::COMPLIANCE->value);
    }

    public function action(Stock $stock, array $modelData): Stock
    {
        $this->asAction = true;
        $this->initialisationFromGroup($stock->group, $modelData);

        return $this->handle($stock, $this->validatedData);
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
