<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Events\BroadcastPersonalNotification;
use App\Models\Tasks\StaffTask;
use Illuminate\Notifications\Notification;

class StaffTaskNotification extends Notification
{
    public function __construct(public StaffTask $task, public string $title, public string $body)
    {
    }

    public function via($notifiable): array
    {
        BroadcastPersonalNotification::dispatch($notifiable->id, ['id' => null, 'title' => $this->title, 'body' => $this->body, 'route' => route('grp.tasks.index', ['task' => $this->task->reference])]);

        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'staff_task',
            'route' => route('grp.tasks.index', ['task' => $this->task->reference]),
        ];
    }
}
