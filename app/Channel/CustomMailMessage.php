<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 22 Jul 2024 08:37:11 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Channel;

use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Notifications\Messages\MailMessage;

class CustomMailMessage extends MailMessage
{
    public WebUser|User $notifiable;

    public function __construct($notifiable)
    {
        $this->notifiable = $notifiable;
    }

    public function data(): array
    {
        return array_merge($this->toArray(), array_merge($this->viewData, [
            'shop' => $this->notifiable->shop->name,
            'shop_url' => $this->notifiable->shop->website->domain.'/app/login',
        ]));
    }
}
