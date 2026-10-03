<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\TicketProject\UI\IndexTicketProjects;
use App\Actions\Helpers\TicketProject\UI\ShowTicketProject;
use App\Models\Helpers\TicketProject;
use App\Models\Helpers\TicketProjectMilestone;
use App\Models\Helpers\TicketProjectUpdate;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Projects group tickets (HELP-n, INI-n) and staff tasks (TASK-n) of one big piece of work under milestones, with progress updates. Without a project: lists projects with status, health, owner, team, dates and progress (work done against time used). With a project slug: shows its goal, milestones (with done/total work), every ticket and task the user can see with state, assignee and milestone, the latest progress updates, deployed commits and open work per person. Use project-write-tool to change anything. Read only.')]
#[IsReadOnly]
class ProjectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'project' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();

        if (!$request->filled('project')) {
            return Response::json(['projects' => $this->list($user)]);
        }

        $project = self::findProject($user, $request->string('project')->toString());
        if (!$project) {
            return Response::error('No project "'.$request->string('project').'". Projects: '.TicketProject::where('group_id', $user->group_id)->orderBy('name')->pluck('slug')->implode(', ').'.');
        }

        return Response::json($this->show($user, $project));
    }

    public static function findProject(User $user, string $identifier): ?TicketProject
    {
        $identifier = trim($identifier);

        return TicketProject::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->where('slug', strtolower($identifier))->orWhereRaw('lower(name) = ?', [mb_strtolower($identifier)]))
            ->first();
    }

    private function list(User $user): array
    {
        return IndexTicketProjects::make()->handle($user->group)->map(fn (TicketProject $project) => [
            'project'     => $project->slug,
            'name'        => $project->name,
            'status'      => $project->status->value,
            'health'      => $project->updates->first()?->health?->value,
            'owner'       => $project->owner?->username,
            'team'        => $project->members->pluck('username')->all(),
            'start_date'  => $project->start_date->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
            'progress'    => IndexTicketProjects::progress(
                $project,
                $project->tickets_count + $project->staff_tasks_count,
                $project->done_tickets_count + $project->done_staff_tasks_count,
                $project->cancelled_tickets_count + $project->cancelled_staff_tasks_count
            ),
            'url'         => route('grp.tickets.projects.show', $project->slug),
        ])->values()->all();
    }

    private function show(User $user, TicketProject $project): array
    {
        $project->load(['owner', 'members', 'milestones']);
        $work       = ShowTicketProject::workItems($project, $user);
        $totals     = IndexTicketProjects::workTotals($project);
        $milestones = $project->milestones->keyBy('id');

        $updates = $project->updates()->with('author')->latest('id')->limit(10)->get()->map(fn (TicketProjectUpdate $update) => [
            'at'     => $update->created_at?->toIso8601String(),
            'author' => $update->author?->username,
            'health' => $update->health?->value,
            'body'   => $update->body,
        ]);

        return [
            'project'     => $project->slug,
            'name'        => $project->name,
            'goal'        => $project->description,
            'status'      => $project->status->value,
            'health'      => $updates->firstWhere('health', '!==', null)['health'] ?? null,
            'owner'       => $project->owner?->username,
            'team'        => $project->members->pluck('username')->all(),
            'start_date'  => $project->start_date->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
            'progress'    => IndexTicketProjects::progress($project, $totals['total'], $totals['done'], $totals['cancelled']),
            'hidden_work' => $totals['total'] - $work->count(),
            'milestones'  => $project->milestones->map(fn (TicketProjectMilestone $milestone) => [
                'id'         => $milestone->id,
                'name'       => $milestone->name,
                'start_date' => $milestone->start_date?->toDateString(),
                'due_date'   => $milestone->due_date?->toDateString(),
                'done'       => $milestone->done_at !== null,
                'work_done'  => $work->where('milestone_id', $milestone->id)->where('state', 'done')->count(),
                'work_total' => $work->where('milestone_id', $milestone->id)->where('state', '!=', 'cancelled')->count(),
            ])->values()->all(),
            'work' => $work->map(fn (array $item) => [
                'reference' => $item['reference'],
                'type'      => $item['type'],
                'subject'   => $item['subject'],
                'state'     => $item['state'],
                'status'    => $item['status'],
                'priority'  => $item['priority'],
                'assignee'  => $item['assignee']['name'] ?? null,
                'milestone' => $item['milestone_id'] ? $milestones->get($item['milestone_id'])?->name : null,
            ])->values()->all(),
            'updates'  => $updates->all(),
            'commits'  => array_slice(ShowTicketProject::commits($work), 0, 20),
            'workload' => collect(ShowTicketProject::workload($work))->map(fn (array $row) => [
                'person'      => $row['person']['name'] ?? null,
                'todo'        => $row['todo'],
                'in_progress' => $row['in_progress'],
                'done'        => $row['done'],
            ])->all(),
            'can_edit' => $project->canBeEditedBy($user),
            'url'      => route('grp.tickets.projects.show', $project->slug),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project slug (or exact name) to show; leave out to list all projects'),
        ];
    }
}
