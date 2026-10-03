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
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusGroupEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketProject;
use App\Models\Helpers\TicketProjectMilestone;
use App\Models\Helpers\TicketProjectUpdate;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicketProject extends OrgAction
{
    use WithTicketsScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null && $request->route('ticketProject')->group_id === $request->user()->group_id;
    }

    public function handle(TicketProject $ticketProject): TicketProject
    {
        return $ticketProject->load(['owner.image', 'members.image', 'milestones']);
    }

    public function asController(TicketProject $ticketProject, ActionRequest $request): TicketProject
    {
        $this->initialisationFromTicketsScope($request);

        return $this->handle($ticketProject);
    }

    /**
     * Tickets and staff tasks as one list, each with a state shared by both kinds of work:
     * todo, in_progress, done or cancelled.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function workItems(TicketProject $project, User $viewer): Collection
    {
        $ticketIcons = TicketStatusEnum::stateIcon();
        $taskIcons   = StaffTaskStatusEnum::stateIcon();

        $tickets = $project->tickets()->visibleTo($viewer)->with('assignee.image')->get()->map(fn (Ticket $ticket) => [
            'key'          => 'ticket-'.$ticket->id,
            'type'         => 'ticket',
            'id'           => $ticket->id,
            'reference'    => $ticket->reference,
            'subject'      => $ticket->subject,
            'url'          => route('grp.tickets.show', $ticket->reference),
            'status'       => $ticket->status->value,
            'status_label' => TicketStatusEnum::labels()[$ticket->status->value],
            'status_icon'  => $ticketIcons[$ticket->status->value],
            'state'        => match ($ticket->status->group()) {
                TicketStatusGroupEnum::TODO        => 'todo',
                TicketStatusGroupEnum::IN_PROGRESS => 'in_progress',
                default                            => $ticket->status === TicketStatusEnum::CANCELLED ? 'cancelled' : 'done',
            },
            'priority'     => $ticket->priority->value,
            'assignee'     => IndexTicketProjects::person($ticket->assignee),
            'milestone_id' => $ticket->ticket_project_milestone_id,
            'created_at'   => $ticket->created_at,
            'updated_at'   => $ticket->updated_at,
            'done_at'      => $ticket->status === TicketStatusEnum::RESOLVED ? ($ticket->resolved_at ?? $ticket->updated_at) : null,
            'commits'      => data_get($ticket->data, 'commits', []),
            'project_route' => ['name' => 'grp.models.ticket.project.update', 'parameters' => ['ticket' => $ticket->id]],
        ]);

        $tasks = $project->staffTasks()->visibleTo($viewer)->with('assignee.image')->get()->map(fn (StaffTask $task) => [
            'key'          => 'task-'.$task->id,
            'type'         => 'task',
            'id'           => $task->id,
            'reference'    => $task->reference,
            'subject'      => $task->subject,
            'url'          => route('grp.tasks.show', $task->reference),
            'status'       => $task->status->value,
            'status_label' => StaffTaskStatusEnum::labels()[$task->status->value],
            'status_icon'  => $taskIcons[$task->status->value],
            'state'        => $task->status->value,
            'priority'     => $task->priority->value,
            'assignee'     => IndexTicketProjects::person($task->assignee),
            'milestone_id' => $task->ticket_project_milestone_id,
            'created_at'   => $task->created_at,
            'updated_at'   => $task->updated_at,
            'done_at'      => $task->status === StaffTaskStatusEnum::DONE ? ($task->closed_at ?? $task->updated_at) : null,
            'commits'      => [],
            'project_route' => ['name' => 'grp.tasks.project.update', 'parameters' => ['staffTask' => $task->reference]],
        ]);

        return $tickets->concat($tasks)->sortByDesc('updated_at')->values();
    }

    /**
     * Weekly burn-up from the start date: scope is the work that existed by the end of each week,
     * done is the work finished by then. Cancelled work is left out of both.
     *
     * @return array<int, array{date: string, scope: int, done: int}>
     */
    public static function burnUp(TicketProject $project, Collection $work): array
    {
        $counted = $work->where('state', '!=', 'cancelled');
        $end     = Carbon::today();
        $weeks   = [];

        for ($week = $project->start_date->copy()->startOfWeek(); $week->lte($end); $week->addWeek()) {
            $weekEnd = $week->copy()->endOfWeek();
            $weeks[] = [
                'date'  => $week->toDateString(),
                'scope' => $counted->filter(fn (array $item) => $item['created_at']->lte($weekEnd))->count(),
                'done'  => $counted->filter(fn (array $item) => $item['done_at']?->lte($weekEnd))->count(),
            ];
        }

        return $weeks;
    }

    /**
     * @return array<int, array{hash: string, subject: string|null, version: string|null, deployed_at: string|null, reference: string, url: string}>
     */
    public static function commits(Collection $work): array
    {
        return $work->where('type', 'ticket')
            ->flatMap(fn (array $item) => collect($item['commits'])->map(fn (array $commit) => [
                'hash'        => $commit['hash'] ?? '',
                'subject'     => $commit['subject'] ?? null,
                'version'     => $commit['version'] ?? null,
                'deployed_at' => $commit['deployed_at'] ?? null,
                'reference'   => $item['reference'],
                'url'         => $item['url'],
            ]))
            ->unique(fn (array $commit) => $commit['hash'].$commit['reference'])
            ->sortByDesc('deployed_at')
            ->values()
            ->all();
    }

    /**
     * What happened on the project, newest first: work added and finished, deployments,
     * milestones reached and progress updates.
     *
     * @return array<int, array{at: mixed, icon: string, text: string, url: string|null, by: string|null}>
     */
    public static function activity(TicketProject $project, Collection $work, Collection $updates, array $commits): array
    {
        $events = collect();

        foreach ($work as $item) {
            $events->push(['at' => $item['created_at'], 'icon' => 'fal fa-plus-circle', 'text' => __(':reference opened: :subject', ['reference' => $item['reference'], 'subject' => $item['subject']]), 'url' => $item['url'], 'by' => null]);
            if ($item['done_at']) {
                $events->push(['at' => $item['done_at'], 'icon' => 'fal fa-check-circle', 'text' => __(':reference done: :subject', ['reference' => $item['reference'], 'subject' => $item['subject']]), 'url' => $item['url'], 'by' => $item['assignee']['name'] ?? null]);
            }
        }

        foreach ($commits as $commit) {
            if ($commit['deployed_at']) {
                $events->push(['at' => $commit['deployed_at'], 'icon' => 'fal fa-rocket', 'text' => __('Deployed :version for :reference: :subject', ['version' => $commit['version'] ?? '', 'reference' => $commit['reference'], 'subject' => $commit['subject'] ?? '']), 'url' => $commit['url'], 'by' => null]);
            }
        }

        foreach ($project->milestones->whereNotNull('done_at') as $milestone) {
            $events->push(['at' => $milestone->done_at, 'icon' => 'fal fa-flag-checkered', 'text' => __('Milestone reached: :name', ['name' => $milestone->name]), 'url' => null, 'by' => null]);
        }

        foreach ($updates as $update) {
            $events->push(['at' => $update['created_at'], 'icon' => 'fal fa-comment-alt-lines', 'text' => __('Progress update'), 'url' => null, 'by' => $update['author']['name'] ?? null]);
        }

        return $events->sortByDesc(fn (array $event) => Carbon::parse($event['at'])->getTimestamp())->take(150)->values()->all();
    }

    /**
     * @return array<int, array{person: array|null, todo: int, in_progress: int, done: int}>
     */
    public static function workload(Collection $work): array
    {
        return $work->where('state', '!=', 'cancelled')
            ->groupBy(fn (array $item) => $item['assignee']['id'] ?? 0)
            ->map(fn (Collection $items) => [
                'person'      => $items->first()['assignee'],
                'todo'        => $items->where('state', 'todo')->count(),
                'in_progress' => $items->where('state', 'in_progress')->count(),
                'done'        => $items->where('state', 'done')->count(),
            ])
            ->sortByDesc(fn (array $row) => $row['todo'] + $row['in_progress'])
            ->values()
            ->all();
    }

    public function htmlResponse(TicketProject $project, ActionRequest $request): Response
    {
        $user = $request->user();
        $work = self::workItems($project, $user);

        $totals = IndexTicketProjects::workTotals($project);

        $updates = $project->updates()->with('author.image')->latest('id')->limit(200)->get()
            ->map(fn (TicketProjectUpdate $update) => [
                'id'           => $update->id,
                'body'         => $update->body,
                'health'       => $update->health?->value,
                'health_label' => $update->health ? TicketProjectHealthEnum::labels()[$update->health->value] : null,
                'author'       => IndexTicketProjects::person($update->author),
                'created_at'   => $update->created_at,
            ]);

        $commits = self::commits($work);
        $health  = $updates->firstWhere('health', '!==', null);

        return Inertia::render(
            'Tickets/TicketProject',
            [
                'breadcrumbs' => array_merge(
                    $this->ticketsBreadcrumbs(),
                    [
                        ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.projects.index'], 'label' => __('Projects')]],
                        ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tickets.projects.show', 'parameters' => [$project->slug]], 'label' => $project->name]],
                    ]
                ),
                'title'    => $project->name,
                'pageHead' => [
                    'model' => __('Project'),
                    'title' => $project->name,
                    'icon'  => ['fal', 'fa-project-diagram'],
                ],
                'project' => [
                    'id'           => $project->id,
                    'slug'         => $project->slug,
                    'name'         => $project->name,
                    'description'  => $project->description,
                    'status'       => $project->status->value,
                    'status_label' => TicketProjectStatusEnum::labels()[$project->status->value],
                    'start_date'   => $project->start_date->toDateString(),
                    'target_date'  => $project->target_date?->toDateString(),
                    'owner'        => IndexTicketProjects::person($project->owner),
                    'members'      => $project->members->map(fn (User $member) => IndexTicketProjects::person($member))->values()->all(),
                    'health'       => $health['health'] ?? null,
                    'health_label' => $health['health_label'] ?? null,
                    'health_at'    => $health['created_at'] ?? null,
                ],
                'milestones' => $project->milestones->map(function (TicketProjectMilestone $milestone) use ($work) {
                    $items = $work->where('milestone_id', $milestone->id)->where('state', '!=', 'cancelled');

                    return [
                        'id'          => $milestone->id,
                        'name'        => $milestone->name,
                        'description' => $milestone->description,
                        'start_date'  => $milestone->start_date?->toDateString(),
                        'due_date'    => $milestone->due_date?->toDateString(),
                        'done_at'     => $milestone->done_at?->toDateString(),
                        'total'       => $items->count(),
                        'done'        => $items->where('state', 'done')->count(),
                    ];
                })->values()->all(),
                'progress'    => IndexTicketProjects::progress($project, $totals['total'], $totals['done'], $totals['cancelled']),
                'hidden_work' => $totals['total'] - $work->count(),
                'work'        => $work->map(fn (array $item) => collect($item)->except('commits')->all())->all(),
                'burn_up'     => self::burnUp($project, $work),
                'commits'     => $commits,
                'activity'    => self::activity($project, $work, $updates, $commits),
                'workload'    => self::workload($work),
                'updates'     => $updates->all(),
                'can_edit'    => $project->canBeEditedBy($user),
                'options'     => [
                    'statuses' => TicketProjectStatusEnum::options(),
                    'healths'  => TicketProjectHealthEnum::options(),
                    'staff'    => IndexTicketProjects::staffOptions($this->group),
                ],
                'routes' => [
                    'update'      => ['name' => 'grp.models.ticket_project.update', 'parameters' => ['ticketProject' => $project->id]],
                    'post_update' => ['name' => 'grp.models.ticket_project.update.store', 'parameters' => ['ticketProject' => $project->id]],
                    'attach_work' => ['name' => 'grp.models.ticket_project.work.attach', 'parameters' => ['ticketProject' => $project->id]],
                    'create_ticket' => ['name' => 'grp.tickets.create', 'parameters' => ['project' => $project->slug]],
                ],
            ]
        );
    }
}
