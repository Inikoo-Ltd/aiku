<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicy\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexClockingMachineCoordinatePolicies extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(ClockingMachine $clockingMachine, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function (Builder $query, string $value) {
            $organisationIds = Organisation::query()
                ->whereStartWith('name', $value)
                ->pluck('id')
                ->all();

            $employeeIdsByContactName = Employee::query()
                ->whereStartWith('contact_name', $value)
                ->pluck('id')
                ->all();

            $employeeIdsBySlug = Employee::query()
                ->whereStartWith('slug', $value)
                ->pluck('id')
                ->all();

            $employeeIds = array_values(array_unique([...$employeeIdsByContactName, ...$employeeIdsBySlug]));

            $query->where(function (Builder $query) use ($value, $organisationIds, $employeeIds) {
                $query->whereStartWith('clocking_machine_coordinate_policies.scope_type', $value)
                    ->orWhere(function (Builder $query) use ($organisationIds) {
                        if (count($organisationIds) === 0) {
                            $query->whereRaw('1 = 0');
                            return;
                        }

                        $query->where('clocking_machine_coordinate_policies.scope_type', 'organisation')
                            ->whereIn('clocking_machine_coordinate_policies.scope_id', $organisationIds);
                    })
                    ->orWhere(function (Builder $query) use ($employeeIds) {
                        if (count($employeeIds) === 0) {
                            $query->whereRaw('1 = 0');
                            return;
                        }

                        $query->where('clocking_machine_coordinate_policies.scope_type', 'employee')
                            ->whereIn('clocking_machine_coordinate_policies.scope_id', $employeeIds);
                    });
            });
        });

        $paginator = QueryBuilder::for(ClockingMachineCoordinatePolicy::class)
            ->where('organisation_id', $clockingMachine->organisation_id)
            ->where(function ($query) use ($clockingMachine) {
                $query->whereNull('clocking_machine_id')
                    ->orWhere('clocking_machine_id', $clockingMachine->id);
            })
            ->with('rules')
            ->withCount('rules')
            ->defaultSort('-created_at')
            ->allowedSorts(['id', 'scope_type', 'scope_id', 'mode', 'is_active', 'rules_count', 'start_at', 'end_at', 'created_at'])
            ->allowedFilters([$globalSearch, 'scope_type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $collection = $paginator->getCollection();

        $organisationIds = $collection
            ->where('scope_type', 'organisation')
            ->pluck('scope_id')
            ->filter()
            ->unique()
            ->values();

        $employeeIds = $collection
            ->where('scope_type', 'employee')
            ->pluck('scope_id')
            ->filter()
            ->unique()
            ->values();

        $organisationNames = Organisation::query()
            ->whereIn('id', $organisationIds)
            ->get()
            ->pluck('name', 'id');

        $employeeNames = Employee::query()
            ->whereIn('id', $employeeIds)
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => ($employee->contact_name ?: $employee->slug)]);

        $offset = max(0, ($paginator->currentPage() - 1) * $paginator->perPage());

        $collection->values()->each(function (ClockingMachineCoordinatePolicy $policy, int $index) use ($offset, $organisationNames, $employeeNames) {
            $scopeName = match ($policy->scope_type) {
                'organisation' => $organisationNames->get($policy->scope_id),
                'employee'     => $employeeNames->get($policy->scope_id),
                default        => null,
            };

            $policy->setAttribute('number', $offset + $index + 1);
            $policy->setAttribute('scope_name', $scopeName ?: (string) $policy->scope_id);
        });

        return $paginator;
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('policy'), __('policies')])
                ->column(key: 'number', label: '#', canBeHidden: false)
                ->column(key: 'scope_type', label: __('Scope'), canBeHidden: false, sortable: true)
                ->column(key: 'scope_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'mode', label: __('Mode'), canBeHidden: false, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: false, sortable: true)
                ->column(key: 'rules_count', label: __('Rules'), canBeHidden: false, sortable: true)
                ->column(key: 'start_at', label: __('Start At'), sortable: true)
                ->column(key: 'end_at', label: __('End At'), sortable: true)
                ->column(key: 'actions', label: __('Actions'), canBeHidden: false)
                ->defaultSort('id');
        };
    }

}
