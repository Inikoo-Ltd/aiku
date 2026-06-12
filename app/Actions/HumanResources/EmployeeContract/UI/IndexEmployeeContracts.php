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
use App\Http\Resources\HumanResources\EmployeeContractResource;
use App\Http\Resources\HumanResources\LeaveBalanceResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
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
            ->with('leaveBalance')
            ->defaultSort('-start_date')
            ->allowedSorts(['start_date', 'end_date', 'annual_leave_days'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function getUnlinkedBalances(Employee $employee): \Illuminate\Database\Eloquent\Collection
    {
        return EmployeeLeaveBalance::where('employee_id', $employee->id)
            ->whereNull('employee_contract_id')
            ->get();
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
                ->column(key: 'balance', label: __('Leave used'), canBeHidden: false)
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

        $unlinkedBalances = $this->getUnlinkedBalances($employee);

        return Inertia::render(
            'Org/HumanResources/EmployeeContracts',
            [
                'breadcrumbs'      => $this->getBreadcrumbs($employee, $request->route()->originalParameters()),
                'title'            => __('Contracts'),
                'pageHead'         => [
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
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ]
                    ],
                ],
                'data'             => EmployeeContractResource::collection($contracts)
                    ->additional(['meta' => ['parameters' => $request->route()->originalParameters()]]),
                'unlinked_balances' => LeaveBalanceResource::collection($unlinkedBalances)->additional([
                    'meta' => [
                        'link_route' => 'grp.models.employee.leave_balance.link',
                        'contracts'  => $employee->contracts()
                            ->orderByDesc('start_date')
                            ->get(['id', 'start_date', 'end_date', 'annual_leave_days'])
                            ->map(fn (EmployeeContract $c) => [
                                'id'                => $c->id,
                                'label'             => $c->start_date->format('d M Y').' – '.($c->end_date?->format('d M Y') ?? __('Present')),
                                'annual_leave_days' => $c->annual_leave_days,
                            ]),
                    ],
                ]),
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
