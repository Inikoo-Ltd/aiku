<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject\UI;

use App\Actions\Helpers\Ticket\UI\WithTicketsScope;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketProjectHealthEnum;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexTicketProjects extends OrgAction
{
    use WithTicketsScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null && !$request->user()->worksOnlyForAgents();
    }

    public function handle(Group $group): Collection
    {
        return TicketProject::where('group_id', $group->id)
            ->with(['owner.image', 'members.image', 'updates' => fn ($query) => $query->whereNotNull('health')->latest('id')->limit(1)])
            ->withCount([
                'tickets',
                'tickets as done_tickets_count'      => fn ($query) => $query->where('status', TicketStatusEnum::RESOLVED),
                'tickets as cancelled_tickets_count' => fn ($query) => $query->where('status', TicketStatusEnum::CANCELLED),
                'staffTasks',
                'staffTasks as done_staff_tasks_count'      => fn ($query) => $query->where('status', StaffTaskStatusEnum::DONE),
                'staffTasks as cancelled_staff_tasks_count' => fn ($query) => $query->where('status', StaffTaskStatusEnum::CANCELLED),
            ])
            ->orderByRaw("case status when 'active' then 0 when 'on_hold' then 1 when 'done' then 2 else 3 end")
            ->orderBy('target_date')
            ->get();
    }

    public function asController(ActionRequest $request): Collection
    {
        $this->initialisationFromTicketsScope($request);

        return $this->handle($this->group);
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
     * @return array{total: int, done: int, cancelled: int}
     */
    public static function workTotals(TicketProject $project): array
    {
        $tickets = $project->tickets()
            ->selectRaw("count(*) as total, count(*) filter (where status = 'resolved') as done, count(*) filter (where status = 'cancelled') as cancelled")
            ->toBase()->first();
        $tasks = $project->staffTasks()
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

    public function htmlResponse(Collection $projects, ActionRequest $request): Response
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
                'projects' => $projects->map(fn (TicketProject $project) => [
                    'slug'        => $project->slug,
                    'name'        => $project->name,
                    'status'      => $project->status->value,
                    'status_label' => TicketProjectStatusEnum::labels()[$project->status->value],
                    'start_date'  => $project->start_date->toDateString(),
                    'target_date' => $project->target_date?->toDateString(),
                    'owner'       => self::person($project->owner),
                    'members'     => $project->members->map(fn (User $member) => self::person($member))->values()->all(),
                    'progress'    => self::progress(
                        $project,
                        $project->tickets_count + $project->staff_tasks_count,
                        $project->done_tickets_count + $project->done_staff_tasks_count,
                        $project->cancelled_tickets_count + $project->cancelled_staff_tasks_count
                    ),
                    'health'       => $project->updates->first()?->health?->value,
                    'health_label' => ($health = $project->updates->first()?->health) ? TicketProjectHealthEnum::labels()[$health->value] : null,
                ])->values()->all(),
                'can_create'  => TicketProject::canBeCreatedBy($request->user()),
                'staff'       => self::staffOptions($this->group),
                'store_route' => ['name' => 'grp.models.ticket_project.store'],
            ]
        );
    }
}
