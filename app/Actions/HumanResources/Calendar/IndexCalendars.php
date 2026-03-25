<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:12:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Calendar;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexCalendars extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithCalendarSubNavigation;

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('employees.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Employee::class)
            ->defaultSort('employees.slug')
            ->select(['slug', 'id', 'job_title', 'contact_name', 'state'])
            ->with('jobPositions')
            ->allowedSorts(['slug', 'state', 'contact_name', 'job_title'])
            ->allowedFilters([$globalSearch, 'slug', 'contact_name', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->column(key: 'slug', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'job_title', label: __('position'), canBeHidden: false)
                ->column(key: 'state', label: __('state'), canBeHidden: false)
                ->defaultSort('slug');
        };
    }

    public function jsonResponse(LengthAwarePaginator $employees): AnonymousResourceCollection
    {
        return EmployeeResource::collection($employees);
    }


    public function htmlResponse(LengthAwarePaginator $employees, ActionRequest $request): Response
    {
        $year  = (int) $request->input('year', now()->year);
        $month = $request->integer('month') ?: null;

        $activeHolidayYear = DB::table('holiday_years')
            ->where('organisation_id', $this->organisation->id)
            ->where('is_active', true)
            ->select(['id', 'label', 'start_date', 'end_date'])
            ->first();

        $allHolidayYears = DB::table('holiday_years')
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('start_date', 'desc')
            ->select(['id', 'label', 'start_date', 'end_date', 'is_active'])
            ->get();

        $holidayYearPeriod = $activeHolidayYear ? [
            'id'         => $activeHolidayYear->id,
            'label'      => $activeHolidayYear->label,
            'start_date' => (string) $activeHolidayYear->start_date,
            'end_date'   => (string) $activeHolidayYear->end_date,
        ] : null;

        $defaultPeriod = [
            'start_date' => sprintf('%d-01-01', $year),
            'end_date'   => sprintf('%d-12-31', $year),
        ];

        $holidaysQuery = Holiday::query()
            ->where('organisation_id', $this->organisation->id);

        $holidaysQuery->where(function ($query) use ($year, $allHolidayYears) {
            $query->where('year', $year);

            if ($allHolidayYears->isNotEmpty()) {
                $minDate = $allHolidayYears->min('start_date');
                $maxDate = $allHolidayYears->max('end_date');

                $query->orWhere(function ($q) use ($minDate, $maxDate) {
                    $q->where('from', '<=', $maxDate)
                      ->where('to', '>=', $minDate);
                });
            }
        });

        $holidays = $holidaysQuery->get();

        $holidayDates = [];

        foreach ($holidays as $holiday) {
            $currentDate = $holiday->from->copy();
            while ($currentDate->lte($holiday->to)) {
                $dateKey = $currentDate->format('Y-m-d');

                if (!array_key_exists($dateKey, $holidayDates)) {
                    $holidayDates[$dateKey] = [
                        'date'   => $dateKey,
                        'labels' => [],
                    ];
                }

                if ($holiday->label) {
                    $holidayDates[$dateKey]['labels'][] = $holiday->label;
                }

                $currentDate->addDay();
            }
        }

        $calendarHolidays = array_values(array_map(
            static function (array $item): array {
                return [
                    'date'  => $item['date'],
                    'label' => implode(', ', $item['labels']),
                ];
            },
            $holidayDates
        ));

        $holidayRanges = $holidays->map(
            static function (Holiday $holiday): array {
                return [
                    'from'  => $holiday->from->format('Y-m-d'),
                    'to'    => $holiday->to->format('Y-m-d'),
                    'label' => $holiday->label,
                ];
            }
        )->values();

        return Inertia::render(
            'Org/HumanResources/Calendar',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Calendar'),
                'pageHead'    => [
                    'title'         => __('Calendar'),
                    'subNavigation' => $this->getCalendarSubNavigation(),
                ],
                'year'           => $year,
                'month'          => $month,
                'holidays'       => $calendarHolidays,
                'holidayRanges'  => $holidayRanges,
                'holidayYearPeriod' => $holidayYearPeriod,
                'allHolidayYears'   => $allHolidayYears,
                'defaultPeriod'     => $defaultPeriod,
            ]
        )->table($this->tableStructure());
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle();
    }


    public function getBreadcrumbs(): array
    {
        return array_merge(
            (new ShowHumanResourcesDashboard())->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.calendars.index',
                            'parameters' => array_values(request()->route()->originalParameters())
                        ],
                        'label' => __('Calendars'),
                        'icon'  => 'fal fa-calendar',
                    ],
                ]
            ]
        );
    }
}
