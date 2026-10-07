<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Tasks\Json\GetStaffTasks;
use App\Models\Chat\StaffMessage;
use App\Models\Tasks\StaffTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Follow up on staff tasks (TASK-n, inter-staff requests made with staff-task-write-tool). Without a reference: lists the authenticated user\'s open tasks, view=requested (tasks they asked for, default), mine (assigned to them or collaborating) or department (their departments\' queue); closed=true lists recently closed ones instead. With a reference: shows that task with the latest messages of its thread, when the user can read the thread. Read only.')]
#[IsReadOnly]
class StaffTasksTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'reference' => ['sometimes', 'nullable', 'string'],
            'view'      => ['sometimes', 'nullable', 'in:requested,mine,department'],
            'closed'    => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if ($request->filled('reference')) {
            $task = StaffTask::where('group_id', $user->group_id)->where('reference', strtoupper($request->string('reference')))->first();
            if (!$task) {
                return Response::error('No task '.$request->string('reference').'.');
            }

            $messages = $task->conversation?->canBeAccessedBy($user)
                ? $task->conversation->messages()->with('user')->latest('id')->limit(30)->get()->reverse()->map(fn (StaffMessage $message) => [
                    'from' => $message->user?->username,
                    'at'   => $message->created_at?->toIso8601String(),
                    'body' => $message->body,
                ])->values()->all()
                : null;

            return Response::json(['task' => $this->summary($task), 'messages' => $messages]);
        }

        $tasks = GetStaffTasks::run($user, $request->get('view') ?? 'requested', $request->boolean('closed'));

        return Response::json(['count' => $tasks->count(), 'tasks' => $tasks->map(fn (StaffTask $task) => $this->summary($task))->all()]);
    }

    private function summary(StaffTask $task): array
    {
        return [
            'reference'  => $task->reference,
            'url'        => route('grp.tasks.index', ['task' => $task->reference]),
            'subject'    => $task->subject,
            'status'     => $task->status->value,
            'priority'   => $task->priority->value,
            'requester'  => $task->requester?->username,
            'assignee'   => $task->assignee?->username,
            'department' => $task->department,
            'due_date'   => $task->due_at?->toDateString(),
            'linked_to'  => $task->model_type ? $task->model_type.' '.($task->model?->reference ?? $task->model?->code ?? $task->model?->name) : null,
            'created_at' => $task->created_at?->toIso8601String(),
            'closed_at'  => $task->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('Task to show with its thread, e.g. TASK-12'),
            'view'      => $schema->string()->description('requested (default), mine or department'),
            'closed'    => $schema->boolean()->description('true = recently closed tasks instead of open ones'),
        ];
    }
}
