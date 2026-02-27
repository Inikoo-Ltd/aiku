<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class AdjustEmployeeLeaveBalance extends OrgAction
{
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'annual_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function handle(Employee $employee, array $data): EmployeeLeaveBalance
    {
        $balance = EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'year'        => $data['year'],
            ],
            [
                'annual_days'   => 10,
                'annual_used'   => 0,
                'medical_days'  => 365,
                'medical_used'  => 0,
                'unpaid_days'  => 0,
                'unpaid_used'  => 0,
            ]
        );

        $balance->update([
            'annual_days' => $data['annual_days'],
        ]);

        return $balance;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        $balance = $this->handle($employee, $this->validatedData);

        return new JsonResponse([
            'employee_id' => $employee->id,
            'year' => $balance->year,
            'annual_days' => $balance->annual_days,
            'annual_used' => $balance->annual_used,
            'annual_remaining' => $balance->annual_remaining,
        ]);
    }
}
