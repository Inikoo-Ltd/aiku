<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Http\Resources\Helpers\TicketResource;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Change a ticket or create a help ticket. With a reference: add a comment (posted as you; internal=true keeps it visible to the help desk only, for technical notes: ids repaired, commands run, root cause), rewrite subject or description, change status (open, in_progress, waiting with optional waiting_hours, resolved, cancelled), priority, assignee (username), kind, module or tags. Without a reference: creates a new HELP ticket with subject, and optional description, kind, module, priority. Every change is recorded as the authenticated user, or as the user named in acting_as when a help desk supervisor passes it. Only engineers, lead engineers and QA can use it.')]
class TicketWriteTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return Ticket::canUseAssistant($request->user());
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'reference'   => ['sometimes', 'string'],
            'subject'     => ['required_without:reference', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'comment'     => ['sometimes', 'string'],
            'internal'    => ['sometimes', 'boolean'],
            'status'      => ['sometimes', 'in:open,in_progress,waiting,resolved,pending_deploy,cancelled'],
            'priority'    => ['sometimes', 'in:low,normal,high,urgent'],
            'assignee'    => ['sometimes', 'nullable', 'string'],
            'kind'        => ['sometimes', 'nullable', Rule::enum(TicketKindEnum::class)],
            'module'      => ['sometimes', 'nullable', 'string'],
            'tags'        => ['sometimes', 'array'],
            'tags.*'      => ['string', 'max:64'],
            'acting_as'   => ['sometimes', 'nullable', 'string'],
            'waiting_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $user = $request->user();

        if ($request->filled('acting_as')) {
            if (!Ticket::canBeAssignedBy($user)) {
                return Response::error('Only a help desk supervisor can act as another user.');
            }
            $actingUser = $user->group->users()->where('username', $request->string('acting_as')->toString())->first();
            if (!$actingUser) {
                return Response::error('No user with username '.$request->string('acting_as').'.');
            }
            auth()->setUser($actingUser);
            $user = $actingUser;
        }

        if (!$request->filled('reference')) {
            $ticket = StoreTicket::make()->action($user->group, array_filter([
                'subject'       => $request->string('subject')->toString(),
                'description'   => $request->get('description'),
                'kind'          => $request->get('kind', 'bug'),
                'module'        => $request->get('module'),
                'priority'      => $request->get('priority'),
                'tags'          => $request->get('tags'),
                'reporter_type' => 'User',
                'reporter_id'   => $user->id,
            ]));

            return Response::json(['created' => $ticket->reference, 'ticket' => TicketResource::make($ticket)->resolve()]);
        }

        $ticket = Ticket::where('group_id', $user->group_id)->visibleTo($user)->where('reference', strtoupper($request->string('reference')))->first();
        if (!$ticket) {
            return Response::error('Ticket not found or not visible to you.');
        }
        if (!Ticket::canBeManagedBy($user) && !$ticket->isReportedBy($user) && $request->hasAny(['subject', 'description'])) {
            return Response::error('Only the help desk or the reporter can change the subject or description. You can comment on it.');
        }
        if ($request->hasAny(['status', 'priority', 'kind', 'module', 'assignee']) && !$ticket->canBeUpdatedBy($user)) {
            return Response::error('Only the engineer assigned to the ticket or a lead engineer can change its status, priority, kind, module or assignee. You can comment on it.');
        }
        if ($request->has('tags') && !$ticket->canContributeBy($user)) {
            return Response::error('Only the people working on the ticket can change its tags. You can comment on it.');
        }
        if ($request->boolean('internal') && !$ticket->canContributeBy($user)) {
            return Response::error('Only the assignee, collaborators and lead engineers can write internal notes.');
        }
        if (!Ticket::canBeAssignedBy($user) && $request->has('assignee') && !($ticket->assignee_id === $user->id && $request->filled('assignee'))) {
            return Response::error('Only a help desk supervisor hands out unassigned tickets. You can pass a ticket assigned to you on to a colleague.');
        }

        $changes = array_filter([
            'subject'     => $request->get('subject'),
            'description' => $request->get('description'),
            'status'   => $request->get('status'),
            'priority' => $request->get('priority'),
            'kind'     => $request->get('kind'),
            'module'   => $request->get('module'),
            'tags'     => $request->get('tags'),
            'waiting_hours' => $request->get('waiting_hours'),
        ], fn ($value) => $value !== null);

        if ($request->has('assignee')) {
            $username = $request->get('assignee');
            $assignee = $username ? $user->group->users()->where('username', $username)->first() : null;
            if ($username && !$assignee) {
                return Response::error("No user with username $username.");
            }
            $changes['assignee_id'] = $assignee?->id;
        }

        $isClosingAfterDeployment = $request->get('status') === 'pending_deploy';
        if ($isClosingAfterDeployment && $request->filled('comment')) {
            $changes['question'] = $request->string('comment')->toString();
        }

        if ($changes) {
            $ticket = UpdateTicket::make()->action($ticket, $changes);
        }

        if ($request->filled('comment') && !$isClosingAfterDeployment) {
            if (!$ticket->assignee_id) {
                return Response::error("$ticket->reference has no assignee. Assign it before commenting.");
            }
            StoreTicketComment::make()->action($ticket, $user, ['body' => $request->string('comment')->toString(), 'is_internal' => $request->boolean('internal')]);
        }

        return Response::json(['updated' => $ticket->reference, 'changes' => array_keys($changes), 'commented' => $request->filled('comment'), 'ticket' => TicketResource::make($ticket->fresh())->resolve()]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference'   => $schema->string()->description('Ticket to change, e.g. HELP-3074. Omit to create a new HELP ticket'),
            'subject'     => $schema->string()->description('Subject: for a new ticket, or to rewrite it on an existing one'),
            'description' => $schema->string()->description('Description: for a new ticket, or to rewrite it on an existing one'),
            'comment'     => $schema->string()->description('Comment to add to the ticket, posted as you'),
            'internal'    => $schema->boolean()->description('true = internal comment, visible to the help desk only. Use it for technical notes (ids repaired, commands run, root cause) so the public thread stays readable for the reporter'),
            'status'      => $schema->string()->description('open, in_progress, waiting, resolved, pending_deploy or cancelled. pending_deploy = close after next deployment (fix already on main): the comment is held and posted when the deployment closes the ticket'),
            'priority'    => $schema->string()->description('low, normal, high or urgent'),
            'assignee'    => $schema->string()->description('Username to assign, empty string to unassign'),
            'kind'        => $schema->string()->description('escalation, bug, feature, task (engineer to engineer) or qa (engineer to QA)'),
            'module'      => $schema->string()->description('Aiku module slug, e.g. dispatching'),
            'tags'        => $schema->array()->description('Full tag list to set, e.g. ["not a bug"]')->items($schema->string()),
            'acting_as'   => $schema->string()->description('Username to act as: the comment, assignment or status change is recorded as that user. Help desk supervisors only'),
            'waiting_hours' => $schema->integer()->description('With status waiting: hours before the ticket resurfaces (1-720). Defaults to the ticket kind\'s waiting period'),
        ];
    }
}
