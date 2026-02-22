<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
     * It contains an encrypted payload with timestamp for validation upon scanning.
     */
    public function handle(ClockingMachine $clockingMachine): array
    {
        $config = $clockingMachine->config['qr'] ?? [];

        if (!($config['enable'] ?? false)) {
            throw new Exception(__('QR Code clocking is not enabled for this machine.'));
        }

        $expiryDuration = (int) ($config['expiry_duration'] ?? 60);

        $payload = [
            'mid'   => $clockingMachine->id,
            'ts'    => Carbon::now()->timestamp,
            'nonce' => Str::random(12)
        ];

        $qrCodeToken = encrypt(json_encode($payload));

        $expiresAt = Carbon::now()->addSeconds($expiryDuration);
        $refreshInterval = (int) ($config['refresh_interval'] ?? 60);

        return [
            'qr_code'          => $qrCodeToken,
            'expires_at'       => $expiresAt->toIso8601String(),
            'duration_seconds' => $expiryDuration,
            'refresh_interval' => $refreshInterval
        ];
    }

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): JsonResponse
    {
        try {
            $data = $this->handle($clockingMachine);
            return response()->json([
                'success' => true,
                'data'    => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
