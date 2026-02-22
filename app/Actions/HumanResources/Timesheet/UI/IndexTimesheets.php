<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithTabsBox; // Trait Tabs
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\Helpers\Period\PeriodEnum;
use App\Enums\UI\HumanResources\TimesheetsTabsEnum;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\HumanResources\WorkSchedule;
use App\Models\HumanResources\QrScanLog;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;

class IndexTimesheets extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesAuthorisation;
    use WithTabsBox;

    private Group|Employee|Organisation|Guest $parent;


    public function getTabsBox(Group|Organisation|Shop|Employee|Guest $parent): array
    {
        if ($parent instanceof Employee || $parent instanceof Guest) {
            return [];
        }

        return [];
    }
    private $statsQuery;

    protected function resolvePeriodRange(): ?array
    {
        $period = request()->input('period');

        if ($period && is_array($period)) {
            return PeriodEnum::toDateRange($period);
        }

        $employeesPeriod = request()->input('employees_period');

        if ($employeesPeriod && is_array($employeesPeriod)) {
            return PeriodEnum::toDateRange($employeesPeriod);
        }

        return [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ];
    }

    public function handle(Group|Organisation|Employee|Guest $parent, ?string $prefix = null, bool $isTodayTimesheet = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('timesheets.subject_name', $value);
            });
        });

        $query = QueryBuilder::for(Timesheet::class);

        if ($parent instanceof Organisation) {
            $query->where('timesheets.organisation_id', $parent->id);
            $timezone = $parent->timezone->name ?? 'UTC';
        } elseif ($parent instanceof Employee) {
            $query->where('timesheets.subject_type', 'Employee')
                ->where('timesheets.subject_id', $parent->id);
            $timezone = $parent->organisation->timezone->name ?? 'UTC';
        } elseif ($parent instanceof Group) {
            $query->where('timesheets.group_id', $parent->id);
            $timezone = 'UTC';
        } else {
            $query->where('subject_type', 'Guest')->where('subject_id', $parent->id);
            $timezone = 'UTC';
        }

        $query->leftjoin('organisations', 'timesheets.organisation_id', '=', 'organisations.id');

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query->with(['subject.jobPositions']);

        if ($isTodayTimesheet) {
            $query->whereDate('timesheets.date', now()->setTimezone($timezone)->format('Y-m-d'));
        }

        $query->withFilterPeriod('date');
        [$from, $to] = $this->resolvePeriodRange() ?? [null, null];
        if ($from && $to) {
            $query->whereBetween('timesheets.date', [$from, $to]);
        }

        $this->statsQuery = $query->clone();

        $this->applyStatusFilter($query, $parent, $timezone);

        $query->select([
            'timesheets.*',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
        ]);

        return $query
            ->defaultSort('date')
            ->allowedSorts(['date', 'subject_name', 'working_duration', 'breaks_duration'])
            ->allowedFilters([$globalSearch, 'subject_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function getStatistics(): array
    {
        if (!$this->statsQuery) {
            return [];
        }

        $baseQuery = $this->statsQuery->clone();

        [$from, $to] = $this->resolvePeriodRange() ?? [null, null];
        if ($from && $to) {
            $baseQuery->whereBetween('timesheets.date', [$from, $to]);
        }

        $total = (clone $baseQuery)->count();
        $noClockOut = (clone $baseQuery)->where('number_open_time_trackers', '>', 0)->count();

        $organisationId = null;
        if ($this->parent instanceof Organisation) {
            $organisationId = $this->parent->id;
            $timezone = $this->parent->timezone->name ?? 'UTC';
        } elseif ($this->parent instanceof Employee) {
            $organisationId = $this->parent->organisation_id;
            $timezone = $this->parent->organisation->timezone->name ?? 'UTC';
        } else {
            $timezone = 'UTC';
        }

        $invalidScanCount = 0;
        if ($organisationId) {
            $invalidQuery = QrScanLog::where('organisation_id', $organisationId)
                ->where('status', 'failed')
                ->whereNotNull('employee_id');

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

        $timesheets = (clone $baseQuery)
            ->setEagerLoads([])
            ->select(['timesheets.date', 'timesheets.start_at', 'timesheets.end_at', 'timesheets.number_open_time_trackers']);
        $lateClockIn = 0;
        $earlyClockOut = 0;
        $onTime = 0;

        foreach ($timesheets->cursor() as $ts) {

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

            if ($startAt && $startAt->gt($scheduledStart->copy()->addMinutes(1))) {
                $lateClockIn++;
                $isLate = true;
            }

            if ($ts->number_open_time_trackers == 0 && $endAt && $endAt->lt($scheduledEnd->copy()->subMinutes(1))) {
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
            'period' => [
                'label'    => __('Period'),
                'elements' => $elements
            ],
        ];
    }

    public function tableStructure(Group|Organisation|Employee|Guest $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $noResults = __("No timesheets found");
            if ($parent instanceof Employee || $parent instanceof Guest) {
                $stats     = $parent->stats;
                $noResults = __("Employee has no timesheets");
            } else {
                $stats = $parent->humanResourcesStats;
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => $noResults, 'count' => $stats->number_timesheets ?? 0])
                ->withModelOperations($modelOperations)
                ->column(key: 'date', label: __('Date'), sortable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'subject_name', label: __('Name'), sortable: true, searchable: true);
                $table->column(key: 'job_position', label: __('Job Position'));
            }

            foreach ($this->getPeriodFilters() as $periodFilter) {
                $table->periodFilters($periodFilter['elements']);
            }

            $table->column(key: 'working_duration', label: __('Working'), sortable: true)
                ->column(key: 'breaks_duration', label: __('Breaks'), sortable: true)
                ->column(key: 'clock_in_count', label: __('Clock In'))
                ->column(key: 'clock_out_count', label: __('Clock Out'));

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('Organisation'), searchable: true);
            }
            $table->defaultSort('date');
        };
    }

    public function jsonResponse(LengthAwarePaginator $timesheets): AnonymousResourceCollection
    {

        $timesheets->through(function ($timesheet) {

            $jobPositions = '-';

            if ($timesheet->subject_type === 'Employee' && $timesheet->subject) {
                $jobPositions = $timesheet->subject->job_title;
            }
            $timesheet->setAttribute('job_position', $jobPositions ?: '-');
            $timesheet->setAttribute('clock_in_count', $timesheet->number_time_trackers);
            $timesheet->setAttribute('clock_out_count', $timesheet->number_time_trackers - $timesheet->number_open_time_trackers);

            return $timesheet;
        });

        return TimesheetsResource::collection($timesheets);
    }

    public function htmlResponse(LengthAwarePaginator|Group|Organisation|Employee|Guest $parent, ActionRequest $request): Response
    {

        if ($parent instanceof LengthAwarePaginator) {
            $parent = $this->parent;
        }

        if (empty($this->tab)) {
            $this->tab = TimesheetsTabsEnum::ALL_EMPLOYEES->value;
        }


        $this->handle($this->parent, TimesheetsTabsEnum::ALL_EMPLOYEES->value);

        return Inertia::render(
            'Org/HumanResources/Timesheets',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->parent, $request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('timesheets'),
                'pageHead'    => [
                    'title'         => __('Timesheets'),
                    'icon'          => ['title' => __('Timesheets'), 'icon'  => 'fal fa-stopwatch'],
                ],
                'statistics' => $this->getStatistics(),
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsBox($this->parent)
                ],

                TimesheetsTabsEnum::ALL_EMPLOYEES->value => $this->tab == TimesheetsTabsEnum::ALL_EMPLOYEES->value
                    ? fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::ALL_EMPLOYEES->value))
                    : Inertia::lazy(fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::ALL_EMPLOYEES->value))),

                TimesheetsTabsEnum::PER_EMPLOYEE->value => $this->tab == TimesheetsTabsEnum::PER_EMPLOYEE->value
                    ? fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::PER_EMPLOYEE->value))
                    : Inertia::lazy(fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::PER_EMPLOYEE->value))),
            ]
        )
            ->table($this->tableStructure($this->parent, null, TimesheetsTabsEnum::ALL_EMPLOYEES->value))
            ->table($this->tableStructure($this->parent, null, TimesheetsTabsEnum::PER_EMPLOYEE->value));
    }


    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(TimesheetsTabsEnum::values());

        return $organisation;
    }

    public function inEmployee(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->parent = $employee;
        $this->initialisation($organisation, $request)->withTab(TimesheetsTabsEnum::values());
        return $employee;
    }

    public function inGroup(ActionRequest $request): Group
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request)->withTab(TimesheetsTabsEnum::values());
        return group();
    }

    public function getBreadcrumbs(Group|Organisation|Employee|Guest $parent, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Timesheets'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.timesheets.index' => array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.timesheets.index',
                        'parameters' => Arr::only($routeParameters, 'organisation')
                    ]
                )
            ),
            'grp.org.hr.employees.show.timesheets.index' => array_merge(
                ShowEmployee::make()->getBreadcrumbs($parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.employees.show.timesheets.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.hr.timesheets.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.hr.timesheets.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }

    protected function applyStatusFilter(QueryBuilder $query,Group|Organisation|Employee|Guest $parent,string $timezone): void {
        $status = request()->input('timesheet_status');

        if (!$status) {
            return;
        }

        if (!in_array($status, ['on_time', 'late_clock_in', 'early_clock_out', 'no_clock_out'], true)) {
            return;
        }

        if ($status === 'no_clock_out') {
            $query->where('timesheets.number_open_time_trackers', '>', 0);

            return;
        }

        $organisationId = null;

        if ($parent instanceof Organisation) {
            $organisationId = $parent->id;
        } elseif ($parent instanceof Employee) {
            $organisationId = $parent->organisation_id;
        }

        if (!$organisationId) {
            return;
        }

        $schedule = WorkSchedule::where('schedulable_type', 'Organisation')
            ->where('schedulable_id', $organisationId)
            ->where('is_active', true)
            ->with('days')
            ->first();

        if (!$schedule) {
            return;
        }

        $scheduleMap = $schedule->days->keyBy('day_of_week');

        $baseQuery = $query->clone()
            ->setEagerLoads([])
            ->select([
                'timesheets.id',
                'timesheets.date',
                'timesheets.start_at',
                'timesheets.end_at',
                'timesheets.number_open_time_trackers',
            ]);

        $matchingIds = [];

        foreach ($baseQuery->cursor() as $ts) {
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

            $isLateClockIn = false;
            $isEarlyClockOut = false;

            if (
                $scheduledStart
                && $startAt
                && $startAt->gt($scheduledStart->copy()->addMinutes(1))
            ) {
                $isLateClockIn = true;
            }

            if (
                $scheduledEnd
                && $ts->number_open_time_trackers == 0
                && $endAt
                && $endAt->lt($scheduledEnd->copy()->subMinutes(1))
            ) {
                $isEarlyClockOut = true;
            }

            if ($status === 'late_clock_in' && $isLateClockIn) {
                $matchingIds[] = $ts->id;
            } elseif ($status === 'early_clock_out' && $isEarlyClockOut) {
                $matchingIds[] = $ts->id;
            } elseif ($status === 'on_time' && !$isLateClockIn && $startAt) {
                $matchingIds[] = $ts->id;
            }
        }

        if (!empty($matchingIds)) {
            $query->whereIn('timesheets.id', $matchingIds);
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
