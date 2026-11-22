<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Http\Resources\Inventory\WarehouseStaffTasksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every task the warehouse was asked to do about this warehouse's locations or this
 * organisation's SKOs, shown to everyone who opens inventory with how long it has waited, so
 * a count asked for months ago stays in plain sight until someone does it (HELP-4868).
 */
class IndexWarehouseStaffTasks extends OrgAction
{
    use WithInventoryAuthorisation;

    public const string DEPARTMENT = 'warehouse';

    public static function warehouseTasks(Warehouse $warehouse): Builder
    {
        return StaffTask::query()
            ->where('staff_tasks.group_id', $warehouse->group_id)
            ->where('staff_tasks.department', self::DEPARTMENT)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('staff_tasks.model_type', 'Location')
                    ->whereIn('staff_tasks.model_id', DB::table('locations')->where('warehouse_id', $warehouse->id)->select('id')))
                ->orWhere(fn (Builder $query) => $query->where('staff_tasks.model_type', 'OrgStock')
                    ->whereIn('staff_tasks.model_id', DB::table('org_stocks')->where('organisation_id', $warehouse->organisation_id)->select('id'))));
    }

    /**
     * @return array{open: int, overdue: int, oldest_days: int|null, done_last_30_days: int}
     */
    public static function openSummary(Warehouse $warehouse): array
    {
        $open = self::warehouseTasks($warehouse)->open()->toBase()
            ->selectRaw('count(*) as open, count(*) filter (where due_at < current_date) as overdue, min(created_at) as oldest')
            ->first();

        return [
            'open'              => (int)$open->open,
            'overdue'           => (int)$open->overdue,
            'oldest_days'       => $open->oldest ? (int)now()->diffInDays($open->oldest, true) : null,
            'done_last_30_days' => self::warehouseTasks($warehouse)->where('staff_tasks.status', StaffTaskStatusEnum::DONE)->where('staff_tasks.closed_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    protected function statusElements(Warehouse $warehouse): array
    {
        $counts = self::warehouseTasks($warehouse)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(StaffTaskStatusEnum::cases())->mapWithKeys(fn (StaffTaskStatusEnum $status) => [
            $status->value => [StaffTaskStatusEnum::labels()[$status->value], (int)($counts[$status->value] ?? 0)],
        ])->all();
    }

    protected function openStatuses(): string
    {
        return StaffTaskStatusEnum::TODO->value.','.StaffTaskStatusEnum::IN_PROGRESS->value;
    }

    public function handle(Warehouse $warehouse, string $prefix = 'warehouse_tasks'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($search) => $search
                ->where('staff_tasks.reference', 'ilike', "%$value%")
                ->orWhere('staff_tasks.subject', 'ilike', "%$value%"));
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        $queryBuilder = QueryBuilder::for(self::warehouseTasks($warehouse))
            ->with(['requester', 'assignee', 'model']);

        $queryBuilder->whereElementGroup(
            key: 'status',
            allowedElements: array_keys($this->statusElements($warehouse)),
            engine: fn ($query, $elements) => $query->whereIn('staff_tasks.status', $elements),
            prefix: $prefix,
            default: $this->openStatuses()
        );

        return $queryBuilder
            ->defaultSort('staff_tasks.created_at')
            ->allowedSorts(['reference', 'subject', 'status', 'due_at', 'created_at', 'closed_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse $warehouse, string $prefix = 'warehouse_tasks'): Closure
    {
        return function (InertiaTable $table) use ($warehouse, $prefix) {
            $table->name($prefix)->pageName($prefix.'Page');

            $table->elementGroup(key: 'status', label: __('Status'), elements: $this->statusElements($warehouse), default: $this->openStatuses());

            $table
                ->withGlobalSearch(__('Search tasks'))
                ->withLabelRecord([__('task'), __('tasks')])
                ->defaultSort('created_at')
                ->column(key: 'reference', label: __('Task'), canBeHidden: false, sortable: true, searchable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'subject', label: __('What was asked'), canBeHidden: false, sortable: true, searchable: true, className: 'w-full max-w-0')
                ->column(key: 'linked', label: __('Where'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'requester', label: __('Asked by'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'created_at', label: __('Asked on'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'days_waiting', label: __('Days waiting'), canBeHidden: false, align: 'right', className: 'whitespace-nowrap w-px')
                ->column(key: 'due_at', label: __('Due'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px');
        };
    }

    public function jsonResponse(LengthAwarePaginator $staffTasks): AnonymousResourceCollection
    {
        return WarehouseStaffTasksResource::collection($staffTasks);
    }

    public function htmlResponse(LengthAwarePaginator $staffTasks, ActionRequest $request): Response
    {
        $summary = self::openSummary($this->warehouse);

        return Inertia::render(
            'Org/Inventory/WarehouseStaffTasks',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Warehouse tasks'),
                'pageHead'    => [
                    'title'      => __('Warehouse tasks'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-tasks'],
                        'title' => __('Warehouse tasks'),
                    ],
                    'afterTitle' => [
                        'label' => $summary['open']
                            ? __(':open waiting, the oldest for :days days', ['open' => $summary['open'], 'days' => $summary['oldest_days']])
                            : __('Nothing waiting'),
                    ],
                ],
                'tasks'       => WarehouseStaffTasksResource::collection($staffTasks),
            ]
        )->table($this->tableStructure($this->warehouse));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.staff_tasks.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Warehouse tasks'),
                        'icon'  => 'fal fa-tasks',
                    ],
                ],
            ]
        );
    }
}
