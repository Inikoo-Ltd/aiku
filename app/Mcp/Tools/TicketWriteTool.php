<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\Ticket\Concerns\WithTicketsWriteGuard;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Change a ticket or create a help ticket. With a reference: add a comment (internal by default, public reaches the customer on AD tickets), change status (open, in_progress, waiting, resolved, closed), priority, assignee (username), kind, module or tags. Without a reference: creates a new HELP ticket with subject, and optional description, kind, module, priority. Every change is recorded as the authenticated user.')]
class TicketWriteTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'reference'   => ['sometimes', 'string'],
            'subject'     => ['required_without:reference', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'comment'     => ['sometimes', 'string'],
            'public'      => ['sometimes', 'boolean'],
            'status'      => ['sometimes', 'in:open,in_progress,waiting,resolved,closed'],
            'priority'    => ['sometimes', 'in:low,normal,high,urgent'],
            'assignee'    => ['sometimes', 'nullable', 'string'],
            'kind'        => ['sometimes', 'nullable', 'in:escalation,bug,feature'],
            'module'      => ['sometimes', 'nullable', 'string'],
            'tags'        => ['sometimes', 'array'],
            'tags.*'      => ['string', 'max:64'],
        ]);

        $user = $request->user();

        if (!$request->filled('reference')) {
            if (WithTicketsWriteGuard::ticketsAreReadOnly()) {
                return Response::error(WithTicketsWriteGuard::readOnlyMessage());
            }

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
        if (WithTicketsWriteGuard::ticketsAreReadOnly($ticket->type)) {
            return Response::error(WithTicketsWriteGuard::readOnlyMessage());
        }

        $changes = array_filter([
            'status'   => $request->get('status'),
            'priority' => $request->get('priority'),
            'kind'     => $request->get('kind'),
            'module'   => $request->get('module'),
            'tags'     => $request->get('tags'),
        ], fn ($value) => $value !== null);

        if ($request->has('assignee')) {
            $username = $request->get('assignee');
            $assignee = $username ? $user->group->users()->where('username', $username)->first() : null;
            if ($username && !$assignee) {
                return Response::error("No user with username $username.");
            }
            $changes['assignee_id'] = $assignee?->id;
        }

        if ($changes) {
            UpdateTicket::make()->action($ticket, $changes);
        }

        if ($request->filled('comment')) {
            StoreTicketComment::make()->action($ticket, $user, [
                'body'        => $request->string('comment')->toString(),
                'is_internal' => !$request->boolean('public'),
            ]);
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
            'subject'     => $schema->string()->description('Subject for a new ticket'),
            'description' => $schema->string()->description('Description for a new ticket'),
            'comment'     => $schema->string()->description('Comment to add to the ticket'),
            'public'      => $schema->boolean()->description('Make the comment visible to the customer (AD tickets). Default false: internal note'),
            'status'      => $schema->string()->description('open, in_progress, waiting, resolved or closed'),
            'priority'    => $schema->string()->description('low, normal, high or urgent'),
            'assignee'    => $schema->string()->description('Username to assign, empty string to unassign'),
            'kind'        => $schema->string()->description('escalation, bug or feature'),
            'module'      => $schema->string()->description('Aiku module slug, e.g. dispatching'),
            'tags'        => $schema->array()->description('Full tag list to set, e.g. ["not a bug"]')->items($schema->string()),
        ];
    }
}
