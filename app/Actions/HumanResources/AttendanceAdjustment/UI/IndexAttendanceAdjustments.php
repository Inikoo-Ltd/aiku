<?php

namespace App\Actions\HumanResources\AttendanceAdjustment\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Employee;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAttendanceAdjustments extends OrgAction
{
    public function handle(Employee $employee, ?string $prefix = null): LengthAwarePaginator
    {
        $prefix = $prefix ?? 'adjustments';

        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('reason', 'like', "%$value%");
            });
        });

        $statusFilter = AllowedFilter::callback('status', function ($query, $value) {
            $query->where('status', $value);
        });

        $queryBuilder = QueryBuilder::for(AttendanceAdjustment::class)
            ->where('employee_id', $employee->id)
            ->with(['media'])
            ->allowedFilters([$globalSearch, $statusFilter])
            ->allowedSorts(['date', 'created_at'])
            ->defaultSort('-date');

        return $queryBuilder->paginate(request()->input($prefix . 'perPage', 10))
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $adjustments): AnonymousResourceCollection
    {
        return AttendanceAdjustmentResource::collection($adjustments);
    }

    public function htmlResponse(LengthAwarePaginator $adjustments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/AttendanceAdjustmentManagement',
            [
                'title'         => __('Attendance Adjustments'),
                'adjustments'   => AttendanceAdjustmentResource::collection($adjustments),
                'status_options' => AttendanceAdjustmentStatusEnum::labels(),
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
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'original_times', label: __('Original Times'))
                ->column(key: 'requested_times', label: __('Requested Times'))
                ->column(key: 'status_label', label: __('Status'))
                ->column(key: 'reason', label: __('Reason'))
                ->column(key: 'actions', label: '')
                ->defaultSort('-date');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
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
