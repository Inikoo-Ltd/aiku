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
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteEmployeeContract extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(EmployeeContract $contract): EmployeeContract
    {
        $contract->leaveBalance()->delete();
        $contract->delete();

        return $contract;
    }

    public function asController(EmployeeContract $contract, ActionRequest $request): EmployeeContract
    {
        $contract->load('employee.organisation.group');
        $this->initialisation($contract->employee->organisation, $request);

        return $this->handle($contract);
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
