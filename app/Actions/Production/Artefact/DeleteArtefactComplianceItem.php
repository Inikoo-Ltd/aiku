<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteArtefactComplianceItem extends OrgAction
{
    use WithArtefactComplianceItemAuthorisation;

    public function handle(ArtefactComplianceItem $artefactComplianceItem): void
    {
        $artefactComplianceItem->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditComplianceItems($request);
    }

    public function action(ArtefactComplianceItem $artefactComplianceItem): void
    {
        $this->asAction = true;
        $this->initialisation($artefactComplianceItem->organisation, []);

        $this->handle($artefactComplianceItem);
    }

    public function asController(ArtefactComplianceItem $artefactComplianceItem, ActionRequest $request): void
    {
        $this->initialisationFromComplianceItem($artefactComplianceItem, $request);

        $this->handle($artefactComplianceItem);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
