<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteEmployee extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee $employee): Employee
    {
        $employee->delete();

        return $employee;
    }

    public function asController(Employee $employee, ActionRequest $request): Employee
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.hr.employees.index', [
            'organisation' => $this->organisation->slug
        ]);
    }

}
