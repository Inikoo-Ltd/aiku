<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\HumanResources\Leave\UpdateLeaveCover;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesSectionAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveCoversResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaveCovers extends OrgAction
{
    use WithHumanResourcesSectionAuthorisation;

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('leaves.employee_name', $value);
        });

        return QueryBuilder::for(Leave::class)
            ->where('organisation_id', $organisation->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(fn ($query) => $query->whereDate('end_date', '>=', now()->toDateString())->orWhereNotNull('cover_employee_id'))
            ->with(['leaveType:id,name', 'coverEmployee:id,contact_name'])
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['employee_name', 'start_date', 'end_date'])
            ->defaultSort('-start_date')
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No absences to cover')])
                ->withLabelRecord([__('absence'), __('absences')])
                ->column(key: 'employee_name', label: __('Employee'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type_label', label: __('Type'), canBeHidden: false)
                ->column(key: 'start_date', label: __('From'), canBeHidden: false, sortable: true)
                ->column(key: 'end_date', label: __('To'), canBeHidden: false, sortable: true)
                ->column(key: 'covered_by', label: __('Covered by'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('-start_date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $leaves, ActionRequest $request): Response
    {
        $title = __('Absence cover');

        $employeeOptions = $this->canEdit
            ? Employee::where('organisation_id', $this->organisation->id)
                ->where('state', EmployeeStateEnum::WORKING)
                ->orderBy('contact_name')
                ->pluck('contact_name', 'id')
            : collect();

        return Inertia::render(
            'Org/HumanResources/LeaveCovers',
            [
                'title'            => $title,
                'breadcrumbs'      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'         => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-friends'],
                        'title' => $title,
                    ],
                    'model' => __('Human Resources'),
                    'title' => $title,
                ],
                'data'             => LeaveCoversResource::collection($leaves),
                'can_edit'         => $this->canEdit,
                'employee_options' => $employeeOptions,
                'employee_leave_periods' => UpdateLeaveCover::approvedLeavePeriods($employeeOptions->keys(), today()),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.covers.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Absence cover'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
