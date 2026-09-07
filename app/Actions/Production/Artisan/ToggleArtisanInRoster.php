<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ToggleArtisanInRoster extends OrgAction
{
    public function handle(Production $production, Employee $employee, bool $hidden): Production
    {
        $hiddenIds = collect(Arr::get($production->data, 'hidden_artisan_ids', []))
            ->reject(fn (int $id) => $id === $employee->id);
        if ($hidden) {
            $hiddenIds->push($employee->id);
        }

        $production->update(['data' => array_merge($production->data ?? [], ['hidden_artisan_ids' => $hiddenIds->values()->all()])]);

        return $production;
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Production $production, Employee $employee, bool $hidden): Production
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, []);

        return $this->handle($production, $employee, $hidden);
    }

    public function hide(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $employee, true);
    }

    public function show(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $employee, false);
    }
}
