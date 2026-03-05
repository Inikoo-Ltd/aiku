<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Mar 2026 15:48:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendWebUserOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\WebUser;

class SendWebUserRegistrationEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendWebUserOutboxEmail;


    public function handle(WebUser $webUser, ?string $password = null): ?DispatchedEmail
    {
        if (!$webUser) {
            return null;
        }

        return $this->sendWebUserOutboxEmail(
            $webUser,
            OutboxCodeEnum::WEB_USER_REGISTRATION,
            [
                'web_user_contact_name' => $webUser->contact_name,
                'customer_email' => $webUser->email,
                'retina_login_link' => $webUser->website?->getUrl() . '/app/login',
                'customer_password' => $password,
            ]
        );
    }
}
