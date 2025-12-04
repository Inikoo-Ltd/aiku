<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 03 Dec 2025 10:32:41 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\SysAdmin\ScheduledTaskResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\ScheduledTaskLog;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSysAdminScheduledTasks
{
    use AsAction;

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('task_name', 'like', '%' . $value . '%')
                    ->orWhere('task_type', 'like', '%' . $value . '%')
                    ->orWhere('status', 'like', '%' . $value . '%');
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ScheduledTaskLog::class)
            ->defaultSort('-started_at')
            ->allowedSorts(['task_name', 'task_type', 'started_at', 'finished_at', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $scheduledTasks, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/ScheduledTasks',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('Scheduled Tasks'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clock'],
                        'title' => __('Scheduled Tasks'),
                    ],
                    'title' => __('Scheduled Tasks'),
                ],
                'scheduledTasks' => ScheduledTaskResource::collection($scheduledTasks),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.scheduled-tasks.index',
                        ],
                        'label' => __('Scheduled Tasks'),
                    ]
                ]
            ]
        );
    }

    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'task_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'task_type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scheduled_at', label: __('Scheduled'), canBeHidden: false)
                ->column(key: 'started_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'finished_at', label: __('Finished'), canBeHidden: false)
                ->column(key: 'duration', label: __('Duration'), canBeHidden: false, align: 'right')
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'error_message', label: __('Error Message'), canBeHidden: false)
                ->defaultSort('-started_at');
        };
    }
}
