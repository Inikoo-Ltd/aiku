<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\CalendarExport;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCalendar extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;

    private const LARGE_EXPORT_THRESHOLD = 5000;

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => [
                'nullable',
                'string',
                Rule::exists('leave_types', 'code')->where(function ($query) {
                    $query->where('organisation_id', $this->organisation->id);
                }),
            ],
            'status' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer'],
            'format' => ['required', 'string', 'in:csv,xlsx'],
        ];
    }

    public function handle(array $modelData): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $filters = array_filter(
            [
                'from' => $modelData['from'] ?? null,
                'to' => $modelData['to'] ?? null,
                'type' => $modelData['type'] ?? null,
                'status' => $modelData['status'] ?? null,
                'department' => $modelData['department'] ?? null,
                'team' => $modelData['team'] ?? null,
                'employee_id' => $modelData['employee_id'] ?? null,
                'year' => $modelData['year'] ?? Carbon::now()->year,
                'month' => $modelData['month'] ?? Carbon::now()->month,
            ]
        );

        $this->applyTeamScope($filters);

        $calendarData = $this->getCalendarData($filters);
        $weeks = $this->generateCalendarWeeks($filters);
        $holidays = $this->getHolidays($filters);

        $this->logExport($filters, $modelData['format'], count($calendarData));

        $export = new CalendarExport(
            $this->organisation,
            $filters,
            $calendarData,
            $weeks,
            $holidays
        );

        return $this->export($export, 'leave-calendar', $modelData['format']);
    }

    private function getCalendarData(array $filters): array
    {
        $query = Leave::query()
            ->where('organisation_id', $this->organisation->id)
            ->with(['employee', 'leaveType']);

        $this->applyFilters($query, $filters);

        $leaves = $query->get();

        $calendarData = [];

        foreach ($leaves as $leave) {
            $employeeId = $leave->employee_id;

            if (!isset($calendarData[$employeeId])) {
                $calendarData[$employeeId] = [
                    'id' => $leave->employee->id,
                    'name' => $leave->employee->contact_name ?? $leave->employee->alias,
                    'department' => $leave->employee->department,
                    'job_title' => $leave->employee->job_title,
                    'leaves' => []
                ];
            }

            $calendarData[$employeeId]['leaves'][] = [
                'id' => $leave->id,
                'type' => $leave->type,
                'status' => $leave->status,
                'start_date' => $leave->start_date->format('Y-m-d'),
                'end_date' => $leave->end_date->format('Y-m-d'),
                'notes' => $leave->notes,
            ];
        }

        return array_values($calendarData);
    }

    private function generateCalendarWeeks(array $filters): array
    {
        $year = $filters['year'];
        $month = $filters['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        if ($startDate->dayOfWeek !== Carbon::MONDAY) {
            $startDate->subDays($startDate->dayOfWeek - Carbon::MONDAY);
        }

        if ($endDate->dayOfWeek !== Carbon::SUNDAY) {
            $endDate->addDays(Carbon::SUNDAY - $endDate->dayOfWeek);
        }

        $weeks = [];
        $currentWeek = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $currentWeek[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->format('j'),
                'isCurrentMonth' => $currentDate->month == $month,
                'isToday' => $currentDate->isToday(),
                'isWeekend' => $currentDate->isWeekend(),
            ];

            if ($currentDate->dayOfWeek === Carbon::SUNDAY) {
                $weeks[] = ['days' => $currentWeek];
                $currentWeek = [];
            }

            $currentDate->addDay();
        }

        if (!empty($currentWeek)) {
            $weeks[] = ['days' => $currentWeek];
        }

        return $weeks;
    }

    private function getHolidays(array $filters): array
    {
        $year = $filters['year'];

        $holidays = $this->organisation->holidays()
            ->whereYear('from', $year)
            ->get(['name', 'from', 'to']);

        return $holidays->map(function ($holiday) {
            return [
                'name' => $holiday->name,
                'from' => $holiday->from->format('Y-m-d'),
                'to' => $holiday->to->format('Y-m-d'),
            ];
        })->toArray();
    }

    private function applyTeamScope(array &$filters): void
    {
        if ($this->canEdit) {
            return;
        }

        $user = request()->user();
        $userEmployee = $user->employees()
            ->where('organisation_id', $this->organisation->id)
            ->first();

        if (!$userEmployee) {
            return;
        }

        $userJobPositions = $userEmployee->jobPositions;

        if ($userJobPositions->isEmpty()) {
            return;
        }

        $departments = $userJobPositions->pluck('department')->filter()->unique()->values()->all();
        $teams = $userJobPositions->pluck('team')->filter()->unique()->values()->all();

        if (!empty($departments)) {
            $filters['departments'] = $departments;
        }
        if (!empty($teams)) {
            $filters['teams'] = $teams;
        }
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->where('start_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('end_date', '<=', $filters['to']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (!empty($filters['department'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department', $filters['department']);
            });
        }
        if (!empty($filters['team'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('team', $filters['team']);
            });
        }
        if (!empty($filters['departments'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('department', $filters['departments']);
            });
        }
        if (!empty($filters['teams'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('team', $filters['teams']);
            });
        }
    }

    private function logExport(array $filters, string $format, int $count): void
    {
        $user = request()->user();

        logger('Leave calendar exported', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'organisation_id' => $this->organisation->id,
            'filters' => $filters,
            'format' => $format,
            'record_count' => $count,
        ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
