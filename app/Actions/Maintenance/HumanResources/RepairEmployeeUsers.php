<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Feb 2025 13:55:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\HumanResources;

use App\Actions\Traits\WithActionUpdate;
use App\Models\HumanResources\Employee;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairEmployeeUsers
{
    use WithActionUpdate;



    protected function handle(Employee $employee): Employee
    {

        $numberUsers = $employee->users()->count();
        if ($numberUsers > 1) {
            dd($employee);
        }

        $user = $employee->getUser();
        if ($user) {
            $employee->update(
                [
                    'user_id' => $user->id
                ]
            );
        }

        return $employee;
    }

    public string $commandSignature = 'employees:repair_user_id';

    public function asCommand(): void
    {
        Nightwatch::dontSample();
        $employees = Employee::all();

        foreach ($employees as $employee) {
            $this->handle($employee);
        }
    }

}
