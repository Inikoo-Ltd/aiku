<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Enums\HumanResources\Clocking\ClockingActionEnum;
use Illuminate\Validation\Rules\Enum;

class ValidateClockingMachineQrCode extends OrgAction
{
    use AsAction;

    public function handle(string $qrCodeToken, ?float $userLat = null, ?float $userLng = null): ClockingMachine
    {
        try {
            $payload = json_decode(decrypt($qrCodeToken), true);
        } catch (DecryptException $e) {
            throw new Exception(__('Invalid QR Code.'));
        }

        if (!$payload || !isset($payload['mid'], $payload['ts'])) {
            throw new Exception(__('Invalid QR Code format.'));
        }

        $clockingMachine = ClockingMachine::find($payload['mid']);

        if (!$clockingMachine) {
            throw new Exception(__('Clocking machine not found.'));
        }

        $config = $clockingMachine->config['qr'] ?? [];

        // 1. Check Expiry based on timestamp in payload vs config duration
        $expiryDuration = (int) ($config['expiry_duration'] ?? 60);
        $generatedAt = Carbon::createFromTimestamp($payload['ts']);

        if ($generatedAt->addSeconds($expiryDuration)->isPast()) {
            throw new Exception(__('QR Code has expired. Please scan a new one.'));
        }

        // 2. Validate Coordinates (Optional, if device sends location)
        if (($config['allow_coordinates'] ?? false) && $userLat && $userLng) {
            $this->validateCoordinates($config, $userLat, $userLng);
        }

        return $clockingMachine;
    }

    private function validateCoordinates(array $config, float $userLat, float $userLng): void
    {
        $targetCoords = $config['coordinates'] ?? null;
        $radius = (float) ($config['radius'] ?? 100);

        if (!$targetCoords) {
            return;
        }

        [$targetLat, $targetLng] = array_map('trim', explode(',', $targetCoords));

        $distance = $this->calculateDistance($userLat, $userLng, (float)$targetLat, (float)$targetLng);

        if ($distance > $radius) {
            throw new Exception(__('Device is too far from the designated clocking location.'));
        }
    }

    /**
     * Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function asController(ActionRequest $request)
    {
        $token = $request->input('qr_code');
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        try {
            $machine = $this->handle($token, $lat, $lng);

            return response()->json([
                'success' => true,
                'message' => __('QR Code valid'),
                'machine' => [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'workplace_id' => $machine->workplace_id
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function rules(): array
    {
        return [
            'qr_code' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'type'    => ['required', new Enum(ClockingActionEnum::class)],
        ];
    }
}
