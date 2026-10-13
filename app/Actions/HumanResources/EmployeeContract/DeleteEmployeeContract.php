<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-11-06
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\EmployeeContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteEmployeeContract extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * @param  array<string, string>|null  $balanceChoices
     */
    public function handle(EmployeeContract $contract, ?array $balanceChoices = null, bool $mustBeConfirmed = false, bool $isConfirmed = false): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $balanceChoices, $mustBeConfirmed, $isConfirmed) {
            $planner = PlanContractLeaveBalances::make();
            $plan    = $planner->forDelete($contract, $balanceChoices);

            if ($mustBeConfirmed) {
                $planner->ensureConfirmed($plan, $isConfirmed);
            }

            $planner->persist($plan, $contract->employee_id, null);
            $contract->delete();

            return $contract;
        });
    }

    public function rules(): array
    {
        return [
            'confirm_balance_changes' => ['sometimes', 'boolean'],
            'balance_choices'         => ['sometimes', 'nullable', 'array'],
            'balance_choices.*'       => ['string', Rule::in([PlanContractLeaveBalances::CARRY, PlanContractLeaveBalances::RECORDED])],
        ];
    }

    public function asController(EmployeeContract $contract, ActionRequest $request): EmployeeContract
    {
        $contract->load('employee.organisation.group');
        $this->initialisation($contract->employee->organisation, $request);

        return $this->handle(
            $contract,
            $this->validatedData['balance_choices'] ?? [],
            true,
            (bool) ($this->validatedData['confirm_balance_changes'] ?? false)
        );
    }

    public function action(EmployeeContract $contract): EmployeeContract
    {
        $this->asAction = true;

        return $this->handle($contract);
    }

    public function htmlResponse(EmployeeContract $contract, ActionRequest $request): RedirectResponse
    {
        $employee = $contract->employee;

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Contract successfully deleted.'),
        ]);

        return Redirect::route(
            'grp.org.hr.employees.show.contracts.index',
            [
                'organisation' => $employee->organisation->slug,
                'employee'     => $employee->slug,
            ]
        );
    }
}
