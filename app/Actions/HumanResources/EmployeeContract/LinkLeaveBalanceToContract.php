<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-12
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\OrgAction;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkLeaveBalanceToContract extends OrgAction
{
    use AsAction;
    public function handle(EmployeeLeaveBalance $balance, EmployeeContract $contract): EmployeeLeaveBalance
    {
        $balance->update([
            'employee_contract_id' => $contract->id,
            'period_start'         => $contract->start_date->toDateString(),
            'period_end'           => $contract->end_date?->toDateString(),
        ]);

        return $balance->refresh();
    }

    public function rules(): array
    {
        return [
            'contract_id' => ['required', 'integer', 'exists:employee_contracts,id'],
        ];
    }

    public function asController(Organisation $organisation, EmployeeLeaveBalance $balance, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        $contract = EmployeeContract::findOrFail($this->validatedData['contract_id']);

        $this->handle($balance, $contract);

        return new JsonResponse(['message' => 'Balance linked successfully.']);
    }
}
