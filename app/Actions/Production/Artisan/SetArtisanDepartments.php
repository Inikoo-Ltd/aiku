<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetArtisanDepartments extends OrgAction
{
    public function handle(Employee $employee, array $modelData): Employee
    {
        $departmentIds = array_map('intval', $modelData['artefact_department_ids']);

        ArtefactDepartment::where('production_id', $this->production->id)
            ->get()
            ->each(function (ArtefactDepartment $department) use ($employee, $departmentIds) {
                $isAssigned = $department->artisans()->where('employees.id', $employee->id)->exists();

                if (in_array($department->id, $departmentIds) && !$isAssigned && $employee->state == EmployeeStateEnum::WORKING) {
                    AttachArtisan::make()->handle($department, ['employee_id' => $employee->id]);
                } elseif (!in_array($department->id, $departmentIds) && $isAssigned) {
                    $department->artisans()->detach($employee->id);
                }
            });

        return $employee;
    }

    public function rules(): array
    {
        return [
            'artefact_department_ids'   => ['present', 'array'],
            'artefact_department_ids.*' => [
                'integer',
                Rule::exists('artefact_departments', 'id')
                    ->where('production_id', $this->production->id)
                    ->whereNull('deleted_at'),
            ],
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
        ]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(Production $production, Employee $employee, array $modelData): Employee
    {
        abort_if($employee->organisation_id != $production->organisation_id, 404);
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
