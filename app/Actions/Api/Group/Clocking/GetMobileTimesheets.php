<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-09h-04m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileTimesheets
{
    use AsController;

    private const int MAX_DAYS = 31;

    private const array TABLE_FIELDS = [
        'start_time_local',
        'end_time_local',
        'number_time_trackers',
        'number_open_time_trackers',
        'clock_in_count',
        'clock_out_count',
        'has_pending_adjustment',
    ];

    public function handle(User $user, ?string $from, ?string $to): array
    {
        $employee = GetUserCurrentEmployee::run($user);

        if (!$employee) {
            return ['from' => $from, 'to' => $to, 'statistics' => [], 'target_seconds' => null, 'timesheets' => []];
        }

        $timezone     = $employee->organisation->timezone?->name ?? config('app.timezone');
        $today        = now()->timezone($timezone);
        $start        = $from ? Carbon::parse($from) : $today->copy()->startOfWeek();
        $end          = $to ? Carbon::parse($to) : $start->copy()->addDays(6);
        $clockingPage = IndexClockingEmployees::make();
        $summary      = new GetMobileClocking();

        $query = Timesheet::query()
            ->where('subject_type', 'Employee')
            ->where('subject_id', $employee->id)
            ->whereBetween('timesheets.date', [$start->toDateString(), $end->toDateString()]);

        $statistics = $clockingPage->getStatistics($employee, $query->clone(), $timezone, $start->toDateString(), $end->toDateString());

        $timesheets = $query
            ->with('organisation.timezone')
            ->withExists(['attendanceAdjustments as has_pending_adjustment' => fn ($adjustments) => $adjustments->where('status', AttendanceAdjustmentStatusEnum::PENDING->value)])
            ->orderBy('date')
            ->get();

        $sessionsByTimesheet = $timesheets->mapWithKeys(fn (Timesheet $timesheet) => [
            $timesheet->id => $clockingPage->getClockingSessions($timesheet, $timezone),
        ]);
        $clockings = $this->clockingsWithPlaces($sessionsByTimesheet);

        return [
            'from'       => $start->toDateString(),
            'to'         => $end->toDateString(),
            'timezone'   => $timezone,
            'statistics'     => $statistics,
            'target_seconds' => $this->targetSeconds($employee, $start, $end),
            'timesheets'     => $timesheets
                ->map(fn (Timesheet $timesheet) => array_merge(
                    ['id' => $timesheet->id],
                    $summary->timesheetSummary($timesheet, $timezone),
                    Arr::only(TimesheetsResource::make($timesheet)->resolve(), self::TABLE_FIELDS),
                    ['sessions' => $this->sessionsWithPlaces($sessionsByTimesheet->get($timesheet->id), $clockings)]
                ))
                ->values()
                ->all(),
        ];
    }

    private function targetSeconds(Employee $employee, Carbon $start, Carbon $end): ?int
    {
        $schedule = $employee->getEffectiveWorkSchedule()?->load(['days', 'timezone']);

        if (!$schedule) {
            return null;
        }

        return collect(CarbonPeriod::create($start->toDateString(), $end->toDateString()))
            ->sum(fn (CarbonInterface $date) => HydrateManufactureArtisanDays::expectedMinutes($employee->scheduledShiftOn($date->toDateString(), $schedule))) * 60;
    }

    private function clockingsWithPlaces(Collection $sessionsByTimesheet): Collection
    {
        $clockingIds = $sessionsByTimesheet
            ->flatten(1)
            ->flatMap(fn (array $session) => [$session['clock_in']['id'] ?? null, $session['clock_out']['id'] ?? null])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Clocking::with(['workplace:id,name', 'clockingMachine:id,name'])
            ->whereIn('id', $clockingIds)
            ->get()
            ->keyBy('id');
    }

    private function sessionsWithPlaces(array $sessions, Collection $clockings): array
    {
        return array_map(fn (array $session) => array_merge($session, [
            'clock_in'  => $this->clockingWithPlace($session['clock_in'], $clockings),
            'clock_out' => $this->clockingWithPlace($session['clock_out'], $clockings),
        ]), $sessions);
    }

    private function clockingWithPlace(?array $clockingData, Collection $clockings): ?array
    {
        if (!$clockingData) {
            return null;
        }

        /** @var Clocking|null $clocking */
        $clocking = $clockings->get($clockingData['id']);

        return array_merge($clockingData, [
            'workplace'        => $clocking?->workplace ? ['id' => $clocking->workplace->id, 'name' => $clocking->workplace->name] : null,
            'clocking_machine' => $clocking?->clockingMachine ? ['id' => $clocking->clockingMachine->id, 'name' => $clocking->clockingMachine->name] : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        if ($from && $to && Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= self::MAX_DAYS) {
            $validator->errors()->add('to', __('Choose at most :days days.', ['days' => self::MAX_DAYS]));
        }
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user      = $request->user();
        $validated = $request->validated();

        return response()->json(['data' => $this->handle($user, $validated['from'] ?? null, $validated['to'] ?? null)]);
    }
}
