<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\Timesheet\CalculateTimesheetOvertime;
use App\Actions\HumanResources\Timesheet\ResolveTimesheetDateRange;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithTabsBox; // Trait Tabs
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum;
use App\Enums\UI\HumanResources\TimesheetsTabsEnum;
use App\Http\Resources\HumanResources\TimesheetEmployeeSummaryResource;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Clocking;
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
use Illuminate\Support\Collection;
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

        return TimesheetsTabsEnum::navigation();
    }
    private $statsQuery;

    protected function resolvePeriodRange(): ?array
    {
        return ResolveTimesheetDateRange::run();
    }

    public function handle(Group|Organisation|Employee|Guest $parent, ?string $prefix = null, bool $isTodayTimesheet = false): LengthAwarePaginator
    {
        if ($prefix === TimesheetsTabsEnum::PER_EMPLOYEE->value) {
            return $this->handlePerEmployeeSummary($parent, $prefix);
        }

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

        $query->with(['subject.jobPositions', 'timeTrackers']);

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
            'first_clocking_notes' => Clocking::query()
                ->select('notes')
                ->whereColumn('timesheet_id', 'timesheets.id')
                ->orderBy('clocked_at')
                ->orderBy('id')
                ->limit(1),
        ]);

        return $query
            ->defaultSort('date')
            ->allowedSorts(['date', 'subject_name', 'working_duration', 'breaks_duration'])
            ->allowedFilters([$globalSearch, 'subject_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function handlePerEmployeeSummary(Group|Organisation|Employee|Guest $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($parent instanceof Guest) {
            return $this->handlePerSubjectSummaryFromTimesheets($parent, $prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('employees.contact_name', $value);
            });
        });

        $stateFilter = AllowedFilter::callback('state', function ($query, $value) {
            if ($value === 'all') {
                return;
            }

            $query->where('employees.state', $value);
        });

        $stateFilterKey = $prefix ? "{$prefix}_filter" : 'filter';
        $selectedState  = Arr::get(request()->input($stateFilterKey, []), 'state', EmployeeStateEnum::WORKING->value);

        $query = QueryBuilder::for(Employee::class);

        if ($parent instanceof Organisation) {
            $query->where('employees.organisation_id', $parent->id);
            if ($selectedState !== 'all') {
                $query->where('employees.state', $selectedState);
            }
        } elseif ($parent instanceof Employee) {
            $query->where('employees.id', $parent->id);
        } else {
            $query->where('employees.group_id', $parent->id);
            if ($selectedState !== 'all') {
                $query->where('employees.state', $selectedState);
            }
        }

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        [$from, $to] = $this->resolvePeriodRange() ?? [null, null];

        $query->leftJoin('timesheets', function ($join) use ($from, $to) {
            $join->on('timesheets.subject_id', '=', 'employees.id')
                ->where('timesheets.subject_type', '=', 'Employee');

            if ($from && $to) {
                $join->whereBetween('timesheets.date', [$from, $to]);
            }
        });

        $query
            ->select([
                'employees.id as subject_id',
                'employees.contact_name as subject_name',
                'employees.job_title as job_position',
                'employees.slug as subject_slug',
            ])
            ->selectRaw("'Employee' as subject_type")
            ->selectRaw('count(timesheets.id) as timesheet_count')
            ->selectRaw('coalesce(sum(timesheets.number_time_trackers), 0) as clockings')
            ->selectRaw('coalesce(sum(timesheets.working_duration), 0) as working_duration')
            ->selectRaw('coalesce(sum(timesheets.breaks_duration), 0) as breaks_duration')
            ->groupBy('employees.id', 'employees.contact_name', 'employees.job_title', 'employees.slug');

        $employeeView = TimesheetEmployeeViewEnum::tryFrom((string) request()->input('view')) ?? TimesheetEmployeeViewEnum::OVERVIEW;
        $sourceColumn = $employeeView->sourceColumn();

        if ($sourceColumn === 'working_duration') {
            foreach ($this->weekdayPivotSelects($sourceColumn) as $selectRaw) {
                $query->selectRaw($selectRaw);
            }
        }

        return $query
            ->defaultSort('subject_name')
            ->allowedSorts(['subject_name', 'working_duration', 'breaks_duration'])
            ->allowedFilters([$globalSearch, 'subject_name', $stateFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function handlePerSubjectSummaryFromTimesheets(Guest $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('timesheets.subject_name', $value);
            });
        });

        $query = QueryBuilder::for(Timesheet::class)
            ->where('timesheets.subject_type', 'Guest')
            ->where('timesheets.subject_id', $parent->id);

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        [$from, $to] = $this->resolvePeriodRange() ?? [null, null];
        if ($from && $to) {
            $query->whereBetween('timesheets.date', [$from, $to]);
        }

        $query
            ->select([
                'timesheets.subject_type',
                'timesheets.subject_id',
                'timesheets.subject_name',
            ])
            ->selectRaw('count(timesheets.id) as timesheet_count')
            ->selectRaw('sum(timesheets.number_time_trackers) as clockings')
            ->selectRaw('sum(timesheets.working_duration) as working_duration')
            ->selectRaw('sum(timesheets.breaks_duration) as breaks_duration')
            ->groupBy('timesheets.subject_type', 'timesheets.subject_id', 'timesheets.subject_name');

        $employeeView = TimesheetEmployeeViewEnum::tryFrom((string) request()->input('view')) ?? TimesheetEmployeeViewEnum::OVERVIEW;
        $sourceColumn = $employeeView->sourceColumn();

        if ($sourceColumn === 'working_duration') {
            foreach ($this->weekdayPivotSelects($sourceColumn) as $selectRaw) {
                $query->selectRaw($selectRaw);
            }
        }

        return $query
            ->defaultSort('subject_name')
            ->allowedSorts(['subject_name', 'working_duration', 'breaks_duration'])
            ->allowedFilters([$globalSearch, 'subject_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function weekdayPivotSelects(string $sourceColumn): array
    {
        $days = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ];

        $selects = [];
        foreach ($days as $isoDayOfWeek => $dayLabel) {
            $selects[] = "sum(case when extract(isodow from timesheets.date) = {$isoDayOfWeek} then timesheets.{$sourceColumn} else 0 end) as {$dayLabel}";
        }

        $selects[] = "sum(case when extract(isodow from timesheets.date) between 1 and 5 then timesheets.{$sourceColumn} else 0 end) as work_week";
        $selects[] = "sum(case when extract(isodow from timesheets.date) between 6 and 7 then timesheets.{$sourceColumn} else 0 end) as weekend";

        return $selects;
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
        $shouldSubHour = false;
        if ($this->parent instanceof Organisation) {
            $organisationId = $this->parent->id;
            $timezone = $this->parent->timezone->name ?? 'UTC';
            $shouldSubHour = $this->parent->code === 'SK';
        } elseif ($this->parent instanceof Employee) {
            $organisationId = $this->parent->organisation_id;
            $timezone = $this->parent->organisation->timezone->name ?? 'UTC';
            $shouldSubHour = $this->parent->organisation?->code === 'SK';
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
                ->when($shouldSubHour, fn ($dt) => $dt->subHour())
                ->setTimezone($timezone);

            $endAt = $ts->end_at
                ?->copy()
                ->when($shouldSubHour, fn ($dt) => $dt->subHour())
                ->setTimezone($timezone);

            $scheduledStart = null;
            $scheduledEnd = null;

            if ($startAt && $daySchedule->start_time && $daySchedule->end_time) {
                $scheduledStart = $startAt->copy()->setTimeFromTimeString($daySchedule->start_time);
                $scheduledEnd = $startAt->copy()->setTimeFromTimeString($daySchedule->end_time);
            }

            $isLate = false;
            $isEarly = false;

            if ($startAt && $scheduledStart && $startAt->gt($scheduledStart->copy()->addMinutes(1))) {
                $lateClockIn++;
                $isLate = true;
            }

            if ($ts->number_open_time_trackers == 0 && $endAt && $scheduledEnd && $endAt->lt($scheduledEnd->copy()->subMinutes(1))) {
                $earlyClockOut++;
                $isEarly = true;
            }

            if (!$isLate && !$isEarly && $startAt && $endAt && $ts->number_open_time_trackers == 0) {
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
                ->withModelOperations($modelOperations);

            if ($prefix === TimesheetsTabsEnum::PER_EMPLOYEE->value) {
                request()->query->remove("{$prefix}_columns");

                $table->column(key: 'subject_name', label: __('Name'), sortable: true, searchable: true);

                if ($parent instanceof Organisation || $parent instanceof Group) {
                    $table->column(key: 'job_position', label: __('Job Position'));

                    $table->selectFilter(
                        'state',
                        ['all' => __('All')] + EmployeeStateEnum::labels(),
                        __('State'),
                        EmployeeStateEnum::WORKING->value,
                        false
                    );
                }

                $table->betweenDates(['date']);

                $employeeView = TimesheetEmployeeViewEnum::tryFrom((string) request()->input('view')) ?? TimesheetEmployeeViewEnum::OVERVIEW;

                if ($employeeView === TimesheetEmployeeViewEnum::OVERVIEW) {
                    $table->column(key: 'clockings', label: __('Clockings'), sortable: true)
                        ->column(key: 'working_duration', label: __('Clocked'), sortable: true)
                        ->column(key: 'unpaid_overtime_duration', label: __('Unpaid overtime'), sortable: true)
                        ->column(key: 'breaks_duration', label: __('Breaks'), sortable: true)
                        ->column(key: 'paid_duration', label: __('Paid time'), sortable: true)
                        ->column(key: 'paid_overtime_duration', label: __('Paid overtime'), sortable: true)
                        ->column(key: 'worked', label: __('Worked'), sortable: false);
                } else {
                    $table->column(key: 'monday', label: __('Monday'), sortable: true)
                        ->column(key: 'tuesday', label: __('Tuesday'), sortable: true)
                        ->column(key: 'wednesday', label: __('Wednesday'), sortable: true)
                        ->column(key: 'thursday', label: __('Thursday'), sortable: true)
                        ->column(key: 'friday', label: __('Friday'), sortable: true)
                        ->column(key: 'saturday', label: __('Saturday'), sortable: true)
                        ->column(key: 'sunday', label: __('Sunday'), sortable: true)
                        ->column(key: 'work_week', label: __('Work week'), sortable: true)
                        ->column(key: 'weekend', label: __('Weekend'), sortable: true);
                }

                $table->defaultSort('subject_name');

                return;
            }

            $table->column(key: 'date', label: __('Date'), sortable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'subject_name', label: __('Name'), sortable: true, searchable: true);
                $table->column(key: 'job_position', label: __('Job Position'));
            }

            $table->betweenDates(['date']);

            $table->column(key: 'start_at', label: __('Start At'))
                ->column(key: 'end_at', label: __('End At'))
                ->column(key: 'notes', label: __('Notes'))
                ->column(key: 'working_duration', label: __('Working'), sortable: true)
                ->column(key: 'unpaid_overtime_duration', label: __('Unpaid overtime'), sortable: true)
                ->column(key: 'breaks_duration', label: __('Breaks'), sortable: true)
                ->column(key: 'paid_duration', label: __('Paid time'), sortable: true)
                ->column(key: 'paid_overtime_duration', label: __('Paid overtime'), sortable: true)
                ->column(key: 'worked', label: __('Worked'))
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
        $overtimeByTimesheetId = CalculateTimesheetOvertime::make()->handleMany($timesheets->getCollection());

        $timesheets->through(function ($timesheet) use ($overtimeByTimesheetId) {

            $jobPositions = '-';
            $employeeSlug = null;

            if ($timesheet->subject_type === 'Employee' && $timesheet->subject) {
                $jobPositions = $timesheet->subject->job_title;
                $employeeSlug = $timesheet->subject->slug;
            }
            $timesheet->setAttribute('job_position', $jobPositions ?: '-');
            $timesheet->setAttribute('subject_slug', $employeeSlug);
            $timesheet->setAttribute('clock_in_count', $timesheet->number_time_trackers);
            $timesheet->setAttribute('clock_out_count', $timesheet->number_time_trackers - $timesheet->number_open_time_trackers);
            $timesheet->setAttribute('notes', $timesheet->first_clocking_notes);

            $overtime = $overtimeByTimesheetId->get($timesheet->id) ?? [
                'paid_duration'            => 0,
                'unpaid_overtime_duration' => 0,
                'paid_overtime_duration'   => 0,
            ];

            $timesheet->setAttribute('paid_duration', $overtime['paid_duration']);
            $timesheet->setAttribute('unpaid_overtime_duration', $overtime['unpaid_overtime_duration']);
            $timesheet->setAttribute('paid_overtime_duration', $overtime['paid_overtime_duration']);
            $timesheet->setAttribute('worked', $timesheet->working_duration);

            return $timesheet;
        });

        return TimesheetsResource::collection($timesheets);
    }

    public function jsonResponsePerEmployee(LengthAwarePaginator $timesheets): AnonymousResourceCollection
    {
        $employeeView = TimesheetEmployeeViewEnum::tryFrom((string) request()->input('view')) ?? TimesheetEmployeeViewEnum::OVERVIEW;
        $sourceColumn = $employeeView->sourceColumn();
        $needsScheduleCalculation = $employeeView === TimesheetEmployeeViewEnum::OVERVIEW
            || ($sourceColumn && $sourceColumn !== 'working_duration');

        $overtimeByEmployee = $needsScheduleCalculation
            ? $this->calculateOvertimeByEmployee($timesheets->getCollection(), $employeeView, $sourceColumn)
            : collect();

        $timesheets->through(function ($timesheet) use ($overtimeByEmployee, $employeeView, $sourceColumn) {
            $timesheet->setAttribute('worked', $timesheet->working_duration);

            $overtime = $overtimeByEmployee->get($timesheet->subject_id);

            if ($employeeView === TimesheetEmployeeViewEnum::OVERVIEW) {
                $timesheet->setAttribute('paid_duration', $overtime['paid_duration'] ?? 0);
                $timesheet->setAttribute('unpaid_overtime_duration', $overtime['unpaid_overtime_duration'] ?? 0);
                $timesheet->setAttribute('paid_overtime_duration', $overtime['paid_overtime_duration'] ?? 0);
            } elseif ($sourceColumn && $sourceColumn !== 'working_duration') {
                foreach ($overtime['by_day'] ?? $this->emptyWeekdayBucket() as $key => $value) {
                    $timesheet->setAttribute($key, $value);
                }
            }

            return $timesheet;
        });

        return TimesheetEmployeeSummaryResource::collection($timesheets);
    }

    /**
     * @return Collection<int, array{paid_duration: int, unpaid_overtime_duration: int, paid_overtime_duration: int, by_day: array}>
     */
    protected function calculateOvertimeByEmployee(Collection $rows, TimesheetEmployeeViewEnum $employeeView, ?string $sourceColumn): Collection
    {
        $employeeIds = $rows->where('subject_type', 'Employee')->pluck('subject_id')->all();

        if (empty($employeeIds)) {
            return collect();
        }

        [$from, $to] = $this->resolvePeriodRange() ?? [null, null];

        $timesheetsQuery = Timesheet::query()
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $employeeIds)
            ->with('timeTrackers');

        if ($from && $to) {
            $timesheetsQuery->whereBetween('date', [$from, $to]);
        }

        $timesheets = $timesheetsQuery->get();

        $overtimeByTimesheetId = CalculateTimesheetOvertime::make()->handleMany($timesheets);

        $overtimeByEmployee = [];

        foreach ($timesheets as $timesheet) {
            $values = $overtimeByTimesheetId->get($timesheet->id) ?? [
                'paid_duration'            => 0,
                'unpaid_overtime_duration' => 0,
                'paid_overtime_duration'   => 0,
            ];

            $employeeId = $timesheet->subject_id;

            if (!isset($overtimeByEmployee[$employeeId])) {
                $overtimeByEmployee[$employeeId] = [
                    'paid_duration'            => 0,
                    'unpaid_overtime_duration' => 0,
                    'paid_overtime_duration'   => 0,
                    'by_day'                   => $this->emptyWeekdayBucket(),
                ];
            }

            $overtimeByEmployee[$employeeId]['paid_duration']            += $values['paid_duration'];
            $overtimeByEmployee[$employeeId]['unpaid_overtime_duration'] += $values['unpaid_overtime_duration'];
            $overtimeByEmployee[$employeeId]['paid_overtime_duration']   += $values['paid_overtime_duration'];

            if ($employeeView !== TimesheetEmployeeViewEnum::OVERVIEW && $sourceColumn) {
                $metricValue = $values[$sourceColumn] ?? 0;
                $isoDayOfWeek = $timesheet->date->dayOfWeekIso;
                $dayKey = $this->weekdayKeyFor($isoDayOfWeek);

                $overtimeByEmployee[$employeeId]['by_day'][$dayKey] += $metricValue;

                if ($isoDayOfWeek <= 5) {
                    $overtimeByEmployee[$employeeId]['by_day']['work_week'] += $metricValue;
                } else {
                    $overtimeByEmployee[$employeeId]['by_day']['weekend'] += $metricValue;
                }
            }
        }

        return collect($overtimeByEmployee);
    }

    protected function weekdayKeyFor(int $isoDayOfWeek): string
    {
        return [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ][$isoDayOfWeek];
    }

    protected function emptyWeekdayBucket(): array
    {
        return [
            'monday'    => 0,
            'tuesday'   => 0,
            'wednesday' => 0,
            'thursday'  => 0,
            'friday'    => 0,
            'saturday'  => 0,
            'sunday'    => 0,
            'work_week' => 0,
            'weekend'   => 0,
        ];
    }

    public function htmlResponse(LengthAwarePaginator|Group|Organisation|Employee|Guest $parent, ActionRequest $request): Response
    {

        if ($parent instanceof LengthAwarePaginator) {
            $parent = $this->parent;
        }

        if (empty($this->tab)) {
            $this->tab = TimesheetsTabsEnum::PER_EMPLOYEE->value;
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
                    'actions'       => ($this->parent instanceof Organisation || $this->parent instanceof Employee) ? [
                        [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'key'   => 'export-timesheets',
                            'label' => __('Export'),
                            'icon'  => ['fal', 'fa-download'],
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'timesheet',
                            'label' => __('Add timesheet'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ] : [],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsBox($this->parent)
                ],
                'employee_view' => [
                    'current'    => (TimesheetEmployeeViewEnum::tryFrom((string) request()->input('view')) ?? TimesheetEmployeeViewEnum::OVERVIEW)->value,
                    'navigation' => TimesheetEmployeeViewEnum::navigation(),
                ],
                'employeeContext' => $this->parent instanceof Employee ? [
                    'id'   => $this->parent->id,
                    'slug' => $this->parent->slug,
                    'name' => $this->parent->contact_name,
                ] : null,
                'employeeOptions' => $this->parent instanceof Organisation
                    ? $this->parent->employees()
                        ->where('state', EmployeeStateEnum::WORKING)
                        ->orderBy('alias')
                        ->get()
                        ->map(fn (Employee $employee) => [
                            'value' => $employee->id,
                            'label' => $employee->contact_name,
                        ])
                    : [],

                TimesheetsTabsEnum::ALL_EMPLOYEES->value => $this->tab == TimesheetsTabsEnum::ALL_EMPLOYEES->value
                    ? fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::ALL_EMPLOYEES->value))
                    : Inertia::optional(fn () => $this->jsonResponse($this->handle($this->parent, TimesheetsTabsEnum::ALL_EMPLOYEES->value))),

                TimesheetsTabsEnum::PER_EMPLOYEE->value => $this->tab == TimesheetsTabsEnum::PER_EMPLOYEE->value
                    ? fn () => $this->jsonResponsePerEmployee($this->handle($this->parent, TimesheetsTabsEnum::PER_EMPLOYEE->value))
                    : Inertia::optional(fn () => $this->jsonResponsePerEmployee($this->handle($this->parent, TimesheetsTabsEnum::PER_EMPLOYEE->value))),
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

    protected function applyStatusFilter(QueryBuilder $query, Group|Organisation|Employee|Guest $parent, string $timezone): void
    {
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
        $shouldSubHour = false;

        if ($parent instanceof Organisation) {
            $organisationId = $parent->id;
            $shouldSubHour = $parent->code === 'SK';
        } elseif ($parent instanceof Employee) {
            $organisationId = $parent->organisation_id;
            $shouldSubHour = $parent->organisation?->code === 'SK';
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
                ->when($shouldSubHour, fn ($dt) => $dt->subHour())
                ->setTimezone($timezone);

            $endAt = $ts->end_at
                ?->copy()
                ->when($shouldSubHour, fn ($dt) => $dt->subHour())
                ->setTimezone($timezone);

            $scheduledStart = null;
            $scheduledEnd = null;

            if ($startAt && $daySchedule->start_time && $daySchedule->end_time) {
                $scheduledStart = $startAt->copy()->setTimeFromTimeString($daySchedule->start_time);
                $scheduledEnd = $startAt->copy()->setTimeFromTimeString($daySchedule->end_time);
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
            } elseif (
                $status === 'on_time'
                && !$isLateClockIn
                && !$isEarlyClockOut
                && $startAt
                && $endAt
                && $ts->number_open_time_trackers == 0
            ) {
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
