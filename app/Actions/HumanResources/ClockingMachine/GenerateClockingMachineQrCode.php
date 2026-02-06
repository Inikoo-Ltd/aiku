<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Exception;

class GenerateClockingMachineQrCode extends OrgAction
{
    use AsAction;

    /**
     * Generate a dynamic, encrypted QR code for the clocking machine.
     * The QR code is NOT stored in the database.
     * It contains an encrypted payload with timestamp for validation upon scanning.
     */
    public function handle(ClockingMachine $clockingMachine, ?float $requestLatitude = null, ?float $requestLongitude = null): array
    {
        $config = $clockingMachine->config['qr'] ?? [];


        if (!($config['enable'] ?? false)) {
            throw new Exception(__('QR Code clocking is not enabled for this machine.'));
        }

        if (($config['allow_coordinates'] ?? false) && $requestLatitude && $requestLongitude) {
            $this->validateCoordinates($config, $requestLatitude, $requestLongitude);
        }

        $expiryDuration = (int) ($config['expiry_duration'] ?? 60);


        $payload = [
            'mid' => $clockingMachine->id,
            'ts'  => Carbon::now()->timestamp,
            'nonce' => Str::random(8)
        ];

        $qrCodeToken = encrypt(json_encode($payload));

        $expiresAt = Carbon::now()->addSeconds($expiryDuration);

        return [
            'qr_code'          => $qrCodeToken,
            'expires_at'       => $expiresAt->toIso8601String(),
            'refresh_interval' => (int) ($config['refresh_interval'] ?? 60)
        ];
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
     * Haversine formula to calculate distance in meters
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

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): array
    {
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        return $this->handle($clockingMachine, $lat, $lng);
    }

    public function rules(): array
    {
        return [
            'latitude'  => ['sometimes', 'numeric'],
            'longitude' => ['sometimes', 'numeric'],
        ];
    }
}
