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


    public function handle(WebUser $webUser, ?string $password): ?DispatchedEmail
    {
        if (!$webUser) {
            return null;
        }

        return $this->sendWebUserOutboxEmail(
            $webUser,
            OutboxCodeEnum::WEB_USER_REGISTRATION,
            [
                'customer_name' => $webUser->contact_name,
                'email' => $webUser->email,
                'password' => $password,
                'login_url' => $webUser->website->getUrl() . '/app/login', // TODO: Update this one later
            ]
        );
    }
}
