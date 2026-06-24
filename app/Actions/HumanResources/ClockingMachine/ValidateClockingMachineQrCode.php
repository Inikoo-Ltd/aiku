<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Enums\HumanResources\Clocking\ClockingActionEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use App\Models\HumanResources\ClockingMachineCoordinatePolicyRule;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\WorkSchedule;
use App\Notifications\LateClockInNotification;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateClockingMachineQrCode
{
    use AsAction;

    public function handle(string $qrCodeToken, ?float $userLat = null, ?float $userLng = null, ?int $workScheduleId = null): array
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

            $employeeId = Auth::user()?->employees->first()?->id;
            $effectiveMode = $this->resolveEffectivePolicyMode($clockingMachine, $employeeId, now());

            if (($config['allow_coordinates'] ?? false) === true && $effectiveMode !== ClockingPolicyModeEnum::REMOTE->value) {
                if ($userLat === null || $userLng === null) {
                    throw new Exception(__('Location access is required to validate this QR code.'));
                }

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

            $workingHours = $this->getWorkingHours($clockingMachine);

            $clockingResult = DB::transaction(function () use ($clockingMachine, $userLat, $userLng, $workScheduleId) {
                return $this->processClocking($clockingMachine, $userLat, $userLng, $workScheduleId);
            });

            return [
                'machine' => $clockingMachine,
                'clocking' => $clockingResult['clocking'],
                'action_type' => $clockingResult['action_type'],
                'working_hours' => $workingHours,
                'effective_mode' => $effectiveMode,
            ];

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

    private function processClocking(ClockingMachine $machine, ?float $lat, ?float $lng, ?int $workScheduleId = null): array
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

        if ($lastClocking && $lastClocking->clocked_at->diffInSeconds(now()) < 5) {
            throw new Exception(__('Scan too frequent. Please wait a moment.'));
        }

        $clockedInAt = now();

        $modelData = [
            'clocked_at' => $clockedInAt,
        ];

        if ($workScheduleId) {
            $modelData['work_schedule_id'] = $workScheduleId;
        }

        $clocking = StoreClocking::run(
            generator: $employee,
            parent: $machine,
            subject: $employee,
            modelData: $modelData
        );

        $isLate = $this->calculateLate($employee, $clockedInAt, $clocking->workSchedule);
        $clocking->is_late = $isLate;
        $clocking->saveQuietly();

        if ($isLate && $clocking->workSchedule && $employee->user) {
            $employee->user->notify(new LateClockInNotification($clocking));
        }

        $timeTracker = null;
        if ($clocking->time_tracker_id) {
            $timeTracker = TimeTracker::find($clocking->time_tracker_id);
        }

        $actionType = null;
        if ($timeTracker) {
            if ($timeTracker->start_clocking_id == $clocking->id) {
                $actionType = ClockingActionEnum::CLOCK_IN;
            } elseif ($timeTracker->end_clocking_id == $clocking->id) {
                $actionType = ClockingActionEnum::CLOCK_OUT;
            }
        }

        return [
            'clocking' => $clocking,
            'action_type' => $actionType
        ];
    }

    private function calculateLate($employee, Carbon $clockedInAt, ?WorkSchedule $selectedSchedule = null): bool
    {
        if ($employee->employment_type === EmploymentTypeEnum::PART_TIME) {
            return false;
        }

        $gracePeriod = $employee->organisation->late_grace_period_minutes ?? 15;
        $schedule = $selectedSchedule ?? $employee->organisation->getDefaultWorkSchedule();

        if (!$schedule) {
            return false;
        }

        $timezone = $schedule->timezone?->name ?? $employee->organisation->timezone?->name ?? config('app.timezone');
        $todayIso = $clockedInAt->dayOfWeekIso;
        $todaySchedule = $schedule->days()->where('day_of_week', $todayIso)->first();

        if (!$todaySchedule || !$todaySchedule->is_working_day) {
            return false;
        }

        $scheduledStart = Carbon::today($timezone)->setTimeFromTimeString($todaySchedule->start_time);
        $allowedTime = $scheduledStart->copy()->addMinutes($gracePeriod);

        return $clockedInAt->gt($allowedTime);
    }

    private function getWorkingHours(ClockingMachine $machine): ?array
    {

        $schedule = WorkSchedule::where('schedulable_type', 'Organisation')
            ->where('schedulable_id', $machine->organisation_id)
            ->where('is_active', true)
            ->first();


        if (!$schedule) {
            return null;
        }
        $todayIso = Carbon::now()->dayOfWeekIso;
        $todaySchedule = $schedule->days->where('day_of_week', $todayIso)->first();

        if (!$todaySchedule || !$todaySchedule->is_working_day) {
            return null;
        }

        return [
            'start' => $todaySchedule->start_time,
            'end' => $todaySchedule->end_time,
            'name' => $schedule->name
        ];
    }

    private function validateCoordinates(array $config, float $userLat, float $userLng): void
    {
        $targetCoords = $config['coordinates'] ?? null;
        $radius = (float) ($config['radius'] ?? 100);

        if (!$targetCoords) {
            return;
        }

        $parts = array_map('trim', explode(',', (string) $targetCoords));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            throw new Exception(__('Clocking machine coordinate configuration is invalid.'));
        }

        [$targetLat, $targetLng] = $parts;

        $distance = $this->calculateDistance($userLat, $userLng, (float)$targetLat, (float)$targetLng);

        if ($distance > $radius) {
            throw new Exception(__('Device is too far from the designated clocking location.'));
        }
    }

    private function resolveEffectivePolicyMode(ClockingMachine $clockingMachine, ?int $employeeId, Carbon $now): string
    {
        $baseQuery = ClockingMachineCoordinatePolicy::query()
            ->where('organisation_id', $clockingMachine->organisation_id)
            ->where('is_active', true)
            ->where(function ($query) use ($clockingMachine) {
                $query->whereNull('clocking_machine_id')
                    ->orWhere('clocking_machine_id', $clockingMachine->id);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->with('rules');

        $policy = null;

        if ($employeeId) {
            $policy = (clone $baseQuery)
                ->where('scope_type', 'employee')
                ->where('scope_id', $employeeId)
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$policy) {
            $policy = (clone $baseQuery)
                ->where('scope_type', 'organisation')
                ->where('scope_id', $clockingMachine->organisation_id)
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$policy) {
            return ClockingPolicyModeEnum::ONSITE->value;
        }

        $policyMode = (string) $policy->mode->value;
        if ($policyMode !== ClockingPolicyModeEnum::HYBRID->value) {
            return $policyMode;
        }

        $ruleMode = $this->resolveHybridRuleMode($policy, $now);
        if ($ruleMode === null) {
            return ClockingPolicyModeEnum::ONSITE->value;
        }

        return $ruleMode;
    }

    private function resolveHybridRuleMode(ClockingMachineCoordinatePolicy $policy, Carbon $now): ?string
    {
        $timezone = $policy->organisation?->timezone?->name ?? config('app.timezone');
        $localNow = $now->copy()->setTimezone($timezone);
        $todayIso = $localNow->dayOfWeekIso;

        $rules = $policy->rules
            ->filter(function (ClockingMachineCoordinatePolicyRule $rule) use ($todayIso, $localNow) {
                if (!$rule->is_active) {
                    return false;
                }

                if ($rule->day_of_week !== null && (int) $rule->day_of_week !== $todayIso) {
                    return false;
                }

                if ($rule->start_at !== null && $rule->start_at->gt($localNow)) {
                    return false;
                }

                if ($rule->end_at !== null && $rule->end_at->lt($localNow)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(function (ClockingMachineCoordinatePolicyRule $rule) {
                return $rule->day_of_week !== null ? 1 : 0;
            })
            ->sortByDesc('id')
            ->values();

        if ($rules->isEmpty()) {
            return null;
        }

        return (string) $rules->first()->mode_override->value;
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
        $workScheduleId = $data['work_schedule_id'] ?? null;

        try {
            $result = $this->handle($token, $lat, $lng, $workScheduleId);
            $machine = $result['machine'];
            $clocking = $result['clocking'];
            $actionType = $result['action_type'];
            $workingHours = $result['working_hours'];

            return response()->json([
                'success' => true,
                'message' => __('QR Code valid'),
                'machine' => [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'workplace_id' => $machine->workplace_id
                ],
                'working_hours' => $workingHours,
                'clocking' => [
                    'clocked_at' => $clocking->clocked_at,
                    'id' => $clocking->id,
                    'type' => $actionType,
                    'is_late' => $clocking->is_late,
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
            'qr_code'          => ['required', 'string'],
            'latitude'         => ['nullable', 'numeric'],
            'longitude'        => ['nullable', 'numeric'],
            'work_schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
        ];
    }
}
