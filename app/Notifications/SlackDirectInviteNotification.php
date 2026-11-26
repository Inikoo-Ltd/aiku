<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Events\BroadcastPersonalNotification;
use Illuminate\Notifications\Notification;

class SlackDirectInviteNotification extends Notification
{
    public function __construct(public string $title, public string $body, public string $url)
    {
    }

    public function via($notifiable): array
    {
        BroadcastPersonalNotification::dispatch($notifiable->id, ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'route' => $this->url]);

        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'slack_direct_invite',
            'route' => $this->url,
        ];
    }
}
