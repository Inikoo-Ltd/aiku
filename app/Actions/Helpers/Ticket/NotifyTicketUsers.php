<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Events\BroadcastRetinaTicketBadgeUpdate;
use App\Models\CRM\WebUser;
use App\Actions\SysAdmin\User\SendUserPushNotification;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Events\BroadcastTicketChanged;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use App\Notifications\TicketNotification;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class NotifyTicketUsers
{
    use AsAction;

    public function asked(Ticket $ticket, User $asker, string $question): void
    {
        $askedBy = $asker->contact_name ?: $asker->username;

        $this->handle(
            $ticket,
            $asker,
            $ticket->reporter,
            __(':reference needs your reply', ['reference' => $ticket->reference]),
            [
                __(':asker needs more information to continue with :reference (:subject):', ['asker' => $askedBy, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                Str::limit($question, 2000),
                __('If there is no reply by :deadline the ticket will be cancelled.', ['deadline' => $ticket->waiting_until?->format('d M Y H:i')]),
            ],
            __('Reply on the ticket'),
            UserNotificationEnum::TICKET_NEEDS_REPLY
        );
    }

    public function done(Ticket $ticket, ?User $actor): void
    {
        $this->handle(
            $ticket,
            $actor,
            $ticket->reporter,
            __(':reference is done', ['reference' => $ticket->reference]),
            [
                $actor
                    ? __(':actor marked :reference (:subject) as done.', ['actor' => $actor->contact_name ?: $actor->username, 'reference' => $ticket->reference, 'subject' => $ticket->subject])
                    : __(':reference (:subject) is done.', ['reference' => $ticket->reference, 'subject' => $ticket->subject]),
                __('If something is still wrong, reply on the ticket to reopen it.'),
            ],
            __('Open the ticket'),
            UserNotificationEnum::TICKET_RESOLVED
        );
    }

    public function commented(Ticket $ticket, User $author, string $body): void
    {
        $authorName = $author->contact_name ?: $author->username;
        $mentioned  = $this->mentionedUsers($ticket, $body);

        foreach ($mentioned as $user) {
            $this->handle(
                $ticket,
                $author,
                $user,
                __(':author mentioned you on :reference', ['author' => $authorName, 'reference' => $ticket->reference]),
                [
                    __(':author mentioned you on :reference (:subject):', ['author' => $authorName, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                    Str::limit($body, 2000),
                ],
                __('Open the ticket'),
                UserNotificationEnum::TICKET_MENTION
            );
        }

        if ($this->mentionsCustomer($ticket, $body)) {
            $this->notifyCustomer(
                $ticket,
                __(':author mentioned you on :reference', ['author' => $authorName, 'reference' => $ticket->reference]),
                [
                    __(':author mentioned you on :reference (:subject):', ['author' => $authorName, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                    Str::limit($body, 2000),
                ],
                __('Open the ticket')
            );

            return;
        }

        $recipient = $ticket->isReportedBy($author) ? $ticket->assignee()->first() : $ticket->reporter;
        if ($recipient instanceof User && $mentioned->contains('id', $recipient->id)) {
            return;
        }

        $this->handle(
            $ticket,
            $author,
            $recipient,
            __(':reference has a new comment', ['reference' => $ticket->reference]),
            [
                __(':author commented on :reference (:subject):', ['author' => $authorName, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                Str::limit($body, 2000),
            ],
            __('Open the ticket'),
            UserNotificationEnum::TICKET_COMMENT
        );
    }

    public function mentionsCustomer(Ticket $ticket, string $body): bool
    {
        $customerHandle = $ticket->reporter_type === 'WebUser' ? $ticket->customer?->slug : null;
        if (!$customerHandle) {
            return false;
        }

        preg_match_all('/(?<![\pL\pN._-])@([\pL\pN._-]{2,})/u', $body, $matches);

        return collect($matches[1])->map(fn (string $handle) => mb_strtolower(rtrim($handle, '.')))->contains(mb_strtolower($customerHandle));
    }

    /**
     * @param array<int, string> $lines
     */
    private function notifyCustomer(Ticket $ticket, string $subject, array $lines, string $actionLabel): void
    {
        // ponytail: customer-facing ticket notifications stay off in production until the team is ready to answer AD tickets in retina;
        // drop this guard together with the app()->isLocal() guards in GetRetinaDropshippingNavigation and GetRetinaLayout to launch it
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        if ($ticket->reporter_type !== 'WebUser' || !$ticket->customer_id) {
            return;
        }

        foreach (WebUser::where('customer_id', $ticket->customer_id)->where('status', true)->get() as $webUser) {
            $webUser->notify(new TicketNotification($ticket, $subject, $lines, $actionLabel, false));
            BroadcastRetinaTicketBadgeUpdate::dispatch($webUser->id, GetRetinaTicketBadgeData::run($webUser));
        }
    }

    public function mentionedInInternalNote(Ticket $ticket, User $author, string $body): void
    {
        $authorName = $author->contact_name ?: $author->username;

        foreach ($this->mentionedUsers($ticket, $body)->filter(fn (User $user) => $ticket->canSeeInternalNotesBy($user)) as $user) {
            $this->handle(
                $ticket,
                $author,
                $user,
                __(':author mentioned you in an internal note on :reference', ['author' => $authorName, 'reference' => $ticket->reference]),
                [
                    __(':author mentioned you in an internal note on :reference (:subject):', ['author' => $authorName, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                    Str::limit($body, 2000),
                ],
                __('Open the ticket'),
                UserNotificationEnum::TICKET_MENTION
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function mentionedUsers(Ticket $ticket, string $body): \Illuminate\Support\Collection
    {
        preg_match_all('/(?<![\pL\pN._-])@([\pL\pN._-]{2,})/u', $body, $matches);
        $handles = collect($matches[1])->map(fn (string $handle) => mb_strtolower(rtrim($handle, '.')))->unique()->values();

        if ($handles->isEmpty()) {
            return collect();
        }

        return User::where('group_id', $ticket->group_id)
            ->where('status', true)
            ->where(fn ($query) => $query->whereIn(\DB::raw('lower(username)'), $handles)->orWhereIn(\DB::raw('lower(nickname)'), $handles))
            ->get()
            ->filter(fn (User $user) => $ticket->isVisibleTo($user))
            ->values();
    }

    public function raised(Ticket $ticket): void
    {
        foreach (GetTicketBadgeData::engineers($ticket->group_id) as $engineer) {
            $this->handle(
                $ticket,
                $ticket->reporter instanceof User ? $ticket->reporter : null,
                $engineer,
                __('New ticket :reference', ['reference' => $ticket->reference]),
                [$ticket->subject],
                __('Open the ticket')
            );
        }
    }

    public function qaChanged(Ticket $ticket, User $actor): void
    {
        if ($ticket->qa_status === TicketQaStatusEnum::REQUESTED) {
            foreach ($ticket->qaUser ? [$ticket->qaUser] : GetTicketBadgeData::qaUsers($ticket->group_id) as $qaUser) {
                $this->handle($ticket, $actor, $qaUser, __(':reference is ready for QA', ['reference' => $ticket->reference]), [$ticket->subject], __('Check the ticket'));
            }

            return;
        }

        if ($ticket->qa_status) {
            $verdict = TicketQaStatusEnum::labels()[$ticket->qa_status->value];
            $this->handle($ticket, $actor, $ticket->assignee()->first(), __(':reference: QA :verdict', ['reference' => $ticket->reference, 'verdict' => strtolower($verdict)]), [$ticket->subject], __('Open the ticket'));
        }
    }

    public function statusChanged(Ticket $ticket, ?User $actor): void
    {
        $statusLabel = TicketStatusEnum::labels()[$ticket->status->value];

        $this->handle(
            $ticket,
            $actor,
            $ticket->reporter,
            __(':reference is now :status', ['reference' => $ticket->reference, 'status' => $statusLabel]),
            [
                $actor
                    ? __(':actor moved :reference (:subject) to :status.', ['actor' => $actor->contact_name ?: $actor->username, 'reference' => $ticket->reference, 'subject' => $ticket->subject, 'status' => $statusLabel])
                    : __(':reference (:subject) is now :status.', ['reference' => $ticket->reference, 'subject' => $ticket->subject, 'status' => $statusLabel]),
            ],
            __('Open the ticket')
        );
    }

    public function collaboratorAdded(Ticket $ticket, User $collaborator, ?User $actor): void
    {
        $this->handle(
            $ticket,
            $actor,
            $collaborator,
            __('You were added to :reference', ['reference' => $ticket->reference]),
            [$ticket->subject],
            __('Open the ticket')
        );
    }

    public function pushBadges(Ticket $ticket, ?User $actor = null): void
    {
        BroadcastTicketChanged::dispatch($ticket);

        $previousAssigneeId  = $ticket->wasChanged('assignee_id') ? ($ticket->getPrevious()['assignee_id'] ?? null) : null;
        $changesQueueCounts  = $ticket->wasRecentlyCreated || $ticket->wasChanged(['status', 'assignee_id', 'qa_status', 'kind', 'is_confidential']);

        $users = collect([$ticket->reporter, $ticket->assignee()->first(), $previousAssigneeId ? User::find($previousAssigneeId) : null, $actor])
            ->merge($ticket->collaborators()->get())
            ->when($changesQueueCounts, fn ($users) => $users
                ->merge(GetTicketBadgeData::engineers($ticket->group_id))
                ->merge(GetTicketBadgeData::qaUsers($ticket->group_id)))
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id');

        SendTicketBadgeUpdateToUsers::run($users->pluck('id')->values()->all());
    }

    /**
     * @param array<int, string> $lines
     */
    public function handle(Ticket $ticket, ?User $actor, mixed $recipient, string $subject, array $lines, string $actionLabel, ?UserNotificationEnum $event = null): void
    {
        if ($recipient instanceof WebUser) {
            $this->notifyCustomer($ticket, $subject, $lines, $actionLabel);

            return;
        }

        if (!$recipient instanceof User || $recipient->id === $actor?->id) {
            return;
        }

        $channels = $event?->channelsFor($recipient) ?? [];

        $recipient->notify(new TicketNotification($ticket, $subject, $lines, $actionLabel, in_array('email', $channels, true) && (bool) $recipient->email));

        SendTicketBadgeUpdateToUsers::run([$recipient->id], [
            'title' => $subject,
            'body'  => $lines[0] ?? '',
            'route' => route('grp.tickets.show', $ticket->reference),
        ]);

        if (in_array('slack', $channels, true) && $recipient->slack_user_id) {
            SendTicketSlackDirectMessage::dispatch($recipient, '*'.$subject."*\n".implode("\n", $lines).' <'.route('grp.tickets.show', $ticket->reference).'|'.$actionLabel.'>');
        }

        if (in_array('browser', $channels, true) && $recipient->pushSubscriptions()->exists()) {
            SendUserPushNotification::dispatch($recipient, [
                'title' => $subject,
                'body'  => Str::limit($lines[0] ?? '', 200),
                'url'   => route('grp.tickets.show', $ticket->reference),
                'tag'   => $ticket->reference,
            ]);
        }
    }
}
