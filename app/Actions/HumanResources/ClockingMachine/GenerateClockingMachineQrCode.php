<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 07:50:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Exception;
use Illuminate\Http\JsonResponse;

class GenerateClockingMachineQrCode extends OrgAction
{
    use AsAction;

    /**
     * Generate a dynamic, encrypted QR code for the clocking machine.
     * The QR code is NOT stored in the database.
     * It contains an encrypted payload with a timestamp for validation upon scanning.
     *
     * @throws \Exception
     */
    public function handle(ClockingMachine $clockingMachine): ClockingMachineQRCode
    {

        $config = $clockingMachine->config['qr'] ?? [];
        $expiryDuration = (int) ($config['expiry_duration'] ?? 60);


        /** @var ClockingMachineQRCode $clockingMachineQRCode */
        $clockingMachineQRCode=StoreClockingMachineQRCode::run($clockingMachine,[
            'expires_at'=>Carbon::now()->addMinutes($expiryDuration)
        ]);

        return $clockingMachineQRCode;
    }

//        if (!($config['enable'] ?? false)) {
//            throw new Exception(__('QR Code clocking is not enabled for this machine.'));
//        }
//
//
//
//        $payload = [
//            'mid'   => $clockingMachine->id,
//            'ts'    => Carbon::now()->timestamp,
//            'nonce' => Str::random(12)
//        ];
//
//        $qrCodeToken = encrypt(json_encode($payload));
//
//        $expiresAt = Carbon::now()->addSeconds($expiryDuration);
//
//        return [
//            'qr_code'          => $qrCodeToken,
//            'expires_at'       => $expiresAt->toIso8601String(),
//            'duration_seconds' => $expiryDuration
//        ];
//    }

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): JsonResponse
    {
        try {
            $clockingMachineQRCode = $this->handle($clockingMachine);
            return response()->json([
                'success' => true,
                'data'    => $clockingMachineQRCode
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
