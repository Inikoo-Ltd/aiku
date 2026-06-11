<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\EmployeeContract\UI;

use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmployeeContracts extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesAuthorisation;

    public function handle(Employee $employee, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('notes', 'ILIKE', "%$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(EmployeeContract::class)
            ->where('employee_id', $employee->id)
            ->defaultSort('-start_date')
            ->allowedSorts(['start_date', 'end_date', 'annual_leave_days'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No contracts'),
                    'description' => __('Add a contract to track leave entitlements.'),
                    'count'       => 0,
                ])
                ->column(key: 'start_date', label: __('Start date'), canBeHidden: false, sortable: true)
                ->column(key: 'end_date', label: __('End date'), sortable: true)
                ->column(key: 'annual_leave_days', label: __('Annual leave'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'notes', label: __('Notes'))
                ->column(key: 'actions', label: '', canBeHidden: false);

            $table->defaultSort('-start_date');
        };
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($employee);
    }

    public function htmlResponse(LengthAwarePaginator $contracts, ActionRequest $request): Response
    {
        /** @var Employee $employee */
        $employee = $request->route('employee');

        return Inertia::render(
            'Org/HumanResources/EmployeeContracts',
            [
                'breadcrumbs' => $this->getBreadcrumbs($employee, $request->route()->originalParameters()),
                'title'       => __('Contracts'),
                'pageHead'    => [
                    'model'         => __('Employee'),
                    'title'         => $employee->contact_name,
                    'icon'          => ['title' => __('Contracts'), 'icon' => 'fal fa-file-contract'],
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Add contract'),
                            'route' => [
                                'name'       => 'grp.org.hr.employees.show.contracts.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'data' => \App\Http\Resources\HumanResources\EmployeeContractResource::collection($contracts)
                    ->additional(['meta' => ['parameters' => $request->route()->originalParameters()]])
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(Employee $employee, array $routeParameters): array
    {
        return array_merge(
            ShowEmployee::make()->getBreadcrumbs($employee, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.employees.show.contracts.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Contracts'),
                        'icon'  => 'fal fa-file-certificate',
                    ]
                ]
            ]
        );
    }
}
