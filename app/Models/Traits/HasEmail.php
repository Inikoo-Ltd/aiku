<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 May 2023 00:49:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

trait HasEmail
{
    use Notifiable;

    public function routeNotificationForMail(Notification $notification): array|string
    {
        if (! app()->isProduction()) {
            return config('mail.testing_mail_to');
        }

        return $this->email;
    }
}
