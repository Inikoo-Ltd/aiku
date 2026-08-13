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
use App\Models\SysAdmin\Organisation;
use Exception;

trait ResolvesEmployeeByCode
{
    /**
     * Match a kiosk-entered/scanned code against an employee's pin.
     *
     * Machines are shared across the whole group: staff visiting another site clock in on
     * whatever machine is in front of them, so the search spans every organisation of the
     * machine's group rather than just its own. Pins are stored prefixed with the owning
     * organisation ("5:ABC123") while employees are shown only the code, and a scanned QR
     * carries the prefixed form - so the code is reduced to its bare part and matched against
     * the prefixed pin of each organisation in the group.
     *
     * Bare codes are unique group-wide; the machine's own organisation still wins any tie.
     */
    private function resolveEmployeeByCode(ClockingMachine $clockingMachine, string $enteredCode, string $invalidMessage): Employee
    {
        $bareCode = trim($enteredCode);

        if (str_contains($bareCode, ':')) {
            $bareCode = substr(strrchr($bareCode, ':'), 1);
        }

        $candidatePins = Organisation::where('group_id', $clockingMachine->group_id)
            ->pluck('id')
            ->map(fn ($organisationId) => $organisationId.':'.$bareCode)
            ->all();

        $candidates = Employee::whereIn('pin', $candidatePins)
            ->whereIn('state', [EmployeeStateEnum::WORKING->value, EmployeeStateEnum::LEAVING->value])
            ->get();

        $employee = $candidates->firstWhere('organisation_id', $clockingMachine->organisation_id)
            ?? $candidates->first();

        if (!$employee) {
            throw new Exception($invalidMessage);
        }

        return $employee;
    }
}
