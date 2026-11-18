<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 30 Jun 2023 13:24:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Notifications\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait WithPushNotifications
{
    public function fcmTokens(): MorphMany
    {
        return $this->morphMany(FcmToken::class, 'push_notifiable');
    }
}
