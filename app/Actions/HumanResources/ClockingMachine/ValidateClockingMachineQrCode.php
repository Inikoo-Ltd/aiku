<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Clocking;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Enums\HumanResources\Clocking\ClockingActionEnum;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\DB;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\TimeTracker\AddClockingToTimeTracker;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;

class ValidateClockingMachineQrCode
{
    use AsAction;

    public function handle(string $qrCodeToken, ?float $userLat = null, ?float $userLng = null, ?string $type = null): ClockingMachine
    {
        $clockingMachine = null;

        try {
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

            $expiryDuration = (int) ($config['expiry_duration'] ?? 60);
            $generatedAt = Carbon::createFromTimestamp($payload['ts']);

            if ($generatedAt->addSeconds($expiryDuration)->isPast()) {
                throw new Exception(__('QR Code has expired. Please scan a new one.'));
            }

            if (($config['allow_coordinates'] ?? false) && $userLat && $userLng) {
                $this->validateCoordinates($config, $userLat, $userLng);
            }

            StoreQrScanLog::make()->handle(
                $clockingMachine,
                'success',
                null,
                $qrCodeToken,
                $userLat,
                $userLng
            );

            DB::transaction(function () use ($clockingMachine, $userLat, $userLng) {
                $this->processClocking($clockingMachine, $userLat, $userLng);
            });

            return $clockingMachine;
        } catch (Exception $e) {
            StoreQrScanLog::make()->handle(
                $clockingMachine,
                'failed',
                $e->getMessage(),
                $qrCodeToken,
                $userLat,
                $userLng
            );

            throw $e;
        }
    }

    private function processClocking(ClockingMachine $machine, ?float $lat, ?float $lng): void
    {
        $user = Auth::user();
        $employee = $user?->employees->first();

        if (!$employee) {
            throw new Exception(__('User is not associated with an employee record.'));
        }

        $lastClocking = Clocking::where('subject_type', $employee->getMorphClass())
            ->where('subject_id', $employee->id)
            ->latest('clocked_at')
            ->first();

        if ($lastClocking && $lastClocking->clocked_at->diffInSeconds(now()) < 60) {
            throw new Exception(__('Scan too frequent. Please wait a moment.'));
        }

        $today = Carbon::now()->format('Y-m-d');
        $timesheet = $employee->timesheets()->where('date', $today)->first();

        if (!$timesheet) {
            $timesheet = StoreTimesheet::make()->handle($employee, ['date' => $today]);
        }

        $clocking = new Clocking();
        $clocking->group_id = $employee->group_id;
        $clocking->organisation_id = $employee->organisation_id;
        $clocking->workplace_id = $machine->workplace_id;
        $clocking->timesheet_id = $timesheet->id;
        $clocking->clocking_machine_id = $machine->id;
        $clocking->subject_type = $employee->getMorphClass();
        $clocking->subject_id = $employee->id;

        $clocking->type = ClockingTypeEnum::CLOCKING_MACHINE;
        $clocking->clocked_at = now();

        $clocking->save();

        AddClockingToTimeTracker::make()->handle($timesheet, $clocking);
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
        $data = $request->validated();

        $token = $data['qr_code'];
        $lat   = $data['latitude'] ?? null;
        $lng   = $data['longitude'] ?? null;
        $type  = $data['type'] ?? null;

        try {
            $machine = $this->handle($token, $lat, $lng, $type);

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
            'qr_code'   => ['required', 'string'],
            'latitude'  => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'type'    => ['required', new Enum(ClockingActionEnum::class)],
        ];
    }
}
