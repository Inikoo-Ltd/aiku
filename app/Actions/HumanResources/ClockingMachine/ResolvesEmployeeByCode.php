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
     * machine's group rather than just its own.
     *
     * Pins are stored prefixed with the owning organisation ("5:ABC123"). A scanned QR carries
     * that whole string, an employee reading it off their phone types the bare code, and a kiosk
     * keypad has no ':' key so the prefix arrives glued on ("5ABC123") - every form is reduced to
     * its bare code and matched against the prefixed pin of each organisation in the group. A
     * generated code always starts with a letter, so stripping a leading run of digits is
     * unambiguous.
     *
     * Bare codes are unique group-wide; the machine's own organisation still wins any tie.
     */
    private function resolveEmployeeByCode(ClockingMachine $clockingMachine, string $enteredCode, string $invalidMessage): Employee
    {
        $entered         = trim($enteredCode);
        $organisationIds = Organisation::where('group_id', $clockingMachine->group_id)->pluck('id');

        $bareCodes = [$entered];

        if (str_contains($entered, ':')) {
            $bareCodes[] = substr(strrchr($entered, ':'), 1);
        }

        foreach ($organisationIds as $organisationId) {
            foreach ([$organisationId.':', (string) $organisationId] as $prefix) {
                if (str_starts_with($entered, $prefix)) {
                    $bareCodes[] = substr($entered, strlen($prefix));
                }
            }
        }

        $candidatePins = [];

        foreach (array_filter(array_unique($bareCodes)) as $bareCode) {
            foreach ($organisationIds as $organisationId) {
                $candidatePins[] = $organisationId.':'.$bareCode;
            }
        }

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
