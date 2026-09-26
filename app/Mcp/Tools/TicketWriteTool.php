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
use App\Actions\Helpers\Ticket\UpdateTicketComment;
use App\Http\Resources\Helpers\TicketResource;
use App\Enums\Helpers\Ticket\TicketCommentTypeEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Change a ticket or create a help ticket. With a reference: add a comment (posted as you, optionally with attachments as base64 files; internal=true keeps it visible to the help desk only, for technical notes: ids repaired, commands run, root cause), correct a comment you already posted by passing its comment_id with the rewritten comment instead of posting a follow-up, rewrite subject or description, change status (open, in_progress, waiting with optional waiting_hours, resolved, cancelled), priority, assignee (username), kind, module or tags. Without a reference: creates a new HELP ticket (or an INI engineer ticket with type=engineer) with subject, and optional description, kind, module, priority. Every change is recorded as the authenticated user, or as the user named in acting_as when a help desk supervisor passes it. Only engineers, lead engineers and QA can use it.')]
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
            'comment_id'  => ['sometimes', 'integer'],
            'internal'    => ['sometimes', 'boolean'],
            'post_mortem' => ['sometimes', 'boolean'],
            'status'      => ['sometimes', 'in:open,in_progress,waiting,resolved,pending_deploy,cancelled'],
            'priority'    => ['sometimes', 'in:low,normal,high,urgent'],
            'assignee'    => ['sometimes', 'nullable', 'string'],
            'kind'        => ['sometimes', 'nullable', Rule::enum(TicketKindEnum::class)],
            'type'        => ['sometimes', 'nullable', 'in:help,engineer'],
            'module'      => ['sometimes', 'nullable', 'string'],
            'tags'        => ['sometimes', 'array'],
            'tags.*'      => ['string', 'max:64'],
            'acting_as'   => ['sometimes', 'nullable', 'string'],
            'waiting_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'commit'        => ['sometimes', 'string', 'regex:/^[0-9a-f]{7,40}$/i'],
            'attachments'   => ['sometimes', 'array', 'max:5'],
            'attachments.*.name'   => ['required', 'string', 'max:255'],
            'attachments.*.base64' => ['required', 'string', 'max:14000000'],
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
            if ($request->get('type') === TicketTypeEnum::ENGINEER->value && !Ticket::canChooseType($user)) {
                return Response::error('Only the help desk and QA can raise engineer (INI) tickets.');
            }

            $ticket = StoreTicket::make()->action($user->group, array_filter([
                'subject'       => $request->string('subject')->toString(),
                'description'   => $request->get('description'),
                'type'          => $request->get('type'),
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
        if ($request->boolean('post_mortem') && !$ticket->canContributeBy($user)) {
            return Response::error('Only the assignee, collaborators and lead engineers can write incident post-mortems.');
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
        if ($isClosingAfterDeployment && $request->filled('commit')) {
            $changes['deploy_commit'] = $request->string('commit')->toString();
        }

        if ($changes) {
            $ticket = UpdateTicket::make()->action($ticket, $changes);
        }

        if ($request->filled('comment_id')) {
            if (!$request->filled('comment')) {
                return Response::error('Pass the rewritten text in comment when editing comment_id.');
            }
            $ticketComment = $ticket->comments()->find($request->integer('comment_id'));
            if (!$ticketComment) {
                return Response::error('No comment '.$request->integer('comment_id').' on '.$ticket->reference.'.');
            }
            if (!$ticketComment->isAuthoredBy($user)) {
                return Response::error('You can only edit a comment you wrote yourself.');
            }
            UpdateTicketComment::make()->action($ticketComment, ['body' => $request->string('comment')->toString()]);

            return Response::json(['updated' => $ticket->reference, 'changes' => array_keys($changes), 'edited' => $ticketComment->id, 'ticket' => TicketResource::make($ticket->fresh())->resolve()]);
        }

        $attachments = $this->decodeAttachments($request->get('attachments', []));
        if ($attachments === null) {
            return Response::error('An attachment is not valid base64.');
        }

        if (($request->filled('comment') || $attachments) && !$isClosingAfterDeployment) {
            if (!$ticket->assignee_id) {
                return Response::error("$ticket->reference has no assignee. Assign it before commenting.");
            }
            StoreTicketComment::make()->action($ticket, $user, [
                'body'        => $request->string('comment')->toString(),
                'is_internal' => $request->boolean('internal'),
                'type'        => $request->boolean('post_mortem') ? TicketCommentTypeEnum::POST_MORTEM->value : TicketCommentTypeEnum::COMMENT->value,
                'images'      => $attachments,
            ]);
        }

        return Response::json(['updated' => $ticket->reference, 'changes' => array_keys($changes), 'commented' => $request->filled('comment'), 'attached' => count($attachments), 'ticket' => TicketResource::make($ticket->fresh())->resolve()]);
    }

    /**
     * @param  array<int, array{name: string, base64: string}>  $attachments
     * @return array<int, UploadedFile>|null
     */
    private function decodeAttachments(array $attachments): ?array
    {
        $files = [];
        foreach ($attachments as $attachment) {
            $content = base64_decode($attachment['base64'], true);
            if ($content === false) {
                return null;
            }
            $path = tempnam(sys_get_temp_dir(), 'ticket-attachment-');
            file_put_contents($path, $content);
            $files[] = new UploadedFile($path, $attachment['name'], mime_content_type($path) ?: null, null, true);
        }

        return $files;
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
            'comment'     => $schema->string()->description('Comment to add to the ticket, posted as you. With comment_id, the text that replaces that comment'),
            'comment_id'  => $schema->integer()->description('Id of one of your own comments on this ticket: its text is replaced by comment, rather than a new comment being added. Use it to correct something you already posted instead of following it with a correction'),
            'internal'    => $schema->boolean()->description('true = internal comment, visible to the help desk only. Use it for technical notes (ids repaired, commands run, root cause) so the public thread stays readable for the reporter'),
            'post_mortem' => $schema->boolean()->description('true = the comment is an incident post-mortem (what broke, who was affected, root cause, fix, how it is prevented), shown highlighted in red on the ticket. Combine with internal for a help-desk-only post-mortem'),
            'status'      => $schema->string()->description('open, in_progress, waiting, resolved, pending_deploy or cancelled. pending_deploy = close after next deployment (fix already on main): the comment is held and posted when the deployment closes the ticket. Pass commit too'),
            'priority'    => $schema->string()->description('low, normal, high or urgent'),
            'assignee'    => $schema->string()->description('Username to assign, empty string to unassign'),
            'type'        => $schema->string()->description('New tickets only: help (HELP-n, default) or engineer (INI-n, engineering work such as upgrades, refactors and tech debt)'),
            'kind'        => $schema->string()->description('escalation, bug, feature, task (engineer to engineer) or qa (engineer to QA)'),
            'module'      => $schema->string()->description('Aiku module slug, e.g. dispatching'),
            'tags'        => $schema->array()->description('Full tag list to set, e.g. ["not a bug"]')->items($schema->string()),
            'acting_as'   => $schema->string()->description('Username to act as: the comment, assignment or status change is recorded as that user. Help desk supervisors only'),
            'commit'        => $schema->string()->description('With status pending_deploy: hash of the commit that fixes the ticket. Only a deployment that includes it closes the ticket; without it the next deployment does'),
            'waiting_hours' => $schema->integer()->description('With status waiting: hours before the ticket resurfaces (1-720). Defaults to the ticket kind\'s waiting period'),
            'attachments'   => $schema->array()->description('Up to 5 files to attach to the comment (images, PDF, Word, Excel, CSV, video, archives; 10 MB each), each as {name, base64}')->items(
                $schema->object(['name' => $schema->string()->description('File name with extension, e.g. proof.png'), 'base64' => $schema->string()->description('Base64-encoded file content')])
            ),
        ];
    }
}
