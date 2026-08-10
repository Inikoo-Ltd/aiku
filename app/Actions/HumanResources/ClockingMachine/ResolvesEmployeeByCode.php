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
     * machine's organisation. Employees are shown the code without its stored organisation
     * prefix (e.g. "ABC123" for the stored "5:ABC123"), but the prefix is stripped back off
     * anyway if it is typed or scanned - a generated code always starts with a letter, so a
     * leading run matching the organisation id, with or without its colon, is unambiguous.
     */
    private function resolveEmployeeByCode(ClockingMachine $clockingMachine, string $enteredCode, string $invalidMessage): Employee
    {
        $organisationPrefix = (string) $clockingMachine->organisation_id;

        foreach ([$organisationPrefix.':', $organisationPrefix] as $prefix) {
            if (str_starts_with($enteredCode, $prefix)) {
                $enteredCode = substr($enteredCode, strlen($prefix));
                break;
            }
        }

        $employee = $clockingMachine->organisation->employees()
            ->where('pin', $organisationPrefix.':'.$enteredCode)
            ->first();

        if (!$employee || $employee->state !== EmployeeStateEnum::WORKING) {
            throw new Exception($invalidMessage);
        }

        return $employee;
    }
}
