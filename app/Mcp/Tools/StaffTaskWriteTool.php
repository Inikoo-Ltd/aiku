<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Ask a colleague or a department to do something: creates a staff task (TASK-n) with its own chat thread, requested by the authenticated user. Use it for work people do, e.g. ask the warehouse to count a location, ask buying to chase a supplier. Give either an assignee (aiku username, or "me" for a task on the user\'s own to-do list) or a department slug (e.g. warehouse); an unknown department lists the valid ones. With a reference and a status (todo, in_progress, done, cancelled with a note) it moves a task the user asked for or is assigned to instead, e.g. ticks off an item of their own to-do list. Optionally link the task to a location or SKO in an organisation so it shows where the work is. Tickets are different: bugs and requests to engineers go through tickets, and engineers and QA cannot be given tasks. Returns the task reference and its URL.')]
class StaffTaskWriteTool extends Tool
{
    public function handle(Request $request): Response
    {
        if ($request->filled('reference')) {
            return $this->changeStatus($request);
        }

        $request->validate([
            'subject'      => ['required', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'assignee'     => ['sometimes', 'nullable', 'string'],
            'department'   => ['sometimes', 'nullable', 'string'],
            'priority'     => ['sometimes', 'nullable', 'string'],
            'due_date'     => ['sometimes', 'nullable', 'string'],
            'organisation' => ['required_with:location,sko', 'nullable', 'string'],
            'location'     => ['sometimes', 'nullable', 'string'],
            'sko'          => ['sometimes', 'nullable', 'string'],
        ]);

        $user     = $request->user();
        $linkedTo = null;

        $modelData = array_filter([
            'subject'     => $request->get('subject'),
            'description' => $request->get('description'),
            'department'  => $request->get('department'),
            'priority'    => $request->get('priority'),
            'due_at'      => $request->get('due_date'),
        ], fn ($value) => $value !== null && $value !== '');

        if ($request->filled('assignee')) {
            $username = $request->string('assignee')->lower()->toString();
            $assignee = $username === 'me' ? $user : $user->group->users()->where('username', $username)->first();
            if (!$assignee) {
                return Response::error('No aiku user with username "'.$request->string('assignee').'".');
            }
            $modelData['assignee_id'] = $assignee->id;
        }

        if ($request->filled('location') || $request->filled('sko')) {
            $identifier   = $request->string('organisation')->lower()->toString();
            $organisation = Organisation::where('group_id', $user->group_id)
                ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$identifier])->orWhereRaw('lower(slug) = ?', [$identifier]))
                ->first();
            if (!$organisation) {
                return Response::error("'{$request->string('organisation')}' is not an organisation. Use one of: ".Organisation::where('group_id', $user->group_id)->orderBy('id')->pluck('code')->implode(', ').'.');
            }

            [$modelType, $code, $models] = $request->filled('location')
                ? ['Location', $request->string('location')->toString(), Location::where('organisation_id', $organisation->id)->where('code', $request->string('location')->toString())->get(['id'])]
                : ['OrgStock', $request->string('sko')->toString(), OrgStock::where('organisation_id', $organisation->id)->where('code', $request->string('sko')->toString())->get(['id'])];

            if ($models->count() !== 1) {
                return Response::error($models->isEmpty() ? "No {$modelType} {$code} in {$organisation->code}." : "{$code} matches {$models->count()} {$modelType} records in {$organisation->code}; describe it in the description instead.");
            }
            $modelData['model_type'] = $modelType;
            $modelData['model_id']   = $models->first()->id;
            $linkedTo                = "{$modelType} {$code} ({$organisation->code})";
        }

        try {
            $task = StoreStaffTask::make()->action($user, $modelData);
        } catch (ValidationException $exception) {
            $message = implode(' ', $exception->validator->errors()->all());
            if ($exception->validator->errors()->hasAny(['department', 'assignee_id'])) {
                $message .= ' Departments: '.implode(', ', array_column(StaffTask::departments($user->group_id), 'value')).'.';
            }

            return Response::error($message);
        }

        return Response::json([
            'created'    => $task->reference,
            'url'        => route('grp.tasks.index', ['task' => $task->reference]),
            'assignee'   => $task->assignee?->username,
            'department' => $task->department,
            'priority'   => $task->priority->value,
            'due_date'   => $task->due_at?->toDateString(),
            'linked_to'  => $linkedTo,
        ]);
    }

    protected function changeStatus(Request $request): Response
    {
        $request->validate([
            'status' => ['required', 'in:'.implode(',', array_column(StaffTaskStatusEnum::cases(), 'value'))],
            'note'   => ['required_if:status,cancelled', 'nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $task = StaffTask::where('group_id', $user->group_id)
            ->where('reference', $request->string('reference')->upper()->toString())
            ->where(fn ($query) => $query->where('requester_id', $user->id)->orWhere('assignee_id', $user->id))
            ->first();
        if (!$task) {
            return Response::error('No task '.$request->string('reference').' that you asked for or are assigned to.');
        }

        UpdateStaffTask::make()->handle($task, $user, array_filter(['status' => $request->get('status'), 'note' => $request->get('note')]));

        return Response::json(['updated' => $task->reference, 'status' => $task->status->value]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject'      => $schema->string()->description('What needs doing, e.g. "Count location 45W24". Required when creating'),
            'description'  => $schema->string()->description('Details and why, posted as the first message in the task thread (max 5000 characters)'),
            'assignee'     => $schema->string()->description('aiku username of the colleague to do it, or "me" for the user\'s own to-do list. Give this or department'),
            'department'   => $schema->string()->description('Department slug to do it, e.g. warehouse; its members pick it up from their queue. Give this or assignee'),
            'priority'     => $schema->string()->description(implode(', ', array_column(ChatPriorityEnum::cases(), 'value')).'; defaults to normal'),
            'due_date'     => $schema->string()->description('Due date, YYYY-MM-DD'),
            'organisation' => $schema->string()->description('Organisation code of the linked location or SKO, e.g. aw'),
            'location'     => $schema->string()->description('Location code to link the task to, e.g. 45W24'),
            'sko'          => $schema->string()->description('SKO (organisation stock) code to link the task to, e.g. SRBL-45'),
            'reference'    => $schema->string()->description('TASK-n to move instead of creating one; give status with it'),
            'status'       => $schema->string()->description('With reference: '.implode(', ', array_column(StaffTaskStatusEnum::cases(), 'value'))),
            'note'         => $schema->string()->description('With reference: posted in the task thread; required when cancelling'),
        ];
    }
}
