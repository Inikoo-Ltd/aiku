<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject\UI;

use App\Actions\Helpers\Ticket\UI\WithTicketsScope;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketProjectsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexTicketProjects extends OrgAction
{
    use WithTicketsScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null && !$request->user()->worksOnlyForAgents();
    }

    /**
     * Top-level projects for the standard table; each brings its sub-projects, whose work rolls up into it.
     */
    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query
                ->whereRaw('ticket_projects.name ilike ?', ["%$value%"])
                ->orWhereHas('children', fn ($child) => $child->whereRaw('name ilike ?', ["%$value%"])));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $statusOrder = new class () implements Sort {
            public function __invoke(Builder $query, bool $descending, string $property): void
            {
                $query->orderByRaw("case ticket_projects.status when 'active' then 0 when 'on_hold' then 1 when 'done' then 2 else 3 end ".($descending ? 'desc' : 'asc'));
            }
        };

        return QueryBuilder::for(self::projectsQuery($group)->whereNull('ticket_projects.parent_id')->with(['children' => fn ($query) => self::withProgress($query)]))
            ->allowedFilters([$globalSearch])
            ->defaultSort(AllowedSort::custom('status', $statusOrder), 'target_date')
            ->allowedSorts([AllowedSort::custom('status', $statusOrder), 'name', 'start_date', 'target_date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromTicketsScope($request);

        return $this->handle($this->group);
    }

    /**
     * Every project of the group with what its progress needs, for the projects MCP tool.
     *
     * @return Collection<int, TicketProject>
     */
    public static function allProjects(Group $group): Collection
    {
        return self::projectsQuery($group)
            ->with(['parent:id,slug', 'children' => fn ($query) => self::withProgress($query)])
            ->orderByRaw("case status when 'active' then 0 when 'on_hold' then 1 when 'done' then 2 else 3 end")
            ->orderBy('target_date')
            ->get();
    }

    private static function projectsQuery(Group $group): Builder
    {
        return self::withProgress(TicketProject::where('ticket_projects.group_id', $group->id));
    }

    public static function withProgress(Builder|HasMany $query): Builder|HasMany
    {
        return $query
            ->with(['owner.image', 'members.image', 'updates' => fn ($query) => $query->whereNotNull('health')->latest('id')->limit(1)])
            ->withCount([
                'tickets',
                'tickets as done_tickets_count'      => fn ($query) => $query->where('status', TicketStatusEnum::RESOLVED),
                'tickets as cancelled_tickets_count' => fn ($query) => $query->where('status', TicketStatusEnum::CANCELLED),
                'staffTasks',
                'staffTasks as done_staff_tasks_count'      => fn ($query) => $query->where('status', StaffTaskStatusEnum::DONE),
                'staffTasks as cancelled_staff_tasks_count' => fn ($query) => $query->where('status', StaffTaskStatusEnum::CANCELLED),
            ]);
    }

    /**
     * Progress of a project loaded through withProgress(), its sub-projects' work included.
     *
     * @return array{total: int, done: int, open: int, percent: int|null, time_percent: int|null, week: int, weeks: int|null, days_left: int|null}
     */
    public static function rolledUpProgress(TicketProject $project): array
    {
        $projects = collect([$project])->concat($project->relationLoaded('children') ? $project->children : []);

        return self::progress(
            $project,
            $projects->sum(fn (TicketProject $item) => $item->tickets_count + $item->staff_tasks_count),
            $projects->sum(fn (TicketProject $item) => $item->done_tickets_count + $item->done_staff_tasks_count),
            $projects->sum(fn (TicketProject $item) => $item->cancelled_tickets_count + $item->cancelled_staff_tasks_count)
        );
    }

    /**
     * Time is measured in days between the start and target dates; work is tickets done out of
     * those not cancelled, so a project falling behind shows a work bar shorter than its time bar.
     *
     * @return array{total: int, done: int, open: int, percent: int|null, time_percent: int|null, week: int, weeks: int|null, days_left: int|null}
     */
    public static function progress(TicketProject $project, int $total, int $done, int $cancelled): array
    {
        $counted   = $total - $cancelled;
        $today     = Carbon::today();
        $totalDays = $project->target_date ? max(1, (int) $project->start_date->diffInDays($project->target_date)) : null;
        $elapsed   = max(0, (int) $project->start_date->diffInDays($today, false));

        return [
            'total'        => $total,
            'done'         => $done,
            'open'         => $counted - $done,
            'percent'      => $counted > 0 ? (int) round(100 * $done / $counted) : null,
            'time_percent' => $totalDays ? (int) min(100, round(100 * $elapsed / $totalDays)) : null,
            'week'         => intdiv($elapsed, 7) + 1,
            'weeks'        => $totalDays ? (int) ceil($totalDays / 7) : null,
            'days_left'    => $project->target_date ? (int) $today->diffInDays($project->target_date, false) : null,
        ];
    }

    /**
     * Work of the project and its sub-projects.
     *
     * @return array{total: int, done: int, cancelled: int}
     */
    public static function workTotals(TicketProject $project): array
    {
        $projectIds = $project->children()->pluck('id')->push($project->id);

        $tickets = Ticket::whereIn('ticket_project_id', $projectIds)
            ->selectRaw("count(*) as total, count(*) filter (where status = 'resolved') as done, count(*) filter (where status = 'cancelled') as cancelled")
            ->toBase()->first();
        $tasks = StaffTask::whereIn('ticket_project_id', $projectIds)
            ->selectRaw("count(*) as total, count(*) filter (where status = 'done') as done, count(*) filter (where status = 'cancelled') as cancelled")
            ->toBase()->first();

        return [
            'total'     => (int) $tickets->total + (int) $tasks->total,
            'done'      => (int) $tickets->done + (int) $tasks->done,
            'cancelled' => (int) $tickets->cancelled + (int) $tasks->cancelled,
        ];
    }

    public static function person(?User $user): ?array
    {
        return $user ? [
            'id'     => $user->id,
            'name'   => $user->contact_name ?: $user->username,
            'avatar' => $user->imageSources(48, 48),
        ] : null;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public static function staffOptions(Group $group): array
    {
        return User::where('group_id', $group->id)
            ->where('status', true)
            ->orderBy('contact_name')
            ->get(['id', 'username', 'contact_name'])
            ->map(fn (User $user) => ['label' => $user->contact_name ?: $user->username, 'value' => $user->id])
            ->values()
            ->all();
    }

    public function htmlResponse(LengthAwarePaginator $projects, ActionRequest $request): Response
    {
        return Inertia::render(
            'Tickets/TicketProjects',
            [
                'breadcrumbs' => array_merge(
                    $this->moduleScopeParentBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.projects.index'], 'label' => __('Projects')]]]
                ),
                'title'    => __('Projects'),
                'pageHead' => [
                    'title' => __('Projects'),
                    'icon'  => ['fal', 'fa-project-diagram'],
                ],
                'data'            => TicketProjectsResource::collection($projects),
                'view'            => data_get($request->user()->settings, 'projects_view', 'table'),
                'can_create'      => TicketProject::canBeCreatedBy($request->user()),
                'staff'           => self::staffOptions($this->group),
                'parent_options'  => self::parentOptions($this->group),
                'store_route'     => ['name' => 'grp.models.ticket_project.store'],
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch(__('Search projects'))
                ->withLabelRecord([__('project'), __('projects')])
                ->withEmptyState(['title' => __('No projects yet'), 'description' => __('A project groups the tickets, milestones and progress notes of one big piece of work.')])
                ->column(key: 'name', label: __('Project'), canBeHidden: false, sortable: true, searchable: true, className: 'w-full')
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'progress', label: __('Work done'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'time', label: __('Time used'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'start_date', label: __('Start'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'target_date', label: __('Target'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'owner', label: __('Owner'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'members', label: __('Team'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->defaultSort('status');
        };
    }

    /**
     * Projects a project can be put under: top-level ones, since sub-projects go one level deep.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function parentOptions(Group $group): array
    {
        return TicketProject::where('group_id', $group->id)
            ->whereNull('parent_id')
            ->whereNotIn('status', [TicketProjectStatusEnum::CANCELLED, TicketProjectStatusEnum::DONE])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TicketProject $project) => ['label' => $project->name, 'value' => $project->id])
            ->all();
    }
}
