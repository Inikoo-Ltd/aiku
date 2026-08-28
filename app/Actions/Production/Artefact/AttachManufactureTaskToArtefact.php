<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachManufactureTaskToArtefact extends OrgAction
{
    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        $artefact->manufactureTasks()->syncWithoutDetaching([
            $modelData['manufacture_task_id'] => [
                'position'           => $modelData['position'] ?? 1,
                'units_per_artefact' => $modelData['units_per_artefact'] ?? 1,
            ],
        ]);

        return $artefact;
    }

    public function rules(): array
    {
        return [
            'manufacture_task_id' => [
                'required',
                Rule::exists('manufacture_tasks', 'id')->where('production_id', $this->artefact->production_id),
            ],
            'position'            => ['sometimes', 'integer', 'min:1'],
            'units_per_artefact'  => ['sometimes', 'numeric', 'gt:0'],
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

    private Artefact $artefact;

    public function action(Artefact $artefact, array $modelData): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisation($artefact->organisation, $modelData);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }
}
