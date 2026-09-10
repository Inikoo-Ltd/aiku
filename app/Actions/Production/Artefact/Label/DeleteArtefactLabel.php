<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactLabel;
use Lorisleiva\Actions\ActionRequest;

class DeleteArtefactLabel extends OrgAction
{
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

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
        ]);
    }

    public function action(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefactLabel->artefact->production, []);

        return $this->handle($artefactLabel);
    }

    public function asController(ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($label->artefact->production, $request);

        return $this->handle($label);
    }
}
