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


    public function handle(int $webUserID): ?DispatchedEmail
    {
        $webUser = WebUser::find($webUserID);
        if (!$webUser) {
            return null;
        }

        return $this->sendWebUserOutboxEmail(
            $webUser,
            OutboxCodeEnum::WEB_USER_REGISTRATION,
            [
                'customer_name' => $webUser->customer->name,
                'web_user_name' => $webUser->username,
                'email' => $webUser->email,
                'registration_date' => $webUser->created_at->format('F jS, Y'),
                'customer_id' => $webUser->customer_id,
                'web_user_id' => $webUser->id,
            ]
        );
    }

    public string $commandSignature = 'test:send-web-user-registration-email';

    public function asCommand(): void
    {
        $webUser = WebUser::first();

        if ($webUser) {
            $this->handle($webUser->id);
        }
    }
}
