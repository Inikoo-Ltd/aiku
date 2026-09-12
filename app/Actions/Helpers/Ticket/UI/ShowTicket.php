<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketCommentResource;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicket extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Ticket $ticket): Ticket
    {
        return $ticket;
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket);
    }

    /**
     * @return array<int, array{at: mixed, icon: string, text: string, by: string|null}>
     */
    private function timeline(Ticket $ticket): array
    {
        $audits      = $ticket->audits()->with('user')->orderBy('id')->get();
        $assigneeIds = $audits->flatMap(fn ($audit) => [$audit->old_values['assignee_id'] ?? null, $audit->new_values['assignee_id'] ?? null])->filter()->unique();
        $names       = User::whereIn('id', $assigneeIds)->get()->mapWithKeys(fn (User $user) => [$user->id => $user->contact_name ?: $user->username]);
        $statuses    = TicketStatusEnum::labels();
        $statusIcons = TicketStatusEnum::stateIcon();
        $modules     = TicketModuleEnum::labels();

        $events = [[
            'at'   => $ticket->created_at,
            'icon' => 'fal fa-plus-circle',
            'text' => __('Ticket opened'),
            'by'   => $ticket->reporter?->contact_name ?: $ticket->reporter?->username,
        ]];

        foreach ($audits->where('event', 'updated') as $audit) {
            $by = $audit->user?->contact_name ?: $audit->user?->username;
            foreach ($audit->new_values as $field => $value) {
                $old  = $audit->old_values[$field] ?? null;
                $text = match ($field) {
                    'status'          => __('Status: :from → :to', ['from' => $statuses[$old] ?? '—', 'to' => $statuses[$value] ?? $value]),
                    'assignee_id'     => $value ? __('Assigned to :name', ['name' => $names[$value] ?? '?']) : __('Unassigned'),
                    'module'          => __('Module set to :module', ['module' => $modules[$value] ?? '—']),
                    'tags'            => __('Tags changed'),
                    'subject'         => __('Subject edited'),
                    'is_confidential' => $value ? __('Marked confidential') : __('No longer confidential'),
                    default           => null,
                };
                if ($text) {
                    $events[] = [
                        'at'   => $audit->created_at,
                        'icon' => $field === 'status' ? ($statusIcons[$value]['icon'] ?? 'fal fa-exchange') : 'fal fa-pencil',
                        'text' => $text,
                        'by'   => $by,
                    ];
                }
            }
        }

        return array_reverse($events);
    }

    public function htmlResponse(Ticket $ticket): Response
    {
        return Inertia::render(
            'Tickets/Ticket',
            [
                'breadcrumbs' => $this->getBreadcrumbs($ticket),
                'title'       => $ticket->reference,
                'pageHead'    => [
                    'model' => __('Ticket'),
                    'title' => $ticket->reference,
                    'icon'  => ['fal', 'fa-life-ring'],
                ],
                'ticket'      => TicketResource::make($ticket)->toArray(request()),
                'comments'    => TicketCommentResource::collection($ticket->comments()->with('author')->orderByDesc('id')->get())->toArray(request()),
                'options'     => [
                    'statuses'   => collect(TicketStatusEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'priorities' => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'tags'       => Ticket::knownTags($ticket->group_id),
                    'kinds'      => collect(TicketKindEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'modules'    => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                    'assignees'  => User::where('group_id', $ticket->group_id)->where('status', true)->orderBy('username')->get(['id', 'username', 'contact_name'])
                        ->map(fn (User $user) => ['label' => $user->contact_name ?: $user->username, 'value' => $user->id])->values(),
                ],
                'timeline'    => $this->timeline($ticket),
                'can_rate'    => RateTicket::canRate($ticket, request()->user()),
                'can_manage'  => Ticket::canBeManagedBy(request()->user()),
                'can_assign'  => Ticket::canBeAssignedBy(request()->user()) || (Ticket::canBeManagedBy(request()->user()) && $ticket->assignee_id === request()->user()->id),
                'can_flag_confidential' => Ticket::canBeAssignedBy(request()->user()),
                'is_reporter' => $ticket->isReportedBy(request()->user()),
                'routes'      => [
                    'update'  => ['name' => 'grp.models.ticket.update', 'parameters' => ['ticket' => $ticket->id]],
                    'comment' => ['name' => 'grp.models.ticket.comment.store', 'parameters' => ['ticket' => $ticket->id]],
                    'rate'    => ['name' => 'grp.models.ticket.rate', 'parameters' => ['ticket' => $ticket->id]],
                    'escalate' => ['name' => 'grp.models.ticket.escalate', 'parameters' => ['ticket' => $ticket->id]],
                    'delete'   => ['name' => 'grp.models.ticket.delete', 'parameters' => ['ticket' => $ticket->id]],
                ],
            ]
        );
    }

    public function getBreadcrumbs(Ticket $ticket): array
    {
        return array_merge(
            IndexTickets::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tickets.show', 'parameters' => [$ticket->reference]],
                        'label' => $ticket->reference,
                    ],
                ],
            ]
        );
    }
}
