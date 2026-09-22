<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\Helpers\Ticket\MarkTicketNotificationsAsRead;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Http\Resources\Helpers\TicketCommentResource;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTicket extends OrgAction
{
    use WithTicketsScope;

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

        return $this->openTicket($ticket, $request);
    }

    public function inOrganisation(Organisation $organisation, Ticket $ticket, ActionRequest $request): Ticket
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromTicketsScope($request, $organisation);

        return $this->openTicket($ticket, $request);
    }

    public function inShop(Organisation $organisation, Shop $shop, Ticket $ticket, ActionRequest $request): Ticket
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromTicketsScope($request, $organisation, $shop);

        return $this->openTicket($ticket, $request);
    }

    private function openTicket(Ticket $ticket, ActionRequest $request): Ticket
    {
        MarkTicketNotificationsAsRead::run($ticket, $request->user());

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
                    'qa_status'       => $value ? TicketQaStatusEnum::labels()[$value] : __('QA check withdrawn'),
                    'collaborators' => $value ? __('Collaborators: :names', ['names' => $value]) : __('Collaborators removed'),
                    default           => null,
                };
                if ($text) {
                    $events[] = [
                        'at'   => $audit->created_at,
                        'icon' => match ($field) {
                            'status'    => $statusIcons[$value]['icon'] ?? 'fal fa-exchange',
                            'qa_status' => TicketQaStatusEnum::stateIcon()[$value]['icon'] ?? 'fal fa-vial',
                            'collaborators' => 'fal fa-users',
                            default     => 'fal fa-pencil',
                        },
                        'text' => $text,
                        'by'   => $by,
                    ];
                }
            }
        }

        return array_reverse($events);
    }

    /**
     * @return array<int, array{username: string, name: string|null, suggested: bool, is_customer: bool}>
     */
    public function mentionableFor(Ticket $ticket): array
    {
        $involvedStaffIds = collect([$ticket->reporter_type === 'User' ? $ticket->reporter : null, $ticket->assignee()->first()])
            ->merge($ticket->collaborators()->get())
            ->merge(GetTicketBadgeData::leadEngineers($ticket->group_id))
            ->filter(fn ($person) => $person instanceof User)
            ->pluck('id')
            ->unique()
            ->all();

        $customer = $ticket->reporter_type === 'WebUser' && $ticket->customer?->slug
            ? [['username' => $ticket->customer->slug, 'name' => $ticket->customer->name, 'suggested' => true, 'is_customer' => true]]
            : [];

        $staff = User::where('group_id', $ticket->group_id)
            ->where('status', true)
            ->orderBy('username')
            ->get(['id', 'username', 'contact_name', 'group_id'])
            ->when($ticket->is_confidential, fn ($users) => $users->filter(fn (User $mentionableUser) => $ticket->isVisibleTo($mentionableUser)))
            ->map(fn (User $mentionableUser) => [
                'username'    => $mentionableUser->username,
                'name'        => $mentionableUser->contact_name,
                'suggested'   => in_array($mentionableUser->id, $involvedStaffIds, true),
                'is_customer' => false,
            ])
            ->sortByDesc('suggested');

        return collect($customer)->merge($staff)->values()->all();
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
                    'wrapped_actions' => Ticket::canBeAssignedBy(request()->user()) ? [['type' => 'button', 'key' => 'delete']] : [],
                ],
                'comments'    => TicketCommentResource::collection($ticket->commentsVisibleTo(request()->user())->with('author', 'ticket')->orderByDesc('id')->get())->toArray(request()),
                'timeline'    => $this->timeline($ticket),
                'can_rate'    => RateTicket::canRate($ticket, request()->user()),
                'comments_newest_first' => (bool) data_get(request()->user()->settings, 'ticket_comments_newest_first', true),
                'history_newest_first'  => (bool) data_get(request()->user()->settings, 'ticket_history_newest_first', true),
                ...$this->controlProps($ticket),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function controlProps(Ticket $ticket): array
    {
        $user = request()->user();

        return [
            'ticket'  => TicketResource::make($ticket)->toArray(request()),
            'options' => [
                'statuses'   => collect(TicketStatusEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'priorities' => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'tags'       => Ticket::knownTags($ticket->group_id),
                'kinds'      => collect(TicketKindEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'modules'    => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'qa_users'      => GetTicketBadgeData::qaUsers($ticket->group_id)
                    ->map(fn (User $person) => [
                        'label'  => strtok((string) ($person->contact_name ?: $person->username), ' '),
                        'value'  => $person->id,
                        'avatar' => $person->imageSources(48, 48),
                    ])->sortBy('label')->values(),
                'collaborators' => GetTicketBadgeData::engineers($ticket->group_id)
                    ->merge(GetTicketBadgeData::qaUsers($ticket->group_id))
                    ->unique('id')
                    ->map(fn (User $person) => [
                        'label'  => $person->contact_name ?: $person->username,
                        'value'  => $person->id,
                        'avatar' => $person->imageSources(48, 48),
                    ])->sortBy('label')->values(),
                'assignees'  => GetTicketBadgeData::engineers($ticket->group_id)
                    ->map(fn (User $engineer) => [
                        'label'  => strtok((string) ($engineer->contact_name ?: $engineer->username), ' '),
                        'value'  => $engineer->id,
                        'avatar' => $engineer->imageSources(48, 48),
                        'is_me'  => $engineer->id === $user->id,
                    ])->sortBy('label')->values(),
                'developers' => GetTicketBadgeData::engineers($ticket->group_id)
                    ->map(fn (User $engineer) => [
                        'username' => $engineer->username,
                        'name'     => $engineer->contact_name ?: $engineer->username,
                    ])->sortBy('name')->values(),
                'mentionable' => $this->mentionableFor($ticket),
            ],
            'can_manage'             => Ticket::canBeManagedBy($user),
            'can_assign'             => $ticket->canChangeAssigneeBy($user),
            'can_flag_confidential'  => Ticket::canBeAssignedBy($user),
            'can_qa'                 => Ticket::canCheckQa($user),
            'is_reporter'            => $ticket->isReportedBy($user),
            'can_cancel_as_reporter' => $ticket->canBeCancelledByReporter($user),
            'can_reopen_as_reporter' => $ticket->canBeReopenedByReporter($user),
            'can_change_kind_module' => $ticket->canChangeKindAndModuleBy($user),
            'can_update'               => $ticket->canBeUpdatedBy($user),
            'can_contribute'           => $ticket->canContributeBy($user),
            'can_manage_collaborators' => $ticket->canManageCollaboratorsBy($user),
            'can_preview_attachments' => $ticket->canPreviewAttachmentsBy($user),
            'can_comment_internally' => $ticket->canWriteEngineeringNotesBy($user),
            'attachment_gallery'     => $ticket->attachmentGalleryFor($user),
            'routes'                 => [
                'update'   => ['name' => 'grp.models.ticket.update', 'parameters' => ['ticket' => $ticket->id]],
                'collaborators' => ['name' => 'grp.models.ticket.collaborators.update', 'parameters' => ['ticket' => $ticket->id]],
                'comment'  => ['name' => 'grp.models.ticket.comment.store', 'parameters' => ['ticket' => $ticket->id]],
                'rate'     => ['name' => 'grp.models.ticket.rate', 'parameters' => ['ticket' => $ticket->id]],
                'delete'   => ['name' => 'grp.models.ticket.delete', 'parameters' => ['ticket' => $ticket->id]],
            ],
        ];
    }

    public function getBreadcrumbs(Ticket $ticket): array
    {
        return array_merge(
            $this->ticketsListBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->ticketsRoute('show', [$ticket->reference]),
                        'label' => $ticket->reference,
                    ],
                ],
            ]
        );
    }
}
