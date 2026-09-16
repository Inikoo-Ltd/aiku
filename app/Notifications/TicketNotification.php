<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, string> $lines
     */
    public function __construct(public Ticket $ticket, public string $subject, public array $lines, public string $actionLabel, public bool $byEmail = true)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->subject,
            'body'  => $this->lines[0] ?? '',
            'type'      => 'ticket',
            'ticket_id' => $this->ticket->id,
            'route'     => $this->ticketUrl($notifiable),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->subject)
            ->markdown('notifications::email', ['shop' => $this->ticket->group->name, 'shop_url' => config('app.url')])
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?: $notifiable->username]));

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $message->action($this->actionLabel, $this->ticketUrl($notifiable));
    }

    private function ticketUrl($notifiable): string
    {
        return $notifiable instanceof WebUser
            ? route('retina.dropshipping.tickets.show', $this->ticket->reference, false)
            : route('grp.tickets.show', $this->ticket->reference);
    }
}
