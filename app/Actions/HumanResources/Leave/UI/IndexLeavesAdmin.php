<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeavesAdmin extends OrgAction
{
    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $prefix = $prefix ?? 'leaves';

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

        $typeFilter = AllowedFilter::callback('type', function ($query, $value) {
            $query->where('type', $value);
        });

        $queryBuilder = QueryBuilder::for(Leave::class)
            ->where('organisation_id', $organisation->id)
            ->with(['media', 'employee'])
            ->allowedFilters([$globalSearch, $statusFilter, $typeFilter])
            ->allowedSorts(['start_date', 'end_date', 'created_at', 'employee_name'])
            ->defaultSort('-created_at');

        return $queryBuilder->paginate(request()->input($prefix . 'perPage', 15))
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $leaves): AnonymousResourceCollection
    {
        return LeaveResource::collection($leaves);
    }

    public function htmlResponse(LengthAwarePaginator $leaves, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/LeaveAdmin',
            [
                'title' => __('Leave Requests'),
                'breadcrumbs' => $this->getBreadcrumbs(),
                'pageHead' => [
                    'title' => __('Leave Requests'),
                    'icon' => ['fal', 'fa-calendar-minus'],
                ],
                'leaves' => LeaveResource::collection($leaves),
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
                ->column(key: 'employee_name', label: __('Employee'), sortable: true)
                ->column(key: 'type_label', label: __('Type'))
                ->column(key: 'start_date', label: __('Start Date'), sortable: true)
                ->column(key: 'end_date', label: __('End Date'), sortable: true)
                ->column(key: 'duration_days', label: __('Days'))
                ->column(key: 'status_label', label: __('Status'))
                ->column(key: 'reason', label: __('Reason'))
                ->column(key: 'attachments', label: __('Attachments'))
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
                    'label' => __('Leave Requests'),
                    'route' => ['name' => 'grp.org.hr.leaves.index', 'parameters' => [$this->organisation->slug]]
                ]
            ]
        ];
    }
}
