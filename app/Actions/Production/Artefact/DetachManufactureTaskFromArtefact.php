<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\ActionRequest;

class DetachManufactureTaskFromArtefact extends OrgAction
{
    public function handle(Artefact $artefact, ManufactureTask $manufactureTask): Artefact
    {
        $artefact->manufactureTasks()->detach($manufactureTask->id);

        return $artefact;
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

    public function action(Artefact $artefact, ManufactureTask $manufactureTask): Artefact
    {
        $this->asAction = true;
        $this->initialisation($artefact->organisation, []);

        return $this->handle($artefact, $manufactureTask);
    }

    public function asController(Artefact $artefact, ManufactureTask $manufactureTask, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $manufactureTask);
    }
}
