<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\Clocking\Traits\DeterminesClockingResult;
use App\Actions\Traits\WithBase64FileConverter;
use App\Models\HumanResources\ClockingMachine;
use App\Notifications\LateClockInNotification;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateClockingKioskCameraQr
{
    use AsAction;
    use WithClockingKioskToken;
    use ResolvesEmployeeByCode;
    use DeterminesClockingResult;
    use WithBase64FileConverter;

    /**
     * The QR an employee shows the kiosk camera encodes their own pin verbatim, so the
     * scanned value is matched exactly the same way a kiosk-typed pin is.
     */
    public function handle(ClockingMachine $clockingMachine, string $scannedCode, ?string $snapshot = null): array
    {
        $employee = $this->resolveEmployeeByCode($clockingMachine, $scannedCode, __('Invalid QR code.'));

        $clockedInAt = now();

        $modelData = [
            'clocked_at' => $clockedInAt,
        ];

        if ($snapshot) {
            $modelData['photo'] = $this->convertBase64ToFile($snapshot, $employee);
        }

        $clocking = StoreClocking::run(
            generator: $employee,
            parent: $clockingMachine,
            subject: $employee,
            modelData: $modelData
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
            'code'     => ['required', 'string', 'max:64'],
            'snapshot' => ['nullable', 'string'],
        ];
    }

    public function asController(string $kioskToken, ActionRequest $request)
    {
        $clockingMachine = $this->resolveKioskMachine($kioskToken);
        $this->assertKioskModeEnabled($clockingMachine, 'camera_qr');

        $data = $request->validated();

        try {
            $result = $this->handle($clockingMachine, $data['code'], $data['snapshot'] ?? null);

            return response()->json([
                'success'  => true,
                'employee' => [
                    'alias'       => $result['employee']->alias,
                    'is_visiting' => $result['employee']->organisation_id !== $clockingMachine->organisation_id,
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
