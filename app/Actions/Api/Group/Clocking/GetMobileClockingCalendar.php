<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileClockingCalendar
{
    use AsController;

    private const array LEAVE_FIELDS = [
        'id',
        'type',
        'type_label',
        'type_color_hex',
        'start_date',
        'end_date',
        'duration_days',
        'is_half_day',
        'leave_type_value',
        'session',
        'status',
        'status_label',
    ];

    public function handle(User $user, ?string $month): array
    {
        $employee   = GetUserCurrentEmployee::run($user);
        $timezone   = $employee?->organisation->timezone?->name ?? config('app.timezone');
        $monthStart = $month ? Carbon::parse($month.'-01') : now()->timezone($timezone)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $calendar   = IndexClockingEmployees::make()->getCalendarDataFor($employee, $monthStart->year, $monthStart->month);

        return [
            'month'          => $monthStart->format('Y-m'),
            'holidays'       => collect($calendar['holidays'])
                ->filter(fn (array $holiday) => str_starts_with($holiday['date'], $monthStart->format('Y-m')))
                ->sortBy('date')
                ->values()
                ->all(),
            'holiday_ranges' => collect($calendar['holidayRanges'])
                ->filter(fn (array $range) => $range['from'] <= $monthEnd->toDateString() && $range['to'] >= $monthStart->toDateString())
                ->sortBy('from')
                ->values()
                ->all(),
            'holiday_year'   => $calendar['holidayYearPeriod'],
            'leaves'         => $employee ? $this->leaves($employee, $monthStart, $monthEnd) : [],
        ];
    }

    private function leaves(Employee $employee, Carbon $monthStart, Carbon $monthEnd): array
    {
        return Leave::query()
            ->with(['leaveType', 'media'])
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatusEnum::PENDING->value, LeaveStatusEnum::APPROVED->value])
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn (Leave $leave) => Arr::only(LeaveResource::make($leave)->resolve(), self::LEAVE_FIELDS))
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->handle($user, $request->validated()['month'] ?? null)]);
    }
}
