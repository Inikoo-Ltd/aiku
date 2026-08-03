<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-11
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

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
            'employee_contract_id' => ['sometimes', 'integer', 'exists:employee_contracts,id'],
        ];
    }

    public function handle(Employee $employee, array $data): EmployeeLeaveBalance
    {
        if (!empty($data['employee_contract_id'])) {
            $balance = EmployeeLeaveBalance::where('employee_contract_id', $data['employee_contract_id'])->first();
        } else {
            $balance = EmployeeLeaveBalance::where('employee_id', $employee->id)
                ->whereNull('employee_contract_id')
                ->first();
        }

        if (!$balance) {
            $balance = EmployeeLeaveBalance::create([
                'employee_id'          => $employee->id,
                'employee_contract_id' => $data['employee_contract_id'] ?? null,
                'annual_used'          => 0,
                'medical_used'         => 0,
                'unpaid_used'          => 0,
            ]);
        }

        return $balance;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        $balance = $this->handle($employee, $this->validatedData);

        return new JsonResponse([
            'employee_id'          => $employee->id,
            'employee_contract_id' => $balance->employee_contract_id,
            'annual_days'          => $balance->contract?->annual_leave_days,
            'annual_used'          => $balance->annual_used,
            'annual_remaining'     => $balance->annual_remaining,
        ]);
    }
}
