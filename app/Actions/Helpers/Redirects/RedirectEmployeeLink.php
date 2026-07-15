<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectEmployeeLink extends OrgAction
{
    public function handle(Employee $employee): RedirectResponse
    {
        return Redirect::to(route('grp.org.hr.employees.show', [
            $employee->organisation->slug,
            $employee->slug,
        ]));
    }

    public function asController(Employee $employee, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee);
    }
}
