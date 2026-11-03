<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SetArtisanAgency extends OrgAction
{
    public function handle(Employee $employee, array $modelData): Employee
    {
        $employee->update(['is_agency_worker' => $modelData['is_agency_worker']]);

        return $employee;
    }

    public function rules(): array
    {
        return [
            'is_agency_worker' => ['required', 'boolean'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "human-resources.{$this->organisation->id}.edit",
        ]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(Production $production, Employee $employee, array $modelData): Employee
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($employee, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Employee
    {
        abort_if($employee->organisation_id != $production->organisation_id, 404);
        $this->initialisationFromProduction($production, $request);

        return $this->handle($employee, $this->validatedData);
    }
}
