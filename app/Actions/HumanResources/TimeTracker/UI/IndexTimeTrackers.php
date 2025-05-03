<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 20:26:25 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker\UI;

use App\Actions\OrgAction;
use App\Http\Resources\HumanResources\ClockingsResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;

class IndexTimeTrackers extends OrgAction
{
    public function handle(Organisation|Workplace|ClockingMachine|Timesheet $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(TimeTracker::class);

        switch (class_basename($parent)) {
            case 'Organisation':
                $queryBuilder->where('time_trackers.organisation_id', $parent->id);
                break;
            case 'Workplace':
                $queryBuilder->where('time_trackers.workplace_id', $parent->id);
                break;
            case 'ClockingMachine':
                $queryBuilder->where('time_trackers.clocking_machine_id', $parent->id);
                break;
            default: //Timesheet
                $queryBuilder->where('time_trackers.timesheet_id', $parent->id);
                break;
        }

        return $queryBuilder
            ->defaultSort('time_trackers.starts_at')
            ->select(
                [
                    'starts_at',
                    'ends_at',
                    'duration',
                    'time_trackers.id',
                    'status'
                ]
            )
            ->allowedSorts(['starts_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(Organisation|Workplace|ClockingMachine|Timesheet $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title'       => __('no clockings'),
                        'description' => $this->canEdit ? __('Get started by creating a new clocking.') : null,
                        'count'       =>
                            class_basename($parent) == 'ClockingMachine' ? $parent->humanResourcesStats?->number_clockings : $parent->stats?->number_clockings,
                    ]
                )
                ->column(key: 'starts_at', label: __('clocked in'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ends_at', label: __('clocked out'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: 'status', type: 'icon')
                ->column(key: 'action', label: 'action', type: 'icon')
                ->defaultSort('starts_at');
        };
    }


    public function jsonResponse(LengthAwarePaginator $clockings): AnonymousResourceCollection
    {
        return ClockingsResource::collection($clockings);
    }

}
