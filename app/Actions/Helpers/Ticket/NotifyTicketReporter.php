<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use App\Notifications\TicketReporterNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class NotifyTicketReporter
{
    use AsAction;
    use WithSlack;

    public function asked(Ticket $ticket, User $asker, string $question): void
    {
        $askedBy = $asker->contact_name ?: $asker->username;

        $this->handle(
            $ticket,
            $asker,
            __(':reference needs your reply', ['reference' => $ticket->reference]),
            [
                __(':asker needs more information to continue with :reference (:subject):', ['asker' => $askedBy, 'reference' => $ticket->reference, 'subject' => $ticket->subject]),
                Str::limit($question, 2000),
                __('If there is no reply by :deadline the ticket will be cancelled.', ['deadline' => $ticket->waiting_until?->format('d M Y H:i')]),
            ],
            __('Reply on the ticket')
        );
    }

    public function done(Ticket $ticket, ?User $actor): void
    {
        $this->handle(
            $ticket,
            $actor,
            __(':reference is done', ['reference' => $ticket->reference]),
            [
                $actor
                    ? __(':actor marked :reference (:subject) as done.', ['actor' => $actor->contact_name ?: $actor->username, 'reference' => $ticket->reference, 'subject' => $ticket->subject])
                    : __(':reference (:subject) is done.', ['reference' => $ticket->reference, 'subject' => $ticket->subject]),
                __('If something is still wrong, reply on the ticket to reopen it.'),
            ],
            __('Open the ticket')
        );
    }

    /**
     * @param array<int, string> $lines
     */
    public function handle(Ticket $ticket, ?User $actor, string $subject, array $lines, string $actionLabel): void
    {
        $reporter = $ticket->reporter;
        if (!$reporter instanceof User || $reporter->id === $actor?->id) {
            return;
        }

        $channels = Arr::get($reporter->settings, 'ticket_notifications', 'both');

        if (in_array($channels, ['both', 'email'], true) && $reporter->email) {
            $reporter->notify(new TicketReporterNotification($ticket, $subject, $lines, $actionLabel));
        }

        if (in_array($channels, ['both', 'slack'], true) && $reporter->slack_user_id && $client = $this->slackClient()) {
            $client->post('chat.postMessage', [
                'channel' => $reporter->slack_user_id,
                'text'    => '*'.$subject."*\n".implode("\n", $lines).' <'.route('grp.tickets.show', $ticket->reference).'|'.$actionLabel.'>',
            ]);
        }
    }
}
