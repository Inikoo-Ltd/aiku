<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\HumanResources;

use App\Actions\HumanResources\Employee\SetEmployeePin;
use App\Models\HumanResources\Employee;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEmployeePins
{
    use AsAction;

    protected function handle(Employee $employee): void
    {
        SetEmployeePin::make()->action($employee, updateQuietly: true);
    }

    public string $commandSignature = 'employees:repair_pins';

    public function asCommand(): void
    {
        Employee::orderBy('id')->chunkById(500, function ($employees) {
            foreach ($employees as $employee) {
                $this->handle($employee);
            }
        });
    }
}
