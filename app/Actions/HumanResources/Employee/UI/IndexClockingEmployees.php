<?php

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Clocking\ClockingEmployeesTabsEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Illuminate\Support\Facades\Auth;
use App\Services\QueryBuilder;
use App\Models\HumanResources\Timesheet;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\Models\HumanResources\WorkSchedule;
use App\Models\HumanResources\QrScanLog;
use Illuminate\Support\Carbon;
use App\Enums\Helpers\Period\PeriodEnum;
use Closure;
use App\InertiaTable\InertiaTable;
use App\Actions\HumanResources\WithEmployeeSubNavigation;

class IndexClockingEmployees extends OrgAction
{
    use AsAction;
    use WithEmployeeSubNavigation;
    protected ?string $tab = null;

    public function handle(ActionRequest $request): array
    {
        $this->tab = $request->input('tab');
        if (!$this->tab) {
            $this->tab = ClockingEmployeesTabsEnum::SCAN_QR_CODE->value;
        }
        $tab = $request->input('tab') ?? ClockingEmployeesTabsEnum::SCAN_QR_CODE->value;

        $user = Auth::user();
        $employee = $user?->employees->first();

        $timesheetsData = collect();
        $statistics = [];

        if ($this->tab == ClockingEmployeesTabsEnum::TIMESHEETS->value && $employee) {

            InertiaTable::updateQueryBuilderParameters(ClockingEmployeesTabsEnum::TIMESHEETS->value);

            $query = QueryBuilder::for(Timesheet::class)
                ->where('subject_type', 'Employee')
                ->where('subject_id', $employee->id)
                ->with(['subject.jobPositions']);

            $timezone = $employee->organisation->timezone->name ?? 'UTC';

            [$from, $to] = $this->resolvePeriodRange() ?? [null, null];

            if ($from && $to) {
                $query->whereBetween('timesheets.date', [$from, $to]);
            }

            $statsQuery = clone $query;

            $statistics = $this->getStatistics($employee, $statsQuery, $timezone, $from, $to);

            $timesheets = $query
                ->defaultSort('date')
                ->allowedSorts(['date', 'working_duration', 'breaks_duration'])
                ->paginate(request()->input('per_page', 15))
                ->withQueryString();

            $timesheetsData = $timesheets;
        }

        return [
            'tab' =>  $tab,
            'timesheets' => $timesheetsData,
            'statistics' => $statistics,
        ];
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {

            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            foreach ($this->getPeriodFilters() as $periodFilter) {
                $table->periodFilters($periodFilter['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No timesheets found'),
                    'count' => 0
                ])
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'subject_name', label: __('Name'))
                ->column(key: 'job_position', label: __('Job Position'))
                ->column(key: 'start_at', label: __('Start At'))
                ->column(key: 'end_at', label: __('End At'))
                ->column(key: 'working_duration', label: __('Working'), sortable: true)
                ->column(key: 'breaks_duration', label: __('Breaks'), sortable: true)
                ->column(key: 'clock_in_count', label: __('Clock In Count'))
                ->column(key: 'clock_out_count', label: __('Clock Out Count'))
                ->column(key: 'number_time_trackers', label: __('Time Trackers'))
                ->column(key: 'number_open_time_trackers', label: __('Open Trackers'));
            $table->defaultSort('-date');
        };
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/ClockingEmployees',
            [
                'title'       => __('Employee Clocking'),
                'breadcrumbs' => $this->getBreadcrumbs($request),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-clock'],
                        'title' => __('Employee Clocking')
                    ],
                    'title' => __('Clock In/Out'),
                    'model' => __('Clocking'),
                ],
                'tabs' => [
                    'current'       => $data['tab'],
                    'navigation'    => ClockingEmployeesTabsEnum::navigation(),
                ],
                ClockingEmployeesTabsEnum::SCAN_QR_CODE->value =>
                $data['tab'] == ClockingEmployeesTabsEnum::SCAN_QR_CODE->value
                    ? fn() => ['status' => 'ready_to_scan']
                    : Inertia::lazy(fn() => ['status' => 'loaded_lazy']),

                ClockingEmployeesTabsEnum::TIMESHEETS->value =>
                $data['tab'] === ClockingEmployeesTabsEnum::TIMESHEETS->value
                    ? fn() => [
                        'data' => TimesheetsResource::collection($data['timesheets']),
                        'statistics' => $data['statistics'],
                    ]
                    : Inertia::lazy(fn() => [
                        'data' => TimesheetsResource::collection($data['timesheets']),
                        'statistics' => $data['statistics'],
                    ]),
            ]
        )
            ->table(
                $this->tableStructure(
                    ClockingEmployeesTabsEnum::TIMESHEETS->value
                )
            );
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request);
    }


    public function getBreadcrumbs(ActionRequest $request): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Employee Clocking'),
                        'route' => [
                            'name' => 'grp.clocking_employees.index'
                        ]
                    ]
                ]
            ]
        );
    }

    protected function resolvePeriodRange(): ?array
    {
        $period = request()->input('timesheets_period');

        if (!$period || !is_array($period)) {
            // Default: Month
            return [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ];
        }
        return PeriodEnum::toDateRange($period);
    }

    protected function getStatistics($employee, $statsQuery, $timezone, $from, $to): array
    {
        if (!$statsQuery) {
            return [];
        }

        $baseQuery = $statsQuery->clone();

        $total = (clone $baseQuery)->count();
        $noClockOut = (clone $baseQuery)->where('number_open_time_trackers', '>', 0)->count();

        $organisationId = $employee->organisation_id;

        $invalidScanCount = 0;
        if ($organisationId) {
            $invalidQuery = QrScanLog::where('organisation_id', $organisationId)
                ->where('status', 'failed')
                ->where('employee_id', $employee->id);

            if ($from && $to) {
                $invalidQuery->whereBetween('scanned_at', [
                    Carbon::parse($from, $timezone)->startOfDay()->utc(),
                    Carbon::parse($to, $timezone)->endOfDay()->utc(),
                ]);
            }

            $invalidScanCount = $invalidQuery->count();
        }

        $schedule = null;
        if ($organisationId) {
            $schedule = WorkSchedule::where('schedulable_type', 'Organisation')
                ->where('schedulable_id', $organisationId)
                ->where('is_active', true)
                ->with('days')
                ->first();
        }

        if (!$schedule) {
            return [
                'on_time' => 0,
                'late_clock_in' => 0,
                'early_clock_out' => 0,
                'no_clock_out' => $noClockOut,
                'invalid' => $invalidScanCount,
                'absent' => 0,
                'total' => $total,
            ];
        }

        $scheduleMap = $schedule->days->keyBy('day_of_week');

        $timesheetsQuery = (clone $baseQuery)
            ->setEagerLoads([])
            ->select(['timesheets.date', 'timesheets.start_at', 'timesheets.end_at', 'timesheets.number_open_time_trackers']);

        $lateClockIn = 0;
        $earlyClockOut = 0;
        $onTime = 0;

        foreach ($timesheetsQuery->cursor() as $ts) {

            $dayOfWeek = $ts->date->dayOfWeekIso;
            $daySchedule = $scheduleMap->get($dayOfWeek);

            if (!$daySchedule || !$daySchedule->is_working_day) {
                continue;
            }

            $startAt = $ts->start_at
                ?->copy()
                ->setTimezone($timezone);

            $endAt = $ts->end_at
                ?->copy()
                ->setTimezone($timezone);

            $scheduledStart = null;
            $scheduledEnd = null;

            if ($startAt) {
                $scheduledStart = $startAt->copy()->setTime(
                    $daySchedule->start_time->hour,
                    $daySchedule->start_time->minute,
                    $daySchedule->start_time->second ?? 0,
                );

                $scheduledEnd = $startAt->copy()->setTime(
                    $daySchedule->end_time->hour,
                    $daySchedule->end_time->minute,
                    $daySchedule->end_time->second ?? 0,
                );
            }

            $isLate = false;

            if ($scheduledStart && $startAt && $startAt->gt($scheduledStart->copy()->addMinutes(1))) {
                $lateClockIn++;
                $isLate = true;
            }

            if ($scheduledEnd && $ts->number_open_time_trackers == 0 && $endAt && $endAt->lt($scheduledEnd->copy()->subMinutes(1))) {
                $earlyClockOut++;
            }

            if (!$isLate && $startAt) {
                $onTime++;
            }
        }

        return [
            'on_time' => $onTime,
            'late_clock_in' => $lateClockIn,
            'early_clock_out' => $earlyClockOut,
            'no_clock_out' => $noClockOut,
            'invalid' => $invalidScanCount,
            'absent' => 0,
            'total' => $total,
        ];
    }

    protected function getPeriodFilters(): array
    {
        $elements = array_merge_recursive(
            PeriodEnum::labels(),
            PeriodEnum::date()
        );

        return [
            'employees_period' => [
                'label'    => __('Period'),
                'elements' => $elements
            ],
        ];
    }
}
