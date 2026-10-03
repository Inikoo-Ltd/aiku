<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Events\BroadcastPersonalNotification;
use App\Models\Helpers\TicketProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketProjectNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TicketProject $project, public string $title, public string $body)
    {
    }

    public function via($notifiable): array
    {
        BroadcastPersonalNotification::dispatch($notifiable->id, ['id' => null, 'title' => $this->title, 'body' => $this->body, 'route' => $this->projectUrl()]);

        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'ticket_project',
            'route' => $this->projectUrl(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->title)
            ->markdown('notifications::email', ['shop' => $this->project->group->name, 'shop_url' => config('app.url')])
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?: $notifiable->username]));

        if (app()->isProduction()) {
            $message->mailer('ses')->from('help@aiku.io', 'Aiku Help');
        }

        foreach (preg_split('/\R{2,}/', $this->body) as $paragraph) {
            $message->line($paragraph);
        }

        return $message->action(__('Open the project'), $this->projectUrl());
    }

    private function projectUrl(): string
    {
        return route('grp.tickets.projects.show', $this->project->slug);
    }
}
