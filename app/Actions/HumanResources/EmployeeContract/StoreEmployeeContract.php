<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-11-06
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class StoreEmployeeContract extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $balanceChoices
     */
    public function handle(Employee $employee, array $data, ?array $balanceChoices = null, bool $mustBeConfirmed = false): EmployeeContract
    {
        return DB::transaction(function () use ($employee, $data, $balanceChoices, $mustBeConfirmed) {
            $planner = PlanContractLeaveBalances::make();
            $plan    = $planner->forStore($employee, $data, $balanceChoices);

            if ($mustBeConfirmed) {
                $planner->ensureConfirmed($plan, (bool) ($data['confirm_balance_changes'] ?? false));
            }

            $contract = $employee->contracts()->create([
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'] ?? null,
                'annual_leave_days' => $data['annual_leave_days'] ?? 10.0,
                'notes'             => $data['notes'] ?? null,
            ]);

            $planner->persist($plan, $employee->id, $contract->id);

            return $contract;
        });
    }

    public function rules(): array
    {
        return [
            'start_date'        => ['required', 'date'],
            'end_date'          => ['sometimes', 'nullable', 'date', 'after:start_date'],
            'annual_leave_days' => ['sometimes', 'numeric', 'min:0', 'max:365'],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'link_balance_id'   => ['sometimes', 'nullable', 'integer', 'exists:employee_leave_balances,id'],
            'confirm_balance_changes' => ['sometimes', 'boolean'],
            'balance_choices'         => ['sometimes', 'nullable', 'array'],
            'balance_choices.*'       => ['string', Rule::in([PlanContractLeaveBalances::CARRY, PlanContractLeaveBalances::RECORDED])],
        ];
    }

    public function asController(Employee $employee, ActionRequest $request): EmployeeContract
    {
        $employee->load('organisation.group');
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee, $this->validatedData, $this->validatedData['balance_choices'] ?? [], true);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Contract successfully created.'),
        ]);

        return Redirect::back();
    }

    public function action(Employee $employee, array $data): EmployeeContract
    {
        $this->asAction = true;
        $this->initialisation($employee->organisation, $data);

        return $this->handle($employee, $this->validatedData);
    }

    private function contractResponse(EmployeeContract $contract): array
    {
        return [
            'id'                => $contract->id,
            'employee_id'       => $contract->employee_id,
            'start_date'        => $contract->start_date->toDateString(),
            'end_date'          => $contract->end_date?->toDateString(),
            'annual_leave_days' => $contract->annual_leave_days,
        ];
    }
}
