<?php

/*
 * Author: Louis Perez
 * Created: Mon, 21 September 2026 10:15
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Notification;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Notifications\Notification;

class MarkNotificationAsUnread
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(Notification $notification): Notification
    {
        return $this->update($notification, [
            'read_at' => null
        ]);
    }

    public function asController(Notification $notification): Notification
    {
        return $this->handle($notification);
    }
}
