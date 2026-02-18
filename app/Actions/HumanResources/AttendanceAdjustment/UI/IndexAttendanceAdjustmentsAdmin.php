<?php

namespace App\Actions\HumanResources\AttendanceAdjustment\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAttendanceAdjustmentsAdmin extends OrgAction
{
    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $prefix = $prefix ?? 'adjustments';

        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('employee_name', 'like', "%$value%")
                    ->orWhere('reason', 'like', "%$value%");
            });
        });

        $statusFilter = AllowedFilter::callback('status', function ($query, $value) {
            $query->where('status', $value);
        });

        $queryBuilder = QueryBuilder::for(AttendanceAdjustment::class)
            ->where('organisation_id', $organisation->id)
            ->with(['media', 'employee'])
            ->allowedFilters([$globalSearch, $statusFilter])
            ->allowedSorts(['date', 'created_at', 'employee_name'])
            ->defaultSort('-created_at');

        return $queryBuilder->paginate(request()->input($prefix . 'perPage', 15))
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $adjustments): AnonymousResourceCollection
    {
        return AttendanceAdjustmentResource::collection($adjustments);
    }

    public function htmlResponse(LengthAwarePaginator $adjustments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/AttendanceAdjustmentAdmin',
            [
                'title' => __('Attendance Adjustments'),
                'breadcrumbs' => $this->getBreadcrumbs(),
                'pageHead' => [
                    'title' => __('Attendance Adjustments'),
                    'icon' => ['fal', 'fa-edit'],
                ],
                'adjustments' => AttendanceAdjustmentResource::collection($adjustments),
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
                ->column(key: 'employee_name', label: __('Employee'), sortable: true)
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'original_times', label: __('Original Times'))
                ->column(key: 'requested_times', label: __('Requested Times'))
                ->column(key: 'status_label', label: __('Status'))
                ->column(key: 'reason', label: __('Reason'))
                ->column(key: 'actions', label: '')
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(): array
    {
        return [
            [
                'type' => 'simple',
                'simple' => [
                    'label' => __('Attendance Adjustments'),
                    'route' => ['name' => 'grp.org.hr.adjustments.index', 'parameters' => [$this->organisation->slug]]
                ]
            ]
        ];
    }
}
