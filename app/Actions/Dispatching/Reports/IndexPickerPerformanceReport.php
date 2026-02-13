<?php

namespace App\Actions\Dispatching\Reports;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Enums\UI\Dispatching\PerformanceReportTabsEnum;
use App\Http\Resources\Dispatching\Reports\PerformanceReportResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPickerPerformanceReport extends OrgAction
{
    private int $records;

    public function handle(Organisation $organisation, $prefix = null, $dateFilter = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('users.contact_name', 'like', "%{$value}%");
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        // Subquery to get the oldest and newest picking dates per picker
        $pickingDateRangeSubQuery = DB::table('pickings')
            ->select(
                'pickings.picker_user_id',
                DB::raw('MIN(DATE(pickings.created_at)) as first_picking_date'),
                DB::raw('MAX(DATE(pickings.created_at)) as last_picking_date')
            )
            ->join('delivery_notes', 'pickings.delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_notes.organisation_id', $organisation->id);

        // Subquery for metrics picking
        $pickingsSubQuery = DB::table('pickings')
            ->select(
                'pickings.picker_user_id as picker_id',
                DB::raw('COUNT(DISTINCT pickings.delivery_note_id) as deliveries'),
                DB::raw('SUM(pickings.quantity) as items'),
                DB::raw("COUNT(DISTINCT CONCAT(pickings.delivery_note_id, '-', pickings.org_stock_id)) as dp")
            )
            ->join('delivery_notes', 'pickings.delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_notes.organisation_id', $organisation->id);

        $this->records = QueryBuilder::for(User::class)
            ->joinSub($pickingsSubQuery->clone()->groupBy('pickings.picker_user_id'), 'pickings_metrics', function ($join) {
                $join->on('users.id', '=', 'pickings_metrics.picker_id');
            })
            ->distinct()
            ->count('users.contact_name');

        if ($dateFilter && !empty($dateFilter['created_at'])) {
            $raw = $dateFilter['created_at'];
            [$start, $end] = explode('-', $raw);
            $startDate = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $pickingsSubQuery->whereBetween('pickings.created_at', [$startDate, $endDate]);
            $pickingDateRangeSubQuery->whereBetween('pickings.created_at', [$startDate, $endDate]);
        }

        $pickingsSubQuery->groupBy('pickings.picker_user_id');
        $pickingDateRangeSubQuery->groupBy('pickings.picker_user_id');

        // Subquery for timesheet - only count working hours from the first to the last picking date
        $timesheetsSubQuery = DB::table('timesheets')
            ->select(
                'timesheets.subject_id as employee_id',
                DB::raw('SUM(timesheets.working_duration) / 3600 as hours')
            )
            ->join('employees', function ($join) use ($organisation) {
                $join->on('timesheets.subject_id', '=', 'employees.id')
                    ->where('employees.organisation_id', $organisation->id)
                    ->whereNull('employees.employment_end_at');
            })
            ->join('users', 'employees.contact_name', '=', 'users.contact_name')
            ->joinSub($pickingDateRangeSubQuery, 'picking_dates', function ($join) {
                $join->on('users.id', '=', 'picking_dates.picker_user_id');
            })
            ->where('timesheets.subject_type', (new Employee())->getMorphClass())
            ->where('timesheets.organisation_id', $organisation->id)
            ->whereRaw('DATE(timesheets.date) BETWEEN picking_dates.first_picking_date AND picking_dates.last_picking_date')
            ->groupBy('timesheets.subject_id');

        $queryBuilder = QueryBuilder::for(User::class)
            ->joinSub($pickingsSubQuery, 'pickings_metrics', function ($join) {
                $join->on('users.id', '=', 'pickings_metrics.picker_id');
            })
            ->leftJoin('employees', function ($join) use ($organisation) {
                $join->on('users.contact_name', '=', 'employees.contact_name')
                    ->where('employees.organisation_id', $organisation->id)
                    ->whereNull('employees.employment_end_at');
            })
            ->leftJoinSub($timesheetsSubQuery, 'timesheets_metrics', function ($join) {
                $join->on('employees.id', '=', 'timesheets_metrics.employee_id');
            })
            ->select(
                DB::raw('MAX(users.id) as id'),
                'users.contact_name as name',
                DB::raw('SUM(pickings_metrics.deliveries) as deliveries'),
                DB::raw('SUM(pickings_metrics.items) as items'),
                DB::raw('SUM(pickings_metrics.dp) as dp'),
                DB::raw('SUM(COALESCE(timesheets_metrics.hours, 0)) as hours'),
                DB::raw('CASE WHEN (SUM(COALESCE(timesheets_metrics.hours, 0)) > 0) THEN (SUM(pickings_metrics.dp) / SUM(COALESCE(timesheets_metrics.hours, 0))) ELSE 0 END as dp_per_hour'),
                DB::raw('0 as issues'), // Placeholder
                DB::raw('0 as issues_percentage'), // Placeholder
                DB::raw('0 as cartons'), // Placeholder
                DB::raw('0 as bonus'), // Placeholder
                DB::raw('0 as salary'), // Placeholder
                DB::raw('0 as bonus_net'), // Placeholder
                // DB::raw("COALESCE(CAST(employees.data->'salary'->>'hourly_rate' AS NUMERIC), 0) * COALESCE(timesheets_metrics.hours, 0) as salary"),
                // DB::raw("0 - (COALESCE(CAST(employees.data->'salary'->>'hourly_rate' AS NUMERIC), 0) * COALESCE(timesheets_metrics.hours, 0)) as bonus_net")
            )
            ->groupBy('users.contact_name');

        $totalDpQuery = DB::table('pickings')
            ->join('delivery_notes', 'pickings.delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_notes.organisation_id', $organisation->id);

        if ($dateFilter && !empty($dateFilter['created_at'])) {
            $raw = $dateFilter['created_at'];
            [$start, $end] = explode('-', $raw);
            $startDate = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');
            $totalDpQuery->whereBetween('pickings.created_at', [$startDate, $endDate]);
        }

        $totalDp = $totalDpQuery->distinct()->count(DB::raw("CONCAT(pickings.delivery_note_id, '-', pickings.org_stock_id)"));

        $queryBuilder->addSelect(DB::raw($totalDp > 0 ? "(SUM(pickings_metrics.dp) / $totalDp) * 100 as dp_percentage" : '0 as dp_percentage'));

        return $queryBuilder
            ->allowedSorts(['name', 'deliveries', 'items', 'dp', 'dp_percentage', 'hours', 'dp_per_hour'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['created_at'])
            ->defaultSort('name')
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
                ->withEmptyState(
                    [
                        'title'       => __('No Picker Performance Data'),
                        'description' => __('No data found for the selected period.'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['created_at']);

            $table->column(key: 'name', label: 'Name', sortable: true);
            $table->column(key: 'deliveries', label: 'Deliveries', sortable: true, type: 'number');
            $table->column(key: 'items', label: 'Items', sortable: true, type: 'number');
            $table->column(key: 'hours', label: 'Hours', sortable: true, type: 'number');

            if ($prefix == PerformanceReportTabsEnum::OVERVIEW->value) {
                $table->column(key: 'dp', label: 'DP', sortable: true, type: 'number');
                $table->column(key: 'dp_percentage', label: 'DP %', sortable: true, type: 'percentage');
                $table->column(key: 'dp_per_hour', label: 'DP/Hour', sortable: true, type: 'number');
                $table->column(key: 'issues', label: 'Issues', sortable: true, type: 'number');
                $table->column(key: 'issues_percentage', label: 'Issues %', sortable: true, type: 'percentage');
            }

            if ($prefix == PerformanceReportTabsEnum::BONUS->value) {
                $table->column(key: 'cartons', label: 'Cartons', sortable: true, type: 'number');
                $table->column(key: 'bonus', label: 'Bonus', sortable: true, type: 'currency');
                $table->column(key: 'salary', label: 'Salary', sortable: true, type: 'currency');
                $table->column(key: 'bonus_net', label: 'Bonus Net', sortable: true, type: 'currency');
            }
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(PerformanceReportTabsEnum::values());
        $dateFilter = $request->input('between', []);

        return $this->handle($organisation, PerformanceReportTabsEnum::OVERVIEW->value, $dateFilter);
    }

    public function inReports(Organisation $organisation): int
    {
        $this->handle($organisation);

        return $this->records;
    }

    public function htmlResponse(LengthAwarePaginator $data, ActionRequest $request): Response
    {
        $dateFilter = $request->input('between', []);

        return Inertia::render(
            'Org/Reports/PerformanceReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Picker Performance Report'),
                'pageHead'    => [
                    'title' => __('Picker Performance Report'),
                    'icon'  => [
                        'title' => __('Picker Performance'),
                        'icon'  => 'fal fa-dolly'
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PerformanceReportTabsEnum::navigation(),
                ],

                PerformanceReportTabsEnum::OVERVIEW->value => $this->tab == PerformanceReportTabsEnum::OVERVIEW->value
                    ? fn () => PerformanceReportResource::collection($data)
                    : Inertia::lazy(fn () => PerformanceReportResource::collection($data)),

                PerformanceReportTabsEnum::BONUS->value => $this->tab == PerformanceReportTabsEnum::BONUS->value
                    ? fn () => PerformanceReportResource::collection($this->handle($this->organisation, PerformanceReportTabsEnum::BONUS->value, $dateFilter))
                    : Inertia::lazy(fn () => PerformanceReportResource::collection($this->handle($this->organisation, PerformanceReportTabsEnum::BONUS->value, $dateFilter))),
            ]
        )->table($this->tableStructure(PerformanceReportTabsEnum::OVERVIEW->value))
            ->table($this->tableStructure(PerformanceReportTabsEnum::BONUS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-dolly',
                        'label' => __('Picker Performance'),
                        'route' => [
                            'name'       => 'grp.org.reports.picker-performance',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
