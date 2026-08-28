<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class RegenerateEmployeePin extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee $employee): Employee
    {
        SetEmployeePin::make()->action($employee, false, false);

        return $employee->fresh();
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee);
    }

    public function jsonResponse(Employee $employee): JsonResponse
    {
        return response()->json([
            'pin' => preg_replace('/^\d+:/', '', (string) $employee->pin),
        ]);
    }
}
