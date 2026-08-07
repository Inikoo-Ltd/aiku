<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\Clocking\Traits\DeterminesClockingResult;
use App\Models\HumanResources\ClockingMachine;
use App\Notifications\LateClockInNotification;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateClockingKioskPin
{
    use AsAction;
    use WithClockingKioskToken;
    use ResolvesEmployeeByCode;
    use DeterminesClockingResult;

    public function handle(ClockingMachine $clockingMachine, string $enteredPin): array
    {
        $employee = $this->resolveEmployeeByCode($clockingMachine, $enteredPin, __('Invalid PIN.'));

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

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'max:32'],
        ];
    }

    public function asController(string $kioskToken, ActionRequest $request)
    {
        $clockingMachine = $this->resolveKioskMachine($kioskToken);
        $this->assertKioskModeEnabled($clockingMachine, 'pin');

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
