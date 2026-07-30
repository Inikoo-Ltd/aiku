<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\Clocking\Traits\DeterminesClockingResult;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Notifications\LateClockInNotification;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateClockingKioskPin
{
    use AsAction;
    use WithClockingKioskToken;
    use DeterminesClockingResult;

    public function handle(ClockingMachine $clockingMachine, string $enteredPin): array
    {
        $employee = $this->resolveEmployee($clockingMachine, $enteredPin);

        $this->guardAgainstFrequentClocking($employee);

        $clockedInAt = now();

        $clocking = StoreClocking::run(
            generator: $employee,
            parent: $clockingMachine,
            subject: $employee,
            modelData: [
                'clocked_at' => $clockedInAt,
            ]
        );

        $isLate = $this->calculateLateClocking($employee, $clockedInAt, $clocking->workSchedule);
        $clocking->is_late = $isLate;
        $clocking->saveQuietly();

        if ($isLate && $clocking->workSchedule && $employee->user) {
            $employee->user->notify(new LateClockInNotification($clocking));
        }

        return [
            'employee'    => $employee,
            'clocking'    => $clocking,
            'action_type' => $this->resolveClockingActionType($clocking),
        ];
    }

    private function resolveEmployee(ClockingMachine $clockingMachine, string $enteredPin): Employee
    {
        $organisationPrefix = (string) $clockingMachine->organisation_id;

        // The kiosk keypad has no ':' key, so employees typing their pin as displayed
        // (e.g. "5:GF🌴🚀48") naturally include the leading organisation digits.
        // A generated code always starts with a letter, so stripping a leading run
        // that matches the organisation id is unambiguous.
        if (str_starts_with($enteredPin, $organisationPrefix)) {
            $enteredPin = substr($enteredPin, strlen($organisationPrefix));
        }

        $employee = $clockingMachine->organisation->employees()
            ->where('state', '!=', EmployeeStateEnum::LEFT->value)
            ->where('pin', $organisationPrefix.':'.$enteredPin)
            ->first();

        if (!$employee) {
            throw new Exception(__('Invalid PIN.'));
        }

        if ($employee->state !== EmployeeStateEnum::WORKING) {
            throw new Exception(__('Invalid PIN.'));
        }

        return $employee;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'max:32'],
        ];
    }

    public function asController(string $kioskToken, ActionRequest $request)
    {
        $clockingMachine = $this->resolveKioskMachine($kioskToken);

        $data = $request->validated();

        try {
            $result = $this->handle($clockingMachine, $data['pin']);

            return response()->json([
                'success'  => true,
                'employee' => [
                    'alias' => $result['employee']->alias,
                ],
                'clocking' => [
                    'clocked_at' => $result['clocking']->clocked_at,
                    'type'       => $result['action_type'],
                    'is_late'    => $result['clocking']->is_late,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
