<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\OrgAction;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use App\InertiaTable\InertiaTable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexWorkSchedules extends OrgAction
{
    public function handle(Organisation $organisation, $type = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('work_schedules.name', $value);
        });

        $queryBuilder = QueryBuilder::for(WorkSchedule::class)
            ->where('work_schedules.schedulable_type', 'Organisation')
            ->where('work_schedules.schedulable_id', $organisation->id)
            ->when($type, fn ($q) => $q->where('work_schedules.type', $type))
            ->defaultSort('-work_schedules.created_at')
            ->select([
                'work_schedules.id',
                'work_schedules.name',
                'work_schedules.type',
                'work_schedules.is_active',
                'work_schedules.created_at',
            ])
            ->allowedSorts(['name', 'type', 'is_active', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        return $queryBuilder->paginate();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-admin.' . $this->organisation->id
        ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, 'shift');
    }

    public function jsonResponse(LengthAwarePaginator $schedules): AnonymousResourceCollection
    {
        return response()->json($schedules);
    }

    public function tableStructure(?array $modelOperations = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations) {
            $table
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __('No shift schedules'),
                        'description' => __('Get started by creating a new shift schedule.'),
                        'action' => [
                            'type' => 'button',
                            'style' => 'create',
                            'tooltip' => __('New shift schedule'),
                            'label' => __('shift schedule'),
                            'route' => [
                                'name' => 'grp.org.hr.shift_schedules.store',
                                'parameters' => [$this->organisation->slug]
                            ]
                        ]
                    ]
                )
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true)
                ->column(key: 'is_active', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: __('Actions'))
                ->defaultSort('-created_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $schedules, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/ShiftSchedules',
            [
                'breadcrumbs' => [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => ['grp.org.hr.show', $this->organisation->slug],
                            'label' => __('HR'),
                            'icon' => 'fal fa-bars'
                        ],
                    ],
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => 'grp.org.hr.shift_schedules.index',
                            'label' => __('Shift Schedules'),
                            'icon' => 'fal fa-clock'
                        ],
                    ],
                ],
                'title' => __('Shift Schedules'),
                'pageHead' => [
                    'title' => __('Shift Schedules'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'key' => 'btn-create',
                            'style' => 'create',
                            'label' => __('Create Shift')
                        ]
                    ]
                ],
                'data' => $schedules,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return [
            [
                'type' => 'simple',
                'simple' => [
                    'route' => ['grp.org.hr.show', $this->organisation->slug],
                    'label' => __('HR'),
                    'icon' => 'fal fa-bars'
                ],
            ],
            [
                'type' => 'simple',
                'simple' => [
                    'route' => 'grp.org.hr.shift_schedules.index',
                    'label' => __('Shift Schedules'),
                    'icon' => 'fal fa-clock'
                ],
            ],
        ];
    }
}
