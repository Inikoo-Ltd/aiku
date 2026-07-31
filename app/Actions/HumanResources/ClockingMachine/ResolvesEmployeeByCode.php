<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use Exception;

trait ResolvesEmployeeByCode
{
    /**
     * Match a kiosk-entered/scanned code against an employee's pin, scoped to the kiosk
     * machine's organisation. Employees only ever see the full pin with its organisation
     * prefix (e.g. "5:ABC123"), so a code that already carries that same numeric prefix is
     * tolerated too - a generated code always starts with a letter, so stripping a leading
     * run that matches the organisation id is unambiguous.
     */
    private function resolveEmployeeByCode(ClockingMachine $clockingMachine, string $enteredCode, string $invalidMessage): Employee
    {
        $organisationPrefix = (string) $clockingMachine->organisation_id;

        if (str_starts_with($enteredCode, $organisationPrefix)) {
            $enteredCode = substr($enteredCode, strlen($organisationPrefix));
        }

        $employee = $clockingMachine->organisation->employees()
            ->where('state', '!=', EmployeeStateEnum::LEFT->value)
            ->where('pin', $organisationPrefix.':'.$enteredCode)
            ->first();

        if (!$employee || $employee->state !== EmployeeStateEnum::WORKING) {
            throw new Exception($invalidMessage);
        }

        return $employee;
    }
}
