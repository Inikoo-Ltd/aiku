<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Notification;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Notifications\Notification;

class MarkNotificationAsRead
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(Notification $notification): Notification
    {
        return $this->update($notification, [
            'read_at' => now(),
        ]);
    }

    public function asController(Notification $notification): Notification
    {
        return $this->handle($notification);
    }
}
