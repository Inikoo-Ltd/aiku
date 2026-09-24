<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Lorisleiva\Actions\ActionRequest;

class DeleteArtefactLabel extends OrgAction
{
    use WithArtefactLabelAuthorisation;

    public function handle(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $artefactLabel->delete();

        return $artefactLabel;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditLabels($request);
    }

    public function action(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisation($artefactLabel->organisation, []);

        return $this->handle($artefactLabel);
    }

    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($label);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgStock(OrgStock $orgStock, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($label);
    }
}
