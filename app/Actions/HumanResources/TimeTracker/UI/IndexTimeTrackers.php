<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 20:26:25 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker\UI;

use App\Actions\OrgAction;
use App\Http\Resources\HumanResources\TimeTrackersResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;

class IndexTimeTrackers extends OrgAction
{
    private function ensureRequiredColumnsVisible(?string $prefix = null): void
    {
        $columnsQueryKey = $prefix ? $prefix.'_columns' : 'columns';
        $requestedColumns = request()->query($columnsQueryKey);

        if (!$requestedColumns) {
            return;
        }

        $columns = is_string($requestedColumns)
            ? array_filter(explode(',', $requestedColumns))
            : (is_array($requestedColumns) ? $requestedColumns : []);

        if (empty($columns)) {
            return;
        }

        $requiredColumns = ['starts_at', 'ends_at', 'status'];
        $columns = array_values(array_unique(array_merge($columns, $requiredColumns)));

        request()->query->set($columnsQueryKey, implode(',', $columns));
    }

    public function handle(Organisation|Workplace|ClockingMachine|Timesheet $parent, $prefix = null): LengthAwarePaginator
    {
        $this->ensureRequiredColumnsVisible($prefix);

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(TimeTracker::class)
            ->leftJoin('timesheets', 'time_trackers.timesheet_id', 'timesheets.id')
            ->leftJoin('organisations', 'timesheets.organisation_id', 'organisations.id');

        switch (class_basename($parent)) {
            case 'Organisation':
                $queryBuilder->where('timesheets.organisation_id', $parent->id);
                break;
            case 'Workplace':
                $queryBuilder->where('time_trackers.workplace_id', $parent->id);
                break;
            case 'ClockingMachine':
                $queryBuilder
                    ->leftJoin('clockings as start_clockings', 'time_trackers.start_clocking_id', 'start_clockings.id')
                    ->leftJoin('clockings as end_clockings', 'time_trackers.end_clocking_id', 'end_clockings.id')
                    ->where(function ($query) use ($parent) {
                        $query
                            ->where('start_clockings.clocking_machine_id', $parent->id)
                            ->orWhere('end_clockings.clocking_machine_id', $parent->id);
                    });
                break;
            default: //Timesheet
                $queryBuilder->where('time_trackers.timesheet_id', $parent->id);
                break;
        }

        return $queryBuilder
            ->defaultSort('time_trackers.starts_at')
            ->select(
                [
                    'time_trackers.starts_at',
                    'time_trackers.ends_at',
                    'time_trackers.duration',
                    'time_trackers.id',
                    'time_trackers.status',
                    'organisations.code as organisation_code'
                ]
            )
            ->allowedSorts(['time_trackers.starts_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(Organisation|Workplace|ClockingMachine|Timesheet $parent, ?array $modelOperations = null, $prefix = null, bool $showActions = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $showActions) {
            $this->ensureRequiredColumnsVisible($prefix);

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
                ->column(key: 'starts_at', label: __('Clocked in'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ends_at', label: __('Clocked out'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: 'Status', type: 'icon');

            if ($showActions) {
                $table->column(key: 'action', label: 'Action', type: 'icon');
            }

            $table->defaultSort('starts_at');
        };
    }


    public function jsonResponse(...$args)
    {
        $timeTrackers = $args[0] ?? collect();

        return TimeTrackersResource::collection($timeTrackers);
    }

}
