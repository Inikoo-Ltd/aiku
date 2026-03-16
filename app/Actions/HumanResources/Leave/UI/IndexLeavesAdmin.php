<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
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
    use WithLeaveSubNavigation;

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
            ->with(['employee'])
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
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-calendar-minus'],
                        'title' => __('Human resources')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-calendar-minus'],
                        'title' => __('Leave')
                    ],
                    'title'         => __('Leave Requests'),
                    'subNavigation' => $this->getLeaveSubNavigation($request),
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
                ->column(
                    key: 'employee_name',
                    label: __('Employee'),
                    canBeHidden: false,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'type_label',
                    label: __('Type'),
                    canBeHidden: false
                )
                ->column(
                    key: 'start_date',
                    label: __('Start Date'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'end_date',
                    label: __('End Date'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'duration_days',
                    label: __('Days'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'status',
                    label: __('Status'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'reason',
                    label: __('Reason'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'attachments',
                    label: __('Attachments'),
                    canBeHidden: false
                )
                ->column(
                    key: 'actions',
                    label: __('Options'),
                    canBeHidden: false
                )
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Leave Requests'),
                        'icon'  => 'fal fa-calendar-minus',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leaves.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
