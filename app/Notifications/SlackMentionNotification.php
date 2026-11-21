<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Events\BroadcastPersonalNotification;
use App\Models\Chat\SlackMessage;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlackMentionNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = false;

    public function __construct(public SlackMessage $message, public string $title, public string $body, public string $url)
    {
    }

    public function via($notifiable): array
    {
        BroadcastPersonalNotification::dispatch($notifiable->id, ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'route' => $this->url]);

        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->body)
            ->action(__('Open in aiku'), $this->url);
    }

    public function toArray($notifiable): array
    {
        return [
            'title'            => $this->title,
            'body'             => $this->body,
            'type'             => 'slack_mention',
            'slack_message_id' => $this->message->id,
            'route'            => $this->url,
        ];
    }
}
