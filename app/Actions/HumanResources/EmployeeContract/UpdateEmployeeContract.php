<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-11
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\OrgAction;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateEmployeeContract extends OrgAction
{
    public function handle(EmployeeContract $contract, array $data): EmployeeContract
    {
        $contract->update(array_filter([
            'start_date'          => $data['start_date'] ?? null,
            'end_date'            => array_key_exists('end_date', $data) ? $data['end_date'] : $contract->end_date,
            'annual_leave_days'   => $data['annual_leave_days'] ?? null,
            'notes'               => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['start_date']) || array_key_exists('end_date', $data)) {
            EmployeeLeaveBalance::where('employee_contract_id', $contract->id)->update([
                'period_start' => $contract->fresh()->start_date->toDateString(),
                'period_end'   => $contract->fresh()->end_date?->toDateString(),
            ]);
        }

        if (!empty($data['link_balance_id'])) {
            $balance = EmployeeLeaveBalance::find($data['link_balance_id']);
            if ($balance && $balance->employee_id === $contract->employee_id) {
                LinkLeaveBalanceToContract::run($balance, $contract);
            }
        }

        return $contract->fresh();
    }

    public function rules(): array
    {
        return [
            'start_date'        => ['sometimes', 'date'],
            'end_date'          => ['sometimes', 'nullable', 'date'],
            'annual_leave_days' => ['sometimes', 'numeric', 'min:0', 'max:365'],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'link_balance_id'   => ['sometimes', 'nullable', 'integer', 'exists:employee_leave_balances,id'],
        ];
    }

    public function asController(EmployeeContract $contract, ActionRequest $request): EmployeeContract
    {
        $contract->load('employee.organisation.group');
        $this->initialisation($contract->employee->organisation, $request);

        return $this->handle($contract, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Contract successfully updated.'),
        ]);

        return Redirect::back();
    }
}
