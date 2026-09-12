<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Helpers\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReporterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, string> $lines
     */
    public function __construct(public Ticket $ticket, public string $subject, public array $lines, public string $actionLabel)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?: $notifiable->username]));

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $message->action($this->actionLabel, route('grp.tickets.show', $this->ticket->reference));
    }
}
