<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Lorisleiva\Actions\ActionRequest;

class PublishArtefactLabel extends OrgAction
{
    public function handle(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $artefactLabel->update([
            'state'        => ArtefactLabelStateEnum::PUBLISHED,
            'published_at' => now(),
        ]);

        return $artefactLabel;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function action(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefactLabel->artefact->production, []);

        return $this->handle($artefactLabel);
    }

    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($label);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}
