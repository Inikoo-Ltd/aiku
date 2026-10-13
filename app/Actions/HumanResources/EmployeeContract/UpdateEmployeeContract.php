<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-11
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

class UpdateEmployeeContract extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $balanceChoices
     */
    public function handle(EmployeeContract $contract, array $data, ?array $balanceChoices = null, bool $mustBeConfirmed = false): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $data, $balanceChoices, $mustBeConfirmed) {
            $planner = PlanContractLeaveBalances::make();
            $plan    = $planner->forUpdate($contract, $data, $balanceChoices);

            if ($mustBeConfirmed) {
                $planner->ensureConfirmed($plan, (bool) ($data['confirm_balance_changes'] ?? false));
            }

            $contract->update($planner->contractAttributes($data));
            $planner->persist($plan, $contract->employee_id, $contract->id);

            return $contract->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'start_date'        => ['sometimes', 'date'],
            'end_date'          => ['sometimes', 'nullable', 'date'],
            'annual_leave_days' => ['sometimes', 'numeric', 'min:0', 'max:365'],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'link_balance_id'   => ['sometimes', 'nullable', 'integer', 'exists:employee_leave_balances,id'],
            'confirm_balance_changes' => ['sometimes', 'boolean'],
            'balance_choices'         => ['sometimes', 'nullable', 'array'],
            'balance_choices.*'       => ['string', Rule::in([PlanContractLeaveBalances::CARRY, PlanContractLeaveBalances::RECORDED])],
        ];
    }

    public function asController(EmployeeContract $contract, ActionRequest $request): EmployeeContract
    {
        $contract->load('employee.organisation.group');
        $this->initialisation($contract->employee->organisation, $request);

        return $this->handle($contract, $this->validatedData, $this->validatedData['balance_choices'] ?? [], true);
    }

    public function action(EmployeeContract $contract, array $data): EmployeeContract
    {
        $this->asAction = true;
        $contract->load('employee.organisation.group');
        $this->initialisation($contract->employee->organisation, $data);

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
