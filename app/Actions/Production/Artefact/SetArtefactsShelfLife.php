<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactsShelfLife extends OrgAction
{
    public function handle(Production $production, array $modelData): int
    {
        return Artefact::where('production_id', $production->id)
            ->whereIn('id', $modelData['artefacts'])
            ->update(['shelf_life_days' => $modelData['shelf_life_days']]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function rules(): array
    {
        return [
            'artefacts'       => ['required', 'array', 'min:1'],
            'artefacts.*'     => ['integer'],
            'shelf_life_days' => ['present', 'nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function action(Production $production, array $modelData): int
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): int
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
