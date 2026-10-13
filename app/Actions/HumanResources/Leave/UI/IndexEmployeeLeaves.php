<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\HumanResources\Employee\UI\GetEmployeeShowcase;
use App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance;
use App\Enums\HumanResources\Leave\LeaveColorEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsObject;

class IndexEmployeeLeaves
{
    use AsObject;

    public const string ALL_YEARS = 'all';

    public const string TABLE_NAME = 'grp.org.hr.employees.show.leaves';

    /**
     * @param  array{status?: string|null, year?: int|null}  $modelData
     */
    public function handle(Employee $employee, ?string $prefix = null, array $modelData = []): LengthAwarePaginator
    {
        $status = $modelData['status'] ?? null;
        $year   = $modelData['year'] ?? null;

        return QueryBuilder::for(Leave::class)
            ->where('leaves.employee_id', $employee->id)
            ->when($status, fn ($query) => $query->where('leaves.status', $status))
            ->when($year, fn ($query) => $query->whereYear('leaves.start_date', $year))
            ->with(['leaveType', 'media', 'coverEmployee:id,contact_name', 'approver:id,contact_name,username', 'approvalRecords.approver:id,contact_name,username'])
            ->orderByDesc('leaves.start_date')
            ->orderByDesc('leaves.id')
            ->withPaginator($prefix, tableName: self::TABLE_NAME)
            ->withQueryString();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $prefix): array
    {
        return [
            "{$prefix}_status" => ['sometimes', 'nullable', Rule::enum(LeaveStatusEnum::class)],
            "{$prefix}_year"   => ['sometimes', 'nullable', 'string', 'regex:/^('.self::ALL_YEARS.'|(19|20)\d{2})$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validatedData
     * @return array{status: string|null, year: int|null}
     */
    public function resolveFilters(array $validatedData, string $prefix): array
    {
        $status = $validatedData["{$prefix}_status"] ?? null;
        $year   = $validatedData["{$prefix}_year"] ?? null;

        return [
            'status' => $status instanceof LeaveStatusEnum ? $status->value : ($status ?: null),
            'year'   => match (true) {
                $year === self::ALL_YEARS => null,
                !$year                    => now()->year,
                default                   => (int) $year,
            },
        ];
    }

    /**
     * @param  array{status: string|null, year: int|null}  $filters
     * @return array<string, mixed>
     */
    public function getTabData(Employee $employee, string $prefix, array $filters, Request $request): array
    {
        $statistics = $this->statistics($employee);
        $inPeriod   = $filters['year'] ? $statistics->where('year', $filters['year'])->values() : $statistics;

        return [
            'summary'              => $this->summary($inPeriod),
            'by_type'              => $this->breakdownByType($inPeriod),
            'balance'              => GetEmployeeShowcase::make()->leaveBalanceData($employee),
            'leaves'               => $this->handle($employee, $prefix, $filters)
                ->through(fn (Leave $leave): array => [
                    ...LeaveResource::make($leave)->resolve($request),
                    'days' => RecalculateEmployeeLeaveBalance::deductionFor($leave),
                ]),
            'filters'              => [
                'status' => $filters['status'],
                'year'   => $filters['year'] ?? self::ALL_YEARS,
            ],
            'year_options'         => $statistics->pluck('year')
                ->push(now()->year)
                ->when($filters['year'], fn (Collection $years) => $years->push($filters['year']))
                ->map(fn ($year): int => (int) $year)
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
            'status_options'       => LeaveStatusEnum::labels(),
            'query_parameters'     => [
                'status'   => "{$prefix}_status",
                'year'     => "{$prefix}_year",
                'per_page' => "{$prefix}_perPage",
            ],
            'leave_requests_route' => [
                'name'       => 'grp.org.hr.leaves.index',
                'parameters' => ['organisation' => $employee->organisation->slug, 'filter' => ['global' => $employee->contact_name]],
            ],
        ];
    }

    /**
     * Every leave in a group shares its type, half day flag and duration, so the group's days are
     * the day value of one of its leaves times the number of leaves in it.
     *
     * @return Collection<int, array{year: int, status: string, type_key: string, type_label: string, type_color_hex: string, requests: int, days: float}>
     */
    public function statistics(Employee $employee): Collection
    {
        return Leave::query()
            ->where('employee_id', $employee->id)
            ->selectRaw('EXTRACT(YEAR FROM start_date)::int AS year, status, leave_type_id, type, is_half_day, duration_days, COUNT(*) AS requests')
            ->groupByRaw('EXTRACT(YEAR FROM start_date), status, leave_type_id, type, is_half_day, duration_days')
            ->with('leaveType')
            ->get()
            ->map(fn (Leave $group): array => [
                'year'           => (int) $group->getAttribute('year'),
                'status'         => $group->status->value,
                'type_key'       => $group->leave_type_id ? 'id:'.$group->leave_type_id : 'code:'.$group->type,
                'type_label'     => $group->leaveType?->name ?? (string) $group->type,
                'type_color_hex' => LeaveColorEnum::hexFor($group->leaveType?->color),
                'requests'       => (int) $group->getAttribute('requests'),
                'days'           => RecalculateEmployeeLeaveBalance::deductionFor($group) * (int) $group->getAttribute('requests'),
            ]);
    }

    /**
     * @param  Collection<int, array{status: string, requests: int, days: float}>  $statistics
     * @return array{total: int, approved: int, pending: int, rejected: int, days_taken: float}
     */
    public function summary(Collection $statistics): array
    {
        $requestsWith = fn (LeaveStatusEnum $status): int => (int) $statistics->where('status', $status->value)->sum('requests');

        return [
            'total'      => (int) $statistics->sum('requests'),
            'approved'   => $requestsWith(LeaveStatusEnum::APPROVED),
            'pending'    => $requestsWith(LeaveStatusEnum::PENDING),
            'rejected'   => $requestsWith(LeaveStatusEnum::REJECTED),
            'days_taken' => round((float) $statistics->where('status', LeaveStatusEnum::APPROVED->value)->sum('days'), 2),
        ];
    }

    /**
     * @param  Collection<int, array{status: string, type_key: string, type_label: string, type_color_hex: string, requests: int, days: float}>  $statistics
     * @return array<int, array{key: string, label: string, color_hex: string, requests: int, approved: int, approved_days: float}>
     */
    public function breakdownByType(Collection $statistics): array
    {
        return $statistics
            ->groupBy('type_key')
            ->map(function (Collection $groups, string $key): array {
                $approved = $groups->where('status', LeaveStatusEnum::APPROVED->value);

                return [
                    'key'           => $key,
                    'label'         => $groups->first()['type_label'],
                    'color_hex'     => $groups->first()['type_color_hex'],
                    'requests'      => (int) $groups->sum('requests'),
                    'approved'      => (int) $approved->sum('requests'),
                    'approved_days' => round((float) $approved->sum('days'), 2),
                ];
            })
            ->sortBy([['approved_days', 'desc'], ['requests', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }
}
