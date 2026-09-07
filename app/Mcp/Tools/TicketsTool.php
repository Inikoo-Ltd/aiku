<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Http\Resources\Helpers\TicketCommentResource;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Aiku tickets. Pass a reference (HELP-123 or AD-45) to get one ticket with its comments; otherwise list tickets, most urgent and most recently updated first. Types: customer (AD, from customers) and help (HELP, internal: bugs, feature requests, escalations). Confidential tickets are hidden unless the user reported them, is assigned or is admin.')]
#[IsReadOnly]
class TicketsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'reference' => ['sometimes', 'string'],
            'type'      => ['sometimes', 'in:customer,help'],
            'status'    => ['sometimes', 'string'],
            'priority'  => ['sometimes', 'string'],
            'module'    => ['sometimes', 'string'],
            'kind'      => ['sometimes', 'string'],
            'tag'       => ['sometimes', 'string'],
            'mine'      => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'string'],
            'limit'     => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user  = $request->user();
        $query = Ticket::where('group_id', $user->group_id)->visibleTo($user);

        if ($request->filled('reference')) {
            $ticket = (clone $query)->where('reference', strtoupper($request->string('reference')))->first();
            if (!$ticket) {
                return Response::error('Ticket not found or not visible to you.');
            }

            return Response::json([
                'ticket'   => TicketResource::make($ticket)->resolve(),
                'comments' => TicketCommentResource::collection($ticket->comments()->with('author')->orderBy('id')->get())->resolve(),
            ]);
        }

        $query
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', explode(',', $request->string('status'))), fn ($query) => $query->whereNotIn('status', ['resolved', 'closed']))
            ->when($request->filled('priority'), fn ($query) => $query->whereIn('priority', explode(',', $request->string('priority'))))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->when($request->filled('tag'), fn ($query) => $query->whereJsonContains('tags', $request->string('tag')->toString()))
            ->when($request->boolean('mine'), fn ($query) => $query->where(fn ($query) => $query->where('assignee_id', $user->id)->orWhere(fn ($query) => $query->where('reporter_type', 'User')->where('reporter_id', $user->id))))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query->whereRaw('subject ILIKE ?', ['%'.$request->string('search').'%'])->orWhereRaw('description ILIKE ?', ['%'.$request->string('search').'%'])));

        $tickets = $query
            ->orderByRaw("array_position(array['urgent','high','normal','low'], priority::text)")
            ->orderByDesc('updated_at')
            ->limit($request->integer('limit', 20))
            ->get()
            ->map(fn (Ticket $ticket) => [
                'reference'   => $ticket->reference,
                'subject'     => $ticket->subject,
                'status'      => $ticket->status->value,
                'priority'    => $ticket->priority->value,
                'kind'        => $ticket->kind?->value,
                'module'      => $ticket->module?->value,
                'tags'        => $ticket->tags,
                'shop'        => $ticket->shop?->slug,
                'customer'    => $ticket->customer?->name,
                'reporter'    => $ticket->reporter?->contact_name ?: $ticket->reporter?->username,
                'assignee'    => $ticket->assignee?->username,
                'updated_at'  => $ticket->updated_at?->toDateTimeString(),
                'reference_url' => data_get($ticket->data, 'reference_url'),
            ]);

        return Response::json(['count' => $tickets->count(), 'tickets' => $tickets]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->description('Ticket reference, e.g. HELP-3074 or AD-1697. Returns the full ticket with comments.'),
            'type'      => $schema->string()->description('customer or help'),
            'status'    => $schema->string()->description('Comma list of open,in_progress,waiting,resolved,closed. Default: everything not resolved or closed'),
            'priority'  => $schema->string()->description('Comma list of urgent,high,normal,low'),
            'module'    => $schema->string()->description('Aiku module, e.g. dispatching, crm, ordering'),
            'kind'      => $schema->string()->description('escalation, bug or feature'),
            'tag'       => $schema->string()->description('Only tickets carrying this tag'),
            'mine'      => $schema->boolean()->description('Only tickets the user reported or is assigned'),
            'search'    => $schema->string()->description('Text in subject or description'),
            'limit'     => $schema->integer()->description('Maximum tickets, default 20')->min(1)->max(100),
        ];
    }
}
