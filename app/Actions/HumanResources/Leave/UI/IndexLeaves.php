<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Http\Resources\HumanResources\LeaveBalanceResource;
use App\Http\Resources\HumanResources\LeaveResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaves extends OrgAction
{
    public function handle(Employee $employee, ?string $prefix = null): array
    {
        $prefix = $prefix ?? 'leaves';

        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('reason', 'like', "%$value%");
            });
        });

        $statusFilter = AllowedFilter::callback('status', function ($query, $value) {
            $query->where('status', $value);
        });

        $typeFilter = AllowedFilter::callback('type', function ($query, $value) {
            $query->where('type', $value);
        });

        $queryBuilder = QueryBuilder::for(Leave::class)
            ->where('employee_id', $employee->id)
            ->with(['media'])
            ->allowedFilters([$globalSearch, $statusFilter, $typeFilter])
            ->allowedSorts(['start_date', 'end_date', 'duration_days', 'created_at'])
            ->defaultSort('-created_at');

        $leaves = $queryBuilder->paginate(request()->input($prefix . 'perPage', 10))
            ->withQueryString();

        $balance = EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'year'        => now()->year,
            ],
            [
                'group_id'     => $employee->group_id,
                'annual_days'  => 14,
                'medical_days' => 14,
                'unpaid_days'  => 0,
            ]
        );

        return [
            'leaves'  => $leaves,
            'balance' => $balance,
        ];
    }

    public function jsonResponse(array $data): AnonymousResourceCollection
    {
        return LeaveResource::collection($data['leaves']);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/LeaveManagement',
            [
                'title'    => __('Leave Management'),
                'leaves'   => LeaveResource::collection($data['leaves']),
                'balance'  => LeaveBalanceResource::make($data['balance']),
                'type_options' => LeaveTypeEnum::labels(),
                'status_options' => LeaveStatusEnum::labels(),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'start_date', label: __('Start Date'), sortable: true)
                ->column(key: 'end_date', label: __('End Date'), sortable: true)
                ->column(key: 'type_label', label: __('Type'))
                ->column(key: 'duration_days', label: __('Days'))
                ->column(key: 'status_label', label: __('Status'))
                ->column(key: 'reason', label: __('Reason'))
                ->column(key: 'actions', label: '')
                ->defaultSort('start_date');
        };
    }

    public function asController(ActionRequest $request): array
    {
        $user = Auth::user();
        $employee = $user?->employees->first();

        if (!$employee) {
            abort(404, 'Employee not found');
        }

        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee);
    }
}
