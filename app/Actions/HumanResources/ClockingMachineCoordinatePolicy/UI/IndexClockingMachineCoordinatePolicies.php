<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicy\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexClockingMachineCoordinatePolicies extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(ClockingMachine $clockingMachine, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ClockingMachineCoordinatePolicy::class)
            ->where('organisation_id', $clockingMachine->organisation_id)
            ->where(function ($query) use ($clockingMachine) {
                $query->whereNull('clocking_machine_id')
                    ->orWhere('clocking_machine_id', $clockingMachine->id);
            })
            ->with('rules')
            ->withCount('rules')
            ->defaultSort('-created_at')
            ->allowedSorts(['id', 'scope_type', 'scope_id', 'mode', 'is_active', 'rules_count', 'start_at', 'end_at', 'created_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->column(key: 'id', label: __('ID'), canBeHidden: false, sortable: true)
                ->column(key: 'scope_type', label: __('Scope'), canBeHidden: false, sortable: true)
                ->column(key: 'scope_id', label: __('Scope ID'), canBeHidden: false, sortable: true)
                ->column(key: 'mode', label: __('Mode'), canBeHidden: false, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: false, sortable: true)
                ->column(key: 'rules_count', label: __('Rules'), canBeHidden: false, sortable: true)
                ->column(key: 'start_at', label: __('Start At'), canBeHidden: true, sortable: true)
                ->column(key: 'end_at', label: __('End At'), canBeHidden: true, sortable: true)
                ->column(key: 'actions', label: __('Actions'), canBeHidden: false, sortable: false, searchable: false)
                ->defaultSort('id');
        };
    }

}
